<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

require_login('1');

function manager_report_valid_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $date->format('Y-m-d') === $value ? $value : '';
}

function manager_report_format_date($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function manager_report_format_time($value): string
{
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('H:i', $timestamp) : '';
}

function manager_report_status_meta(): array
{
    return [
        'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => 'amber', 'icon' => 'fa-inbox'],
        'waiting' => ['label' => 'รอช่างรับงาน', 'tone' => 'blue', 'icon' => 'fa-hourglass-half'],
        'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => 'cyan', 'icon' => 'fa-handshake'],
        'pickup' => ['label' => 'ช่างกำลังไปรับสินค้า', 'tone' => 'cyan', 'icon' => 'fa-box-open'],
        'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => 'yellow', 'icon' => 'fa-box'],
        'working' => ['label' => 'กำลังติดตั้ง', 'tone' => 'violet', 'icon' => 'fa-screwdriver-wrench'],
        'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => 'orange', 'icon' => 'fa-user-check'],
        'done' => ['label' => 'เสร็จสิ้น', 'tone' => 'green', 'icon' => 'fa-circle-check'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => 'red', 'icon' => 'fa-ban'],
        'rejected' => ['label' => 'ช่างปฏิเสธงาน', 'tone' => 'orange', 'icon' => 'fa-user-xmark'],
        'overdue' => ['label' => 'เกินกำหนด', 'tone' => 'red', 'icon' => 'fa-clock'],
    ];
}

/* Mirrors the Manager assignment-list deadline rule without changing it. */
function manager_report_is_overdue(array $row, ?DateTimeImmutable $now = null): bool
{
    if (empty($row['assign_id'])) {
        return false;
    }

    $assign_status = (int) ($row['assign_status'] ?? 0);
    if (!in_array($assign_status, [1, 2], true)) {
        return false;
    }

    $install_date = trim((string) ($row['assign_install_date'] ?? ''));
    $install_start = trim((string) ($row['assign_install_time'] ?? ''));
    $install_end = trim((string) ($row['assign_install_end_time'] ?? ''));

    if ($install_date === '') {
        return false;
    }

    if ($install_end === '' && $install_start !== '') {
        $start_timestamp = strtotime($install_date . ' ' . $install_start);
        if ($start_timestamp !== false) {
            $install_end = date('H:i:s', $start_timestamp + 7200);
        }
    }

    if ($install_end === '') {
        return false;
    }

    $timezone = new DateTimeZone('Asia/Bangkok');
    $current = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $install_date . ' ' . $install_end, $timezone);

    if (!$deadline) {
        $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i', $install_date . ' ' . substr($install_end, 0, 5), $timezone);
    }

    return $deadline instanceof DateTimeImmutable && $deadline < $current;
}

/* One status function is shared by summary cards, bars and the latest table. */
function manager_report_status_key(array $row): string
{
    $setup_status = (string) ($row['setup_status'] ?? '');
    $assign_status = (string) ($row['assign_status'] ?? '');
    $has_receive = (string) ($row['has_receive'] ?? '0') === '1';
    $has_install_result = (string) ($row['has_install_result'] ?? '0') === '1';

    if ($setup_status === '5' || $assign_status === '4') {
        return 'cancelled';
    }

    if ($assign_status === '3') {
        return 'rejected';
    }

    if ($setup_status === '4' || $assign_status === '5') {
        return 'done';
    }

    if ($has_install_result && $setup_status === '3') {
        return 'review';
    }

    if (manager_report_is_overdue($row)) {
        return 'overdue';
    }

    if ($setup_status === '3') {
        return 'working';
    }

    if ($has_receive) {
        return 'received';
    }

    if ($setup_status === '2' && $assign_status === '2' && !$has_receive) {
        return 'pickup';
    }

    if ($setup_status === '2') {
        return 'accepted';
    }

    if ($setup_status === '1' || $assign_status === '1') {
        return 'waiting';
    }

    return 'unassigned';
}

function manager_report_contains(string $value, string $query): bool
{
    if ($query === '') {
        return true;
    }

    return mb_stripos($value, $query, 0, 'UTF-8') !== false;
}

$status_meta = manager_report_status_meta();
$report_timezone = new DateTimeZone('Asia/Bangkok');
$report_now = new DateTimeImmutable('now', $report_timezone);

$latest_assignment_join = '
    LEFT JOIN assignment a
        ON a.setup_id = s.setup_id
       AND a.assign_id = (
            SELECT a2.assign_id
            FROM assignment a2
            WHERE a2.setup_id = s.setup_id
            ORDER BY a2.assign_date DESC, a2.assign_id DESC
            LIMIT 1
       )
';

