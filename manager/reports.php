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

function manager_report_timestamp($value, DateTimeZone $timezone): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value, $timezone);
    } catch (Throwable $e) {
        return null;
    }
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
$current_manager_id = trim((string) ($_SESSION['user_id'] ?? ''));

if ($current_manager_id === '') {
    redirect_to(app_system_url('manager/index.php?status=error'));
}

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
        COALESCE(NULLIF(TRIM(t.tech_name), ''), '-') AS technician_name,
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
    WHERE a.assign_by = ?
    ORDER BY s.created_at DESC, s.setup_id DESC
";

$report_stmt = $conn->prepare($report_sql);
$report_stmt->bind_param('s', $current_manager_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
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

$table_search = trim((string) ($_GET['q'] ?? ''));
$table_status = trim((string) ($_GET['status'] ?? ''));
$in_progress_keys = ['waiting', 'accepted', 'pickup', 'received', 'working'];
$manager_status_groups = [
    '' => array_keys($status_meta),
    'unassigned' => ['unassigned'],
    'in_progress' => $in_progress_keys,
    'review' => ['review'],
    'done' => ['done'],
    'cancelled_rejected' => ['cancelled', 'rejected'],
    'overdue' => ['overdue'],
];
$manager_status_labels = [
    '' => 'ทุกสถานะ',
    'unassigned' => 'ยังไม่ได้มอบหมาย',
    'in_progress' => 'กำลังดำเนินการ',
    'review' => 'รอหัวหน้าช่างยืนยัน',
    'done' => 'เสร็จสิ้น',
    'cancelled_rejected' => 'ยกเลิก/ปฏิเสธ',
    'overdue' => 'เกินกำหนด',
];
if (!array_key_exists($table_status, $manager_status_groups)) {
    $table_status = '';
}
$technician_filter = trim((string) ($_GET['technician_id'] ?? ''));
$table_period = in_array((string) ($_GET['table_period'] ?? ''), ['all', 'today', 'month', 'year', 'custom'], true)
    ? (string) $_GET['table_period']
    : 'all';
$table_date_from = manager_report_valid_date((string) ($_GET['table_date_from'] ?? ''));
$table_date_to = manager_report_valid_date((string) ($_GET['table_date_to'] ?? ''));
$table_period_start = null;
$table_period_end = null;
switch ($table_period) {
    case 'today':
        $table_period_start = $report_now->setTime(0, 0, 0);
        $table_period_end = $table_period_start->modify('+1 day');
        break;
    case 'month':
        $table_period_start = $report_now->modify('first day of this month')->setTime(0, 0, 0);
        $table_period_end = $table_period_start->modify('+1 month');
        break;
    case 'year':
        $table_period_start = $report_now->setDate((int) $report_now->format('Y'), 1, 1)->setTime(0, 0, 0);
        $table_period_end = $table_period_start->modify('+1 year');
        break;
    case 'custom':
        if ($table_date_from !== '') {
            $table_period_start = DateTimeImmutable::createFromFormat('!Y-m-d', $table_date_from, $report_timezone);
        }
        if ($table_date_to !== '') {
            $custom_end = DateTimeImmutable::createFromFormat('!Y-m-d', $table_date_to, $report_timezone);
            $table_period_end = $custom_end instanceof DateTimeImmutable ? $custom_end->modify('+1 day') : null;
        }
        break;
}

$manager_report_matches_period = static function ($value, ?DateTimeImmutable $period_start, ?DateTimeImmutable $period_end) use ($report_timezone): bool {
    if (!$period_start && !$period_end) {
        return true;
    }

    $date = manager_report_timestamp($value, $report_timezone);
    if (!$date) {
        return false;
    }

    return (!$period_start || $date >= $period_start) && (!$period_end || $date < $period_end);
};

$table_rows = array_values(array_filter($report_rows, static function (array $row) use ($table_search, $table_status, $technician_filter, $manager_status_groups, $table_period_start, $table_period_end, $manager_report_matches_period): bool {
    $search_matches = manager_report_contains((string) ($row['setup_id'] ?? ''), $table_search)
        || manager_report_contains((string) ($row['customer_name'] ?? ''), $table_search)
        || manager_report_contains((string) ($row['technician_id'] ?? ''), $table_search)
        || manager_report_contains((string) ($row['technician_name'] ?? ''), $table_search);
    $status_matches = in_array((string) ($row['status_key'] ?? ''), $manager_status_groups[$table_status] ?? [], true);
    $technician_matches = $technician_filter === '' || (string) ($row['technician_id'] ?? '') === $technician_filter;
    $time_matches = $manager_report_matches_period($row['created_at'] ?? null, $table_period_start, $table_period_end);

    return $search_matches && $status_matches && $technician_matches && $time_matches;
}));

$status_counts = array_fill_keys(array_keys($status_meta), 0);
foreach ($table_rows as $table_row) {
    $status_counts[$table_row['status_key']]++;
}

$in_progress_count = 0;
foreach ($in_progress_keys as $in_progress_key) {
    $in_progress_count += $status_counts[$in_progress_key] ?? 0;
}

$summary_cards = [
    ['label' => 'งานทั้งหมด', 'value' => count($table_rows), 'tone' => 'blue', 'icon' => 'fa-layer-group'],
    ['label' => 'รอมอบหมาย', 'value' => $status_counts['unassigned'], 'tone' => 'amber', 'icon' => 'fa-inbox'],
    ['label' => 'กำลังดำเนินการ', 'value' => $in_progress_count, 'tone' => 'violet', 'icon' => 'fa-bars-progress'],
    ['label' => 'รอยืนยันผล', 'value' => $status_counts['review'], 'tone' => 'orange', 'icon' => 'fa-user-check'],
    ['label' => 'เสร็จสิ้น', 'value' => $status_counts['done'], 'tone' => 'green', 'icon' => 'fa-circle-check'],
    ['label' => 'เกินกำหนด', 'value' => $status_counts['overdue'], 'tone' => 'red', 'icon' => 'fa-clock'],
];

$manager_technician_options = [];
if ($current_manager_id !== '') {
    $manager_technician_stmt = $conn->prepare(
        "SELECT DISTINCT
                t.tech_id,
                COALESCE(NULLIF(TRIM(t.tech_name), ''), t.tech_id) AS technician_name,
                t.tech_status
         FROM setup s
         INNER JOIN assignment a
           ON a.setup_id = s.setup_id
          AND a.assign_id = (
              SELECT latest_a.assign_id
              FROM assignment latest_a
              WHERE latest_a.setup_id = s.setup_id
              ORDER BY latest_a.assign_date DESC, latest_a.assign_id DESC
              LIMIT 1
          )
         INNER JOIN technicians t ON t.tech_id = a.tech_id
         WHERE a.assign_by = ?
           AND a.tech_id IS NOT NULL
           AND TRIM(a.tech_id) <> ''
         ORDER BY technician_name ASC, t.tech_id ASC"
    );
    $manager_technician_stmt->bind_param('s', $current_manager_id);
    $manager_technician_stmt->execute();
    $manager_technician_result = $manager_technician_stmt->get_result();
    while ($technician_row = $manager_technician_result->fetch_assoc()) {
        $technician_id = trim((string) ($technician_row['tech_id'] ?? ''));
        if ($technician_id === '') {
            continue;
        }

        $manager_technician_options[$technician_id] = [
            'technician_id' => $technician_id,
            'technician_name' => trim((string) ($technician_row['technician_name'] ?? '')) ?: $technician_id,
            'tech_status' => (int) ($technician_row['tech_status'] ?? 1),
        ];
    }
    $manager_technician_stmt->close();
}

if ($technician_filter !== '' && !isset($manager_technician_options[$technician_filter])) {
    $technician_filter = '';
}

$technician_report_rows = [];
foreach ($table_rows as $report_row) {
    $technician_id = trim((string) ($report_row['technician_id'] ?? ''));
    if ($technician_id === '' || !isset($manager_technician_options[$technician_id])) {
        continue;
    }

    if (!isset($technician_report_rows[$technician_id])) {
        $technician_option = $manager_technician_options[$technician_id];
        $technician_report_rows[$technician_id] = [
            'technician_id' => $technician_id,
            'technician_name' => $technician_option['technician_name'],
            'tech_status' => $technician_option['tech_status'],
            'readiness_label' => $technician_option['tech_status'] === 0 ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน',
            'readiness_tone' => $technician_option['tech_status'] === 0 ? 'green' : 'red',
            'readiness_icon' => $technician_option['tech_status'] === 0 ? 'fa-circle-check' : 'fa-circle-xmark',
            'assigned' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'review' => 0,
            'overdue' => 0,
        ];
    }

    /* $table_rows is the single filtered, latest-assignment-per-setup dataset. */
    $technician_report_rows[$technician_id]['assigned']++;

    $setup_status = (int) ($report_row['setup_status'] ?? 0);
    $is_completed = $setup_status === 4 && trim((string) ($report_row['completed_at'] ?? '')) !== '';
    if ($is_completed) {
        $technician_report_rows[$technician_id]['completed']++;
    }

    $status_key = (string) ($report_row['status_key'] ?? '');
    if (in_array($status_key, $in_progress_keys, true)) {
        $technician_report_rows[$technician_id]['in_progress']++;
    } elseif ($status_key === 'review') {
        $technician_report_rows[$technician_id]['review']++;
    } elseif ($status_key === 'overdue') {
        $technician_report_rows[$technician_id]['overdue']++;
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
$technician_chart_max = max(1, $tech_assignment_max, $tech_completed_max);

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && in_array($ajax_section, ['table', 'technician'], true)) {
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
        echo json_encode([
            'success' => true,
            'section' => 'table',
            'rows' => $rows,
            'total' => count($rows),
            'metrics' => [
                'total' => count($table_rows),
                'unassigned' => (int) ($status_counts['unassigned'] ?? 0),
                'in_progress' => (int) $in_progress_count,
                'review' => (int) ($status_counts['review'] ?? 0),
                'done' => (int) ($status_counts['done'] ?? 0),
                'overdue' => (int) ($status_counts['overdue'] ?? 0),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($ajax_section === 'technician') {
        $rows = array_map(static function (array $row): array {
            return [
                'technician_id' => (string) ($row['technician_id'] ?? ''),
                'technician_name' => (string) ($row['technician_name'] ?? '-'),
                'readiness_label' => (string) ($row['readiness_label'] ?? '-'),
                'readiness_tone' => (string) ($row['readiness_tone'] ?? 'slate'),
                'readiness_icon' => (string) ($row['readiness_icon'] ?? 'fa-circle-question'),
                'assigned' => (int) ($row['assigned'] ?? 0),
                'completed' => (int) ($row['completed'] ?? 0),
                'in_progress' => (int) ($row['in_progress'] ?? 0),
                'review' => (int) ($row['review'] ?? 0),
                'overdue' => (int) ($row['overdue'] ?? 0),
            ];
        }, $technician_report_rows);
        echo json_encode([
            'success' => true,
            'section' => 'technician',
            'technician_id' => $technician_filter,
            'status' => $table_status,
            'table_period' => $table_period,
            'table_date_from' => $table_date_from,
            'table_date_to' => $table_date_to,
            'rows' => $rows,
            'assigned_total' => $tech_assignment_total,
            'completed_total' => $tech_completed_total,
            'assigned_max' => $tech_assignment_max,
            'completed_max' => $tech_completed_max,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$manager_report_company = system_company_data($conn);
$manager_report_print_date = (new DateTimeImmutable('now'))->format('d/m/Y');

layout_header('รายงาน', 'manager_reports', 'สรุปข้อมูลและสถานะงานติดตั้งทั้งหมด');
?>
<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/reports.css')) ?>?v=<?= h(asset_version('admin/assets/css/reports.css')) ?>">
<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/reports.css')) ?>?v=<?= h(asset_version('manager/assets/css/reports.css')) ?>">

<div class="manager-report-page">
    <table class="manager-print-document" role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <td>
    <header class="report-print-document-header" aria-label="หัวเอกสารรายงาน">
        <div class="report-print-brand">
            <?php if ($manager_report_company['system_logo_url'] !== ''): ?>
                <img class="report-print-logo" src="<?= h($manager_report_company['system_logo_url']) ?>" alt="โลโก้บริษัท">
            <?php else: ?>
                <span class="report-print-logo-placeholder" aria-label="ไม่มีโลโก้บริษัท">-</span>
            <?php endif; ?>
            <div class="report-print-company-copy">
                <p class="report-print-company-name"><?= h($manager_report_company['system_name']) ?></p>
                <p class="report-print-company-address"><?= h($manager_report_company['company_address']) ?></p>
                <p class="report-print-company-identifiers">
                    <span>เลขทะเบียนนิติบุคคล: <?= h($manager_report_company['company_registration_no']) ?></span>
                    <span aria-hidden="true">|</span>
                    <span>เลขประจำตัวผู้เสียภาษี: <?= h($manager_report_company['tax_id']) ?></span>
                </p>
            </div>
        </div>
        <div class="report-print-meta">
            <h2>รายงานงานติดตั้ง</h2>
            <p data-manager-report-print-date data-timezone="<?= h(date_default_timezone_get()) ?>">วันที่พิมพ์: <?= h($manager_report_print_date) ?></p>
        </div>
    </header>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>

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

    <section class="manager-report-filter" aria-label="ตัวกรองรายงานงานติดตั้ง">
        <form class="manager-report-filter-form" method="get" action="<?= h(app_system_url('manager/reports.php')) ?>" data-manager-report-filter-form>
            <label class="manager-report-search-field">
                <span class="sr-only">ค้นหาใบงาน ลูกค้า หรือช่าง</span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" value="<?= h($table_search) ?>" placeholder="ค้นหาใบงาน ลูกค้า หรือช่าง">
            </label>
            <label class="manager-report-field manager-report-technician-field">
                <span class="sr-only">กรองช่าง</span>
                <select name="technician_id">
                    <option value="">ช่างทั้งหมด</option>
                    <?php foreach ($manager_technician_options as $technician_option): ?>
                        <option value="<?= h($technician_option['technician_id']) ?>" <?= $technician_filter === $technician_option['technician_id'] ? 'selected' : '' ?>><?= h($technician_option['technician_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="manager-report-field">
                <span class="sr-only">กรองสถานะ</span>
                <select name="status">
                    <?php foreach ($manager_status_labels as $status_key => $status_label): ?>
                        <option value="<?= h($status_key) ?>" <?= $table_status === $status_key ? 'selected' : '' ?>><?= h($status_label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="manager-report-field manager-report-period-field">
                <span class="sr-only">ช่วงเวลาสร้างใบงาน</span>
                <select name="table_period" data-manager-table-period>
                    <option value="all" <?= $table_period === 'all' ? 'selected' : '' ?>>ช่วงเวลาทั้งหมด</option>
                    <option value="today" <?= $table_period === 'today' ? 'selected' : '' ?>>วันนี้</option>
                    <option value="month" <?= $table_period === 'month' ? 'selected' : '' ?>>เดือนนี้</option>
                    <option value="year" <?= $table_period === 'year' ? 'selected' : '' ?>>ปีนี้</option>
                    <option value="custom" <?= $table_period === 'custom' ? 'selected' : '' ?>>กำหนดช่วงวันที่</option>
                </select>
            </label>
            <div class="manager-report-date-range" data-manager-table-date-range<?= $table_period === 'custom' ? '' : ' hidden' ?>>
                <label class="manager-report-date-field">
                    <span>วันที่เริ่มต้น</span>
                    <input type="date" name="table_date_from" value="<?= h($table_date_from) ?>" data-manager-table-date-from>
                </label>
                <label class="manager-report-date-field">
                    <span>วันที่สิ้นสุด</span>
                    <input type="date" name="table_date_to" value="<?= h($table_date_to) ?>" data-manager-table-date-to>
                </label>
            </div>
            <div class="manager-report-filter-actions">
                <button type="submit" class="manager-report-submit">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    ค้นหา
                </button>
                <a class="manager-report-reset" href="<?= h(app_system_url('manager/reports.php')) ?>" data-manager-report-filter-reset>ล้างตัวกรอง</a>
            </div>
        </form>
    </section>

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

    <div class="manager-technician-report-grid">
    <section class="report-panel manager-technician-chart-card manager-technician-clustered-card" aria-labelledby="manager-technician-chart-title" data-manager-technician-chart>
        <div class="report-panel-head manager-technician-chart-head">
            <div>
                <h2 id="manager-technician-chart-title">กราฟเปรียบเทียบจำนวนงานที่ได้รับมอบหมาย</h2>

            </div>
            <div class="manager-technician-chart-legend" aria-label="คำอธิบายกราฟ">
                <span><i class="manager-technician-legend-dot is-assigned" aria-hidden="true"></i>ได้รับมอบหมาย</span>
                <span><i class="manager-technician-legend-dot is-completed" aria-hidden="true"></i>เสร็จสิ้น</span>
            </div>
        </div>
        <div class="manager-technician-clustered-chart" data-manager-technician-chart-body role="img" aria-label="กราฟเปรียบเทียบงานที่ได้รับมอบหมายและงานที่เสร็จสิ้นของช่างแต่ละคน">
            <div class="manager-technician-chart-axis" aria-hidden="true">
                <span><?= h(number_format($technician_chart_max)) ?></span>
                <span><?= h(number_format((int) round($technician_chart_max / 2))) ?></span>
                <span>0</span>
            </div>
            <div class="manager-technician-chart-plot">
                <div class="manager-technician-chart-grid-lines" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <div class="manager-technician-cluster-groups">
                    <?php foreach ($technician_report_rows as $technician_report_row): ?>
                        <?php
                        $assigned_total = (int) $technician_report_row['assigned'];
                        $completed_total = (int) $technician_report_row['completed'];
                        $technician_name = (string) $technician_report_row['technician_name'];
                        ?>
                        <div class="manager-technician-cluster-group" role="group" aria-label="<?= h($technician_name) ?>">
                            <div class="manager-technician-cluster-bars">
                                <div class="manager-technician-cluster-bar is-assigned" style="--bar-height: <?= h(($assigned_total / $technician_chart_max) * 100) ?>%;" role="img" aria-label="<?= h($technician_name . ': ได้รับมอบหมาย ' . number_format($assigned_total) . ' งาน') ?>">
                                    <strong><?= h(number_format($assigned_total)) ?></strong>
                                </div>
                                <div class="manager-technician-cluster-bar is-completed" style="--bar-height: <?= h(($completed_total / $technician_chart_max) * 100) ?>%;" role="img" aria-label="<?= h($technician_name . ': เสร็จสิ้น ' . number_format($completed_total) . ' งาน') ?>">
                                    <strong><?= h(number_format($completed_total)) ?></strong>
                                </div>
                            </div>
                            <span class="manager-technician-cluster-label"><?= h($technician_name) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="report-empty-state manager-technician-empty-state" data-manager-technician-empty<?= $technician_report_rows === [] ? '' : ' hidden' ?>><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลตามตัวกรองที่เลือก</span></div>
    </section>

    <section class="report-panel report-table-panel report-data-panel manager-technician-summary-panel" aria-labelledby="manager-technician-summary-title" data-manager-technician-summary-panel>
        <div class="report-panel-head">
            <div>
                <h2 id="manager-technician-summary-title">สถานะงานปัจจุบันของช่าง</h2>

            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table manager-technician-summary-table">
                <thead>
                    <tr>
                        <th>รหัสช่าง</th>
                        <th>ชื่อช่าง</th>
                        <th>กำลังดำเนินการ</th>
                        <th>รอหัวหน้าช่างยืนยัน</th>
                        <th>เกินกำหนด</th>
                    </tr>
                </thead>
                <tbody data-manager-technician-summary-body>
                    <?php if ($technician_report_rows === []): ?>
                        <tr>
                            <td colspan="5"><div class="report-empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>ไม่พบข้อมูลช่างตามตัวกรองที่เลือก</span></div></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($technician_report_rows as $technician_report_row): ?>
                            <tr>
                                <td>
                                    <strong><?= h($technician_report_row['technician_id']) ?></strong>
                                </td>
                                <td><span class="manager-technician-summary-name"><?= h($technician_report_row['technician_name']) ?></span></td>
                                <td><?= h(number_format((int) $technician_report_row['in_progress'])) ?></td>
                                <td><?= h(number_format((int) $technician_report_row['review'])) ?></td>
                                <td><?= h(number_format((int) $technician_report_row['overdue'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    </div>

    <section class="report-panel report-table-panel report-data-panel manager-report-table-panel" aria-labelledby="manager-latest-title" data-manager-report-table-panel>
        <div class="report-panel-head">
            <div>
                <h2 id="manager-latest-title">ใบงานติดตั้งล่าสุด</h2>
                
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
                            <?php $row_status_meta = $table_row['status_meta']; ?>
                            <tr>
                                <td><strong><?= h($table_row['setup_id']) ?></strong></td>
                                <td><?= h($table_row['customer_name'] ?: '-') ?></td>
                                <td><?= h($table_row['technician_display']) ?></td>
                                <td><?= h($table_row['install_date_display']) ?></td>
                                <td><?= h($table_row['install_time_display']) ?></td>
                                <td><span class="report-status-label"><i class="fa-solid <?= h($row_status_meta['icon']) ?> tone-<?= h($row_status_meta['tone']) ?>" aria-hidden="true"></i><?= h($row_status_meta['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script src="<?= h(app_asset_url('manager/assets/js/reports.js')) ?>?v=<?= h(asset_version('manager/assets/js/reports.js')) ?>"></script>
<script src="<?= h(app_asset_url('manager/assets/js/technician_reports.js')) ?>?v=<?= h(asset_version('manager/assets/js/technician_reports.js')) ?>"></script>
<script>
(function () {
    'use strict';

    const page = document.querySelector('.manager-report-page');
    const form = page?.querySelector('[data-manager-report-filter-form]');
    const periodSelect = form?.querySelector('[data-manager-table-period]');
    const dateRange = form?.querySelector('[data-manager-table-date-range]');
    const dateFrom = form?.querySelector('[data-manager-table-date-from]');
    const dateTo = form?.querySelector('[data-manager-table-date-to]');
    if (!page || !form || !periodSelect || !dateRange || !dateFrom || !dateTo || page.dataset.tableFilterDropdownReady === 'true') {
        return;
    }

    page.dataset.tableFilterDropdownReady = 'true';

    const syncDateRange = () => {
        const isCustom = periodSelect.value === 'custom';
        dateRange.hidden = !isCustom;
        [dateFrom, dateTo].forEach((input) => {
            input.disabled = false;
            if (!isCustom) {
                input.value = '';
            }
        });
    };

    const submitFilter = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    };

    periodSelect.addEventListener('change', syncDateRange);
    syncDateRange();

    form.querySelector('[data-manager-report-filter-reset]')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        form.querySelector('[name="q"]').value = '';
        form.querySelector('[name="status"]').value = '';
        form.querySelector('[name="technician_id"]').value = '';
        periodSelect.value = 'all';
        dateFrom.value = '';
        dateTo.value = '';
        syncDateRange();
        submitFilter();
    }, true);

    const originalFetch = window.fetch.bind(window);
    window.fetch = async function () {
        const response = await originalFetch.apply(window, arguments);
        try {
            const request = arguments[0];
            const requestUrl = new URL(typeof request === 'string' ? request : request.url, window.location.href);
            if (requestUrl.searchParams.get('section') !== 'table') {
                return response;
            }

            const payload = await response.clone().json();
            if (!payload.success || !payload.metrics) {
                return response;
            }

            const values = ['total', 'unassigned', 'in_progress', 'review', 'done', 'overdue'];
            const cards = page.querySelectorAll('.manager-report-metric-grid .report-metric-card strong');
            values.forEach((key, index) => {
                if (cards[index]) {
                    cards[index].textContent = Number(payload.metrics[key] || 0).toLocaleString('en-US');
                }
            });

            const url = new URL(window.location.href);
            ['q', 'technician_id', 'status', 'table_period', 'table_date_from', 'table_date_to'].forEach((key) => {
                const value = requestUrl.searchParams.get(key) || '';
                if (value) {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });
            url.searchParams.delete('table_period_value');
            url.searchParams.delete('ajax');
            url.searchParams.delete('section');
            window.history.replaceState({}, '', url);
        } catch (error) {
            // The existing report handler owns request errors and rendering.
        }
        return response;
    };
}());
</script>

<?php layout_footer(); ?>