$report_sql = "
    SELECT
        s.setup_id,
        s.setup_status,
        s.created_at,
        s.setup_date,
        s.completed_at,
        c.customer_name,
        a.assign_id,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_install_end_time,
        a.assign_status,
        a.tech_id AS technician_id,
        COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), '-') AS technician_name,
        CASE WHEN EXISTS (
            SELECT 1
            FROM product_receive pr
            WHERE pr.assign_id = a.assign_id
              AND pr.receive_status = 1
        ) THEN 1 ELSE 0 END AS has_receive,
        CASE WHEN EXISTS (
            SELECT 1
            FROM installation_result ir
            WHERE ir.setup_id = s.setup_id
        ) THEN 1 ELSE 0 END AS has_install_result
    FROM setup s
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    {$latest_assignment_join}
    LEFT JOIN technicians t ON t.tech_id = a.tech_id
    ORDER BY s.created_at DESC, s.setup_id DESC
";

$report_result = $conn->query($report_sql);
$report_rows = [];
while ($row = $report_result->fetch_assoc()) {
    $row['status_key'] = manager_report_status_key($row);
    $row['status_meta'] = $status_meta[$row['status_key']];
    $row['technician_display'] = trim((string) ($row['technician_name'] ?? '')) ?: '-';
    $row['install_date_display'] = manager_report_format_date($row['assign_install_date'] ?? null);

    $install_start = manager_report_format_time($row['assign_install_time'] ?? null);
    $install_end = manager_report_format_time($row['assign_install_end_time'] ?? null);
    $row['install_time_display'] = $install_start === '' && $install_end === ''
        ? '-'
        : trim(($install_start !== '' ? $install_start : '-') . ' - ' . ($install_end !== '' ? $install_end : '-')) . ' น.';

    $report_rows[] = $row;
}

$status_counts = array_fill_keys(array_keys($status_meta), 0);
foreach ($report_rows as $report_row) {
    $status_counts[$report_row['status_key']]++;
}

$in_progress_keys = ['waiting', 'accepted', 'pickup', 'received', 'working'];
$in_progress_count = 0;
foreach ($in_progress_keys as $in_progress_key) {
    $in_progress_count += $status_counts[$in_progress_key] ?? 0;
}

$summary_cards = [
    ['label' => 'งานทั้งหมด', 'value' => count($report_rows), 'tone' => 'blue', 'icon' => 'fa-layer-group'],
    ['label' => 'รอมอบหมาย', 'value' => $status_counts['unassigned'], 'tone' => 'amber', 'icon' => 'fa-inbox'],
    ['label' => 'กำลังดำเนินการ', 'value' => $in_progress_count, 'tone' => 'violet', 'icon' => 'fa-bars-progress'],
    ['label' => 'รอยืนยันผล', 'value' => $status_counts['review'], 'tone' => 'orange', 'icon' => 'fa-user-check'],
    ['label' => 'เสร็จสิ้น', 'value' => $status_counts['done'], 'tone' => 'green', 'icon' => 'fa-circle-check'],
    ['label' => 'เกินกำหนด', 'value' => $status_counts['overdue'], 'tone' => 'red', 'icon' => 'fa-clock'],
];

$table_search = trim((string) ($_GET['q'] ?? ''));
$table_status = trim((string) ($_GET['status'] ?? ''));
if ($table_status !== '' && !array_key_exists($table_status, $status_meta)) {
    $table_status = '';
}

$table_rows = array_values(array_filter($report_rows, static function (array $row) use ($table_search, $table_status): bool {
    $search_matches = manager_report_contains((string) ($row['setup_id'] ?? ''), $table_search)
        || manager_report_contains((string) ($row['customer_name'] ?? ''), $table_search);

    return $search_matches && ($table_status === '' || $row['status_key'] === $table_status);
}));

$completed_day_counts = [];
$completed_week_counts = [];
$completed_month_counts = [];
$completed_rows_result = $conn->query(
    "SELECT s.setup_id, s.completed_at
     FROM setup s
     WHERE s.setup_status = 4
       AND s.completed_at IS NOT NULL
     ORDER BY s.completed_at ASC, s.setup_id ASC"
);

while ($completed_row = $completed_rows_result->fetch_assoc()) {
    $completed_at = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string) $completed_row['completed_at'],
        $report_timezone
    );
    if (!$completed_at) {
        continue;
    }

    $completed_date_key = $completed_at->format('Y-m-d');
    $completed_day_counts[$completed_date_key] ??= [0, 0, 0];
    $completed_hour = (int) $completed_at->format('G');
    $completed_day_slot = $completed_hour < 12 ? 0 : ($completed_hour < 17 ? 1 : 2);
    $completed_day_counts[$completed_date_key][$completed_day_slot]++;

    $week_start = $completed_at->modify('monday this week')->setTime(0, 0, 0);
    $week_key = $week_start->format('Y-m-d');
    $completed_week_counts[$week_key] ??= array_fill(0, 7, 0);
    $week_offset = (int) $week_start->diff($completed_at->setTime(0, 0, 0))->format('%r%a');
    if ($week_offset >= 0 && $week_offset < 7) {
        $completed_week_counts[$week_key][$week_offset]++;
    }

    $year_key = $completed_at->format('Y');
    $completed_month_counts[$year_key] ??= array_fill(0, 12, 0);
    $completed_month_counts[$year_key][(int) $completed_at->format('n') - 1]++;
}

$requested_chart_view = (string) ($_GET['period'] ?? 'day');
$chart_view = in_array($requested_chart_view, ['day', 'week', 'month'], true)
    ? $requested_chart_view
    : 'day';
$chart_day = manager_report_valid_date((string) ($_GET['chart_day'] ?? '')) ?: $report_now->format('Y-m-d');
$chart_week = preg_match('/^\d{4}-W\d{2}$/', (string) ($_GET['chart_week'] ?? ''))
    ? (string) $_GET['chart_week']
    : $report_now->format('o-\WW');
$chart_month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['chart_month'] ?? ''))
    ? (string) $_GET['chart_month']
    : $report_now->format('Y-m');

$completed_data_json = json_encode([
    'initialPeriod' => $chart_view,
    'initialDay' => $chart_day,
    'initialWeek' => $chart_week,
    'initialMonth' => $chart_month,
    'day' => ['dates' => $completed_day_counts],
    'week' => ['weeks' => $completed_week_counts],
    'month' => ['years' => $completed_month_counts],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$requested_tech_filter_period = (string) ($_GET['tech_filter_period'] ?? 'month');
$tech_filter_period = in_array($requested_tech_filter_period, ['week', 'month', 'all'], true)
    ? $requested_tech_filter_period
    : 'month';

$requested_tech_filter_value = trim((string) ($_GET['tech_filter_value'] ?? ''));
$tech_filter_week = preg_match('/^\d{4}-W\d{2}$/', $requested_tech_filter_value)
    ? $requested_tech_filter_value
    : (preg_match('/^\d{4}-W\d{2}$/', (string) ($_GET['tech_filter_week'] ?? ''))
        ? (string) $_GET['tech_filter_week']
        : $report_now->format('o-\\WW'));
$tech_filter_month = preg_match('/^\d{4}-\d{2}$/', $requested_tech_filter_value)
    ? $requested_tech_filter_value
    : (preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['tech_filter_month'] ?? ''))
        ? (string) $_GET['tech_filter_month']
        : $report_now->format('Y-m'));

$tech_filter_start = null;
$tech_filter_end = null;
if ($tech_filter_period === 'week' && preg_match('/^(\d{4})-W(\d{2})$/', $tech_filter_week, $week_match)) {
    $tech_filter_start = $report_now
        ->setISODate((int) $week_match[1], (int) $week_match[2], 1)
        ->setTime(0, 0, 0);
    $tech_filter_end = $tech_filter_start->modify('+7 days');
} elseif ($tech_filter_period === 'month') {
    $tech_filter_start = DateTimeImmutable::createFromFormat('!Y-m-d', $tech_filter_month . '-01', $report_timezone);
    if ($tech_filter_start) {
        $tech_filter_end = $tech_filter_start->modify('+1 month');
    }
}

$tech_filter_matches = static function ($value) use ($tech_filter_start, $tech_filter_end, $report_timezone): bool {
    if (!$tech_filter_start || !$tech_filter_end) {
        return true;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $report_timezone);
    return $date instanceof DateTimeImmutable && $date >= $tech_filter_start && $date < $tech_filter_end;
};

$technician_report_rows = [];
$technician_result = $conn->query(
    "SELECT tech_id,
            COALESCE(NULLIF(TRIM(tech_fullname), ''), NULLIF(TRIM(tech_name), ''), tech_id) AS technician_name
     FROM technicians
     ORDER BY technician_name ASC, tech_id ASC"
);
while ($technician_row = $technician_result->fetch_assoc()) {
    $technician_id = trim((string) ($technician_row['tech_id'] ?? ''));
    if ($technician_id === '') {
        continue;
    }

    $technician_report_rows[$technician_id] = [
        'technician_id' => $technician_id,
        'technician_name' => trim((string) ($technician_row['technician_name'] ?? '')) ?: $technician_id,
        'assigned' => 0,
        'completed' => 0,
    ];
}

foreach ($report_rows as $report_row) {
    $technician_id = trim((string) ($report_row['technician_id'] ?? ''));
    if ($technician_id === '') {
        continue;
    }

    if (!isset($technician_report_rows[$technician_id])) {
        $technician_report_rows[$technician_id] = [
            'technician_id' => $technician_id,
            'technician_name' => trim((string) ($report_row['technician_name'] ?? '')) ?: $technician_id,
            'assigned' => 0,
            'completed' => 0,
        ];
    }

    $setup_status = (int) ($report_row['setup_status'] ?? 0);
    $assign_status = (int) ($report_row['assign_status'] ?? 0);
    $is_current_assignment = in_array($assign_status, [1, 2], true)
        && !in_array($setup_status, [4, 5], true);

    if ($is_current_assignment && $tech_filter_matches($report_row['assign_date'] ?? null)) {
        $technician_report_rows[$technician_id]['assigned']++;
    }

    $is_completed = $setup_status === 4 && trim((string) ($report_row['completed_at'] ?? '')) !== '';
    if ($is_completed && $tech_filter_matches($report_row['completed_at'] ?? null)) {
        $technician_report_rows[$technician_id]['completed']++;
    }
}

$technician_report_rows = array_values($technician_report_rows);
usort($technician_report_rows, static function (array $left, array $right): int {
    return strcmp($left['technician_name'], $right['technician_name'])
        ?: strcmp($left['technician_id'], $right['technician_id']);
});

$tech_assignment_total = 0;
$tech_completed_total = 0;
$tech_assignment_max = 0;
$tech_completed_max = 0;
foreach ($technician_report_rows as $technician_report_row) {
    $tech_assignment_total += (int) $technician_report_row['assigned'];
    $tech_completed_total += (int) $technician_report_row['completed'];
    $tech_assignment_max = max($tech_assignment_max, (int) $technician_report_row['assigned']);
    $tech_completed_max = max($tech_completed_max, (int) $technician_report_row['completed']);
}

$requested_trend_week = (string) ($_GET['tech_trend_week'] ?? '');
$tech_trend_week = preg_match('/^\d{4}-W\d{2}$/', $requested_trend_week)
    ? $requested_trend_week
    : $report_now->format('o-\\WW');
$trend_week_match = [];
if (preg_match('/^(\d{4})-W(\d{2})$/', $tech_trend_week, $trend_week_match)) {
    $trend_week_start = $report_now
        ->setISODate((int) $trend_week_match[1], (int) $trend_week_match[2], 1)
        ->setTime(0, 0, 0);
} else {
    $trend_week_start = $report_now->modify('monday this week')->setTime(0, 0, 0);
    $tech_trend_week = $trend_week_start->format('o-\\WW');
}

$trend_week_key = $trend_week_start->format('Y-m-d');
$weekly_trend_counts = $completed_week_counts[$trend_week_key] ?? array_fill(0, 7, 0);
$weekly_trend_counts = array_pad(array_slice(array_map('intval', $weekly_trend_counts), 0, 7), 7, 0);
$weekly_trend_max = max(1, max($weekly_trend_counts));
$weekly_trend_labels = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
$weekly_trend_points = [];
$weekly_trend_x_positions = [];
$weekly_trend_svg_width = 760;
$weekly_trend_svg_height = 238;
$weekly_trend_plot_left = 44;
$weekly_trend_plot_right = 18;
$weekly_trend_plot_top = 24;
$weekly_trend_plot_bottom = 46;
$weekly_trend_plot_width = $weekly_trend_svg_width - $weekly_trend_plot_left - $weekly_trend_plot_right;
$weekly_trend_plot_height = $weekly_trend_svg_height - $weekly_trend_plot_top - $weekly_trend_plot_bottom;
foreach ($weekly_trend_counts as $trend_index => $trend_count) {
    $trend_x = $weekly_trend_plot_left + ($weekly_trend_plot_width * ($trend_index / 6));
    $trend_y = $weekly_trend_plot_top + $weekly_trend_plot_height - (($trend_count / $weekly_trend_max) * $weekly_trend_plot_height);
    $weekly_trend_points[] = number_format($trend_x, 2, '.', '') . ',' . number_format($trend_y, 2, '.', '');
    $weekly_trend_x_positions[] = $trend_x;
}

$trend_previous_week = $trend_week_start->modify('-7 days')->format('o-\\WW');
$trend_next_week = $trend_week_start->modify('+7 days')->format('o-\\WW');
$trend_previous_query = $_GET;
$trend_previous_query['tech_trend_week'] = $trend_previous_week;
$trend_next_query = $_GET;
$trend_next_query['tech_trend_week'] = $trend_next_week;
$tech_trend_previous_url = app_system_url('manager/reports.php') . '?' . http_build_query($trend_previous_query);
$tech_trend_next_url = app_system_url('manager/reports.php') . '?' . http_build_query($trend_next_query);

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && in_array($ajax_section, ['table', 'technician', 'trend'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if ($ajax_section === 'table') {
        $rows = array_map(static function (array $row): array {
            $meta = $row['status_meta'] ?? [];
            return [
                'setup_id' => (string) ($row['setup_id'] ?? '-'),
                'customer_name' => trim((string) ($row['customer_name'] ?? '')) ?: '-',
                'technician_display' => trim((string) ($row['technician_display'] ?? '')) ?: '-',
                'install_date_display' => (string) ($row['install_date_display'] ?? '-'),
                'install_time_display' => (string) ($row['install_time_display'] ?? '-'),
                'status_label' => (string) ($meta['label'] ?? '-'),
                'status_tone' => (string) ($meta['tone'] ?? 'slate'),
                'status_icon' => (string) ($meta['icon'] ?? 'fa-circle-question'),
            ];
        }, $table_rows);
        echo json_encode(['success' => true, 'section' => 'table', 'rows' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($ajax_section === 'technician') {
        $rows = array_map(static function (array $row): array {
            return [
                'technician_id' => (string) ($row['technician_id'] ?? ''),
                'technician_name' => (string) ($row['technician_name'] ?? '-'),
                'assigned' => (int) ($row['assigned'] ?? 0),
                'completed' => (int) ($row['completed'] ?? 0),
            ];
        }, $technician_report_rows);
        echo json_encode([
            'success' => true,
            'section' => 'technician',
            'period' => $tech_filter_period,
            'week' => $tech_filter_week,
            'month' => $tech_filter_month,
            'rows' => $rows,
            'assigned_total' => $tech_assignment_total,
            'completed_total' => $tech_completed_total,
            'assigned_max' => $tech_assignment_max,
            'completed_max' => $tech_completed_max,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode([
        'success' => true,
        'section' => 'trend',
        'week' => $tech_trend_week,
        'start' => $trend_week_start->format('d/m/Y'),
        'end' => $trend_week_start->modify('+6 days')->format('d/m/Y'),
        'previous_week' => $trend_previous_week,
        'next_week' => $trend_next_week,
        'labels' => array_values($weekly_trend_labels),
        'values' => array_values(array_map('intval', $weekly_trend_counts)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

layout_header('รายงาน', 'manager_reports', 'สรุปข้อมูลและสถานะงานติดตั้งทั้งหมด');
?>
<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/reports.css')) ?>?v=<?= h(asset_version('admin/assets/css/reports.css')) ?>">
<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/reports.css')) ?>?v=<?= h(asset_version('manager/assets/css/reports.css')) ?>">

<div class="manager-report-page">
    <header class="report-header">
        <div class="report-header-copy">
            <h1>รายงานงานติดตั้ง</h1>
            <p>สรุปข้อมูลและสถานะงานติดตั้งทั้งหมด</p>
        </div>
        <div class="report-header-actions">
            <button type="button" class="report-print-btn" onclick="window.print()">
                <i class="fa-solid fa-print" aria-hidden="true"></i>
                <span>พิมพ์รายงาน</span>
            </button>
        </div>
    </header>

    <section class="report-metric-grid manager-report-metric-grid" aria-label="สรุปงานติดตั้ง">
        <?php foreach ($summary_cards as $summary_card): ?>
            <article class="report-metric-card manager-report-summary-card">
                <span class="report-metric-icon tone-<?= h($summary_card['tone']) ?>">
                    <i class="fa-solid <?= h($summary_card['icon']) ?>" aria-hidden="true"></i>
                </span>
                <div class="report-metric-copy">
                    <span><?= h($summary_card['label']) ?></span>
                    <strong><?= h(number_format((int) $summary_card['value'])) ?></strong>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="manager-report-filter report-panel" aria-labelledby="manager-report-filter-title">
        <div class="report-panel-head manager-report-filter-head">
            <div>
                <h2 id="manager-report-filter-title">ค้นหาใบงาน</h2>
                <p>กรองรายการในตารางจากข้อมูลใบงานทั้งหมดของระบบ</p>
            </div>
            <span class="report-panel-total" data-manager-table-total><?= h(number_format(count($table_rows))) ?> รายการ</span>
        </div>
        <form class="manager-report-filter-form" method="get" action="<?= h(app_system_url('manager/reports.php')) ?>" data-manager-report-filter-form>
            <label class="manager-report-field">
                <span>รหัสใบงาน / ลูกค้า</span>
                <input type="search" name="q" value="<?= h($table_search) ?>" placeholder="ค้นหารหัสใบงานหรือลูกค้า">
            </label>
            <label class="manager-report-field">
                <span>สถานะ</span>
                <select name="status">
                    <option value="">ทุกสถานะ</option>
                    <?php foreach ($status_meta as $status_key => $status_item): ?>
                        <option value="<?= h($status_key) ?>"<?= $table_status === $status_key ? ' selected' : '' ?>><?= h($status_item['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="manager-report-filter-actions">
                <button type="submit" class="manager-report-submit">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    ค้นหา
                </button>
                <a class="manager-report-reset" href="<?= h(app_system_url('manager/reports.php')) ?>">ล้าง</a>
            </div>
        </form>
    </section>

    <section class="manager-report-visual-grid" aria-label="ภาพรวม workflow และงานที่เสร็จแล้ว">
        <article class="report-panel completed-install-card" data-manager-completed-card>
            <div class="report-panel-head completed-install-header">
                <div>
                    <h2>งานติดตั้งเสร็จแล้ว</h2>
                    <p>สรุปจำนวนงานที่ติดตั้งเสร็จตามช่วงเวลา</p>
                </div>
                <div class="completed-install-controls">
                    <div class="completed-period-tabs" role="group" aria-label="ช่วงเวลางานติดตั้งเสร็จแล้ว">
                        <?php foreach (['day' => 'วัน', 'week' => 'สัปดาห์', 'month' => 'เดือน'] as $period_key => $period_label): ?>
                            <button type="button" class="completed-period-tab<?= $chart_view === $period_key ? ' is-active' : '' ?>" data-manager-period="<?= h($period_key) ?>" aria-pressed="<?= $chart_view === $period_key ? 'true' : 'false' ?>">
                                <?= h($period_label) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <label class="manager-period-picker">
                        <input
                            type="<?= $chart_view === 'week' ? 'week' : ($chart_view === 'month' ? 'month' : 'date') ?>"
                            class="completed-range-input"
                            data-manager-period-input
                            value="<?= h($chart_view === 'week' ? $chart_week : ($chart_view === 'month' ? $chart_month : $chart_day)) ?>"
                            aria-label="<?= h($chart_view === 'week' ? 'เลือกสัปดาห์' : ($chart_view === 'month' ? 'เลือกเดือน' : 'เลือกวันที่')) ?>"
                        >
                    </label>
                </div>
            </div>

            <div class="completed-summary-row">
                <div class="completed-install-summary" data-manager-completed-summary aria-live="polite"></div>
            </div>
            <div class="completed-install-chart" data-manager-completed-chart data-period="<?= h($chart_view) ?>" aria-live="polite"></div>
        </article>

    </section>

    <section class="report-panel manager-technician-filter-panel" aria-labelledby="manager-technician-report-title">
        <div class="report-panel-head manager-technician-filter-head">
            <div>
                <h2 id="manager-technician-report-title">รายงานงานของช่าง</h2>
                <p>ดูจำนวนงานตามการมอบหมายล่าสุดและงานที่ปิดสำเร็จ</p>
            </div>
            <form class="manager-technician-period-form" method="get" action="<?= h(app_system_url('manager/reports.php')) ?>" data-manager-technician-period-form>
                <input type="hidden" name="tech_filter_period" value="<?= h($tech_filter_period) ?>" data-manager-technician-period>
                <?php foreach (['q', 'status', 'period', 'chart_day', 'chart_week', 'chart_month', 'tech_filter_week', 'tech_filter_month', 'tech_trend_week'] as $preserve_key): ?>
                    <?php if (isset($_GET[$preserve_key]) && !is_array($_GET[$preserve_key])): ?>
                        <input type="hidden" name="<?= h($preserve_key) ?>" value="<?= h((string) $_GET[$preserve_key]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="manager-technician-period-tabs" role="group" aria-label="ช่วงเวลารายงานงานของช่าง">
                    <?php foreach (['week' => 'สัปดาห์', 'month' => 'เดือน', 'all' => 'ทั้งหมด'] as $period_key => $period_label): ?>
                        <button type="button" class="manager-technician-period-tab<?= $tech_filter_period === $period_key ? ' is-active' : '' ?>" data-manager-technician-period-option="<?= h($period_key) ?>" aria-pressed="<?= $tech_filter_period === $period_key ? 'true' : 'false' ?>"><?= h($period_label) ?></button>
                    <?php endforeach; ?>
                </div>
                <label class="manager-technician-period-picker" data-manager-technician-period-picker>
                    <input
                        type="<?= $tech_filter_period === 'week' ? 'week' : 'month' ?>"
                        name="tech_filter_value"
                        value="<?= h($tech_filter_period === 'week' ? $tech_filter_week : $tech_filter_month) ?>"
                        data-manager-technician-period-input
                        data-week-value="<?= h($tech_filter_week) ?>"
                        data-month-value="<?= h($tech_filter_month) ?>"
                        aria-label="<?= h($tech_filter_period === 'week' ? 'เลือกสัปดาห์' : 'เลือกเดือน') ?>"
                        <?= $tech_filter_period === 'all' ? ' disabled' : '' ?>
                    >
                </label>
            </form>
        </div>
    </section>

    <section class="manager-technician-chart-grid" aria-label="รายงานงานของช่างตามช่วงเวลา">
        <article class="report-panel manager-technician-chart-card" aria-labelledby="manager-assigned-tech-title" data-manager-technician-card="assigned">
            <div class="report-panel-head manager-technician-chart-head">
                <div>
                    <h2 id="manager-assigned-tech-title">งานที่มอบหมายให้ช่าง</h2>
                    <p>จำนวนใบงานที่ช่างแต่ละคนได้รับมอบหมาย</p>
                </div>
                <span class="report-panel-total" data-manager-technician-total="assigned"><?= h(number_format($tech_assignment_total)) ?> งาน</span>
            </div>
            <?php if ($tech_assignment_total === 0): ?>
                <div class="report-empty-state manager-technician-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลในช่วงเวลาที่เลือก</span></div>
            <?php else: ?>
                <div class="manager-technician-bars" role="list">
                    <?php foreach ($technician_report_rows as $technician_report_row): ?>
                        <?php $assigned_total = (int) $technician_report_row['assigned']; ?>
                        <div class="manager-technician-bar-row<?= $assigned_total === 0 ? ' is-zero' : '' ?>" role="listitem" title="<?= h($technician_report_row['technician_name'] . ': ' . number_format($assigned_total) . ' งาน') ?>">
                            <div class="manager-technician-bar-label"><span><?= h($technician_report_row['technician_name']) ?></span><strong><?= h(number_format($assigned_total)) ?> งาน</strong></div>
                            <div class="manager-technician-bar-track"><span style="width: <?= h($tech_assignment_max > 0 ? (($assigned_total / $tech_assignment_max) * 100) : 0) ?>%;"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="report-panel manager-technician-chart-card" aria-labelledby="manager-completed-tech-title" data-manager-technician-card="completed">
            <div class="report-panel-head manager-technician-chart-head">
                <div>
                    <h2 id="manager-completed-tech-title">งานที่ช่างทำเสร็จแล้ว</h2>
                    <p>จำนวนงานที่ปิดงานสำเร็จของช่างแต่ละคน</p>
                </div>
                <span class="report-panel-total" data-manager-technician-total="completed"><?= h(number_format($tech_completed_total)) ?> งาน</span>
            </div>
            <?php if ($tech_completed_total === 0): ?>
                <div class="report-empty-state manager-technician-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลในช่วงเวลาที่เลือก</span></div>
            <?php else: ?>
                <div class="manager-technician-bars" role="list">
                    <?php foreach ($technician_report_rows as $technician_report_row): ?>
                        <?php $completed_total = (int) $technician_report_row['completed']; ?>
                        <div class="manager-technician-bar-row<?= $completed_total === 0 ? ' is-zero' : '' ?>" role="listitem" title="<?= h($technician_report_row['technician_name'] . ': ' . number_format($completed_total) . ' งาน') ?>">
                            <div class="manager-technician-bar-label"><span><?= h($technician_report_row['technician_name']) ?></span><strong><?= h(number_format($completed_total)) ?> งาน</strong></div>
                            <div class="manager-technician-bar-track"><span style="width: <?= h($tech_completed_max > 0 ? (($completed_total / $tech_completed_max) * 100) : 0) ?>%;"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="report-panel manager-weekly-trend-panel" aria-labelledby="manager-weekly-trend-title" data-manager-weekly-trend>
        <div class="report-panel-head manager-weekly-trend-head">
            <div>
                <h2 id="manager-weekly-trend-title">แนวโน้มงานเสร็จในสัปดาห์</h2>
                <p>จำนวนงานที่ติดตั้งเสร็จในแต่ละวัน</p>
            </div>
            <div class="manager-weekly-trend-controls" aria-label="เลือกสัปดาห์">
                <a class="manager-weekly-trend-arrow" href="<?= h($tech_trend_previous_url) ?>" data-manager-weekly-trend-arrow="previous" data-week="<?= h($trend_previous_week) ?>" aria-label="สัปดาห์ก่อน">&lsaquo;</a>
                <span><?= h($trend_week_start->format('d/m/Y')) ?> - <?= h($trend_week_start->modify('+6 days')->format('d/m/Y')) ?></span>
                <a class="manager-weekly-trend-arrow" href="<?= h($tech_trend_next_url) ?>" data-manager-weekly-trend-arrow="next" data-week="<?= h($trend_next_week) ?>" aria-label="สัปดาห์ถัดไป">&rsaquo;</a>
            </div>
        </div>
        <div class="manager-weekly-trend-chart-wrap">
            <svg class="manager-weekly-trend-chart" viewBox="0 0 <?= h($weekly_trend_svg_width) ?> <?= h($weekly_trend_svg_height) ?>" role="img" aria-label="จำนวนงานที่ติดตั้งเสร็จในแต่ละวันของสัปดาห์">
                <?php foreach ([0, .5, 1] as $grid_ratio): ?>
                    <?php $grid_y = $weekly_trend_plot_top + $weekly_trend_plot_height - ($weekly_trend_plot_height * $grid_ratio); ?>
                    <line class="manager-weekly-trend-grid-line" x1="<?= h($weekly_trend_plot_left) ?>" x2="<?= h($weekly_trend_svg_width - $weekly_trend_plot_right) ?>" y1="<?= h($grid_y) ?>" y2="<?= h($grid_y) ?>"></line>
                    <text class="manager-weekly-trend-y-label" x="<?= h($weekly_trend_plot_left - 10) ?>" y="<?= h($grid_y + 4) ?>" text-anchor="end"><?= h(number_format((int) round($weekly_trend_max * $grid_ratio))) ?></text>
                <?php endforeach; ?>
                <polyline class="manager-weekly-trend-line" points="<?= h(implode(' ', $weekly_trend_points)) ?>"></polyline>
                <?php foreach ($weekly_trend_counts as $trend_index => $trend_count): ?>
                    <?php $trend_y = $weekly_trend_plot_top + $weekly_trend_plot_height - (($trend_count / $weekly_trend_max) * $weekly_trend_plot_height); ?>
                    <circle class="manager-weekly-trend-point" cx="<?= h($weekly_trend_x_positions[$trend_index]) ?>" cy="<?= h($trend_y) ?>" r="4" tabindex="0">
                        <title><?= h($weekly_trend_labels[$trend_index]) ?>: <?= h(number_format($trend_count)) ?> งาน</title>
                    </circle>
                    <text class="manager-weekly-trend-x-label" x="<?= h($weekly_trend_x_positions[$trend_index]) ?>" y="<?= h($weekly_trend_svg_height - 16) ?>" text-anchor="middle"><?= h($weekly_trend_labels[$trend_index]) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
    </section>

    <section class="report-panel report-table-panel report-data-panel manager-report-table-panel" aria-labelledby="manager-latest-title" data-manager-report-table-panel>
        <div class="report-panel-head">
            <div>
                <h2 id="manager-latest-title">ใบงานติดตั้งล่าสุด</h2>
                <p>ใช้ assignment ล่าสุดของแต่ละ setup และแสดง 1 ใบงานต่อ 1 แถว</p>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table manager-report-table">
                <thead>
                    <tr>
                        <th>รหัสใบงาน</th>
                        <th>ลูกค้า</th>
                        <th>ช่าง</th>
                        <th>วันที่ติดตั้ง</th>
                        <th>เวลา</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody data-manager-report-table-body>
                    <?php if ($table_rows === []): ?>
                        <tr>
                            <td colspan="6"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบใบงานตามเงื่อนไขที่เลือก</span></div></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($table_rows as $table_row): ?>
                            <?php $table_status = $table_row['status_meta']; ?>
                            <tr>
                                <td><strong><?= h($table_row['setup_id']) ?></strong></td>
                                <td><?= h($table_row['customer_name'] ?: '-') ?></td>
                                <td><?= h($table_row['technician_display']) ?></td>
                                <td><?= h($table_row['install_date_display']) ?></td>
                                <td><?= h($table_row['install_time_display']) ?></td>
                                <td><span class="report-status-label"><i class="fa-solid <?= h($table_status['icon']) ?> tone-<?= h($table_status['tone']) ?>" aria-hidden="true"></i><?= h($table_status['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
window.managerCompletedReport = <?= $completed_data_json ?: '{}' ?>;
</script>
<script src="<?= h(app_asset_url('manager/assets/js/reports.js')) ?>?v=<?= h(asset_version('manager/assets/js/reports.js')) ?>"></script>
<script src="<?= h(app_asset_url('manager/assets/js/technician_reports.js')) ?>?v=<?= h(asset_version('manager/assets/js/technician_reports.js')) ?>"></script>

<?php layout_footer(); ?>
