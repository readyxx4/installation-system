<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = trim((string) ($_SESSION['user_id'] ?? ''));
if ($tech_id === '') {
    redirect_to(app_public_url('login.html?error=login'));
}

$report_timezone = new DateTimeZone('Asia/Bangkok');
$report_now = new DateTimeImmutable('now', $report_timezone);

function technician_report_valid_week(string $value, DateTimeZone $timezone): string
{
    $value = trim($value);
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches)) {
        return '';
    }

    $year = (int) $matches[1];
    $week = (int) $matches[2];
    if ($week < 1 || $week > 53) {
        return '';
    }

    $date = (new DateTimeImmutable('now', $timezone))->setISODate($year, $week, 1)->setTime(0, 0, 0);
    return $date->format('o-\\WW') === $value ? $value : '';
}

function technician_report_valid_month(string $value, DateTimeZone $timezone): string
{
    $value = trim($value);
    if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01', $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $date->format('Y-m') === $value ? $value : '';
}

function technician_report_week_start(string $value, DateTimeZone $timezone): DateTimeImmutable
{
    $valid_week = technician_report_valid_week($value, $timezone);
    if ($valid_week === '') {
        $valid_week = (new DateTimeImmutable('now', $timezone))->format('o-\\WW');
    }

    preg_match('/^(\d{4})-W(\d{2})$/', $valid_week, $matches);
    return (new DateTimeImmutable('now', $timezone))
        ->setISODate((int) $matches[1], (int) $matches[2], 1)
        ->setTime(0, 0, 0);
}

function technician_report_month_start(string $value, DateTimeZone $timezone): DateTimeImmutable
{
    $valid_month = technician_report_valid_month($value, $timezone);
    if ($valid_month === '') {
        $valid_month = (new DateTimeImmutable('now', $timezone))->format('Y-m');
    }

    return DateTimeImmutable::createFromFormat('!Y-m-d', $valid_month . '-01', $timezone);
}

function technician_report_timestamp($value, DateTimeZone $timezone): ?DateTimeImmutable
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

function technician_report_format_date($value, DateTimeZone $timezone): string
{
    $date = technician_report_timestamp($value, $timezone);
    return $date ? $date->format('d/m/Y') : '--';
}

function technician_report_format_time_range($start, $end, DateTimeZone $timezone): string
{
    $start_date = technician_report_timestamp($start, $timezone);
    $end_date = technician_report_timestamp($end, $timezone);
    if (!$start_date) {
        return '--';
    }

    $start_text = $start_date->format('H:i');
    return $end_date ? $start_text . ' - ' . $end_date->format('H:i') . ' น.' : $start_text . ' น.';
}

function technician_report_is_completed(array $row): bool
{
    return (string) ($row['setup_status'] ?? '') === '4'
        && trim((string) ($row['completed_at'] ?? '')) !== '';
}

function technician_report_status_key(array $row): string
{
    $setup_status = (string) ($row['setup_status'] ?? '');
    $assign_status = (string) ($row['assign_status'] ?? '');
    $has_receive = (string) ($row['has_receive'] ?? '0') === '1';
    $has_install_result = (string) ($row['has_install_result'] ?? '0') === '1';

    if (technician_report_is_completed($row) || $assign_status === '5') {
        return 'done';
    }

    if ($assign_status === '4') {
        return 'cancelled';
    }

    if ($assign_status === '3') {
        return 'rejected';
    }

    if ($setup_status === '3' && $has_install_result) {
        return 'review';
    }

    if ($setup_status === '3') {
        return 'installing';
    }

    if ($setup_status === '2' && $assign_status === '2' && $has_receive) {
        return 'ready';
    }

    if ($setup_status === '2' && $assign_status === '2') {
        return 'awaiting_receive';
    }

    return 'assigned';
}

function technician_report_chart_data(
    array $rows,
    string $period,
    string $week_value,
    string $month_value,
    string $date_field,
    DateTimeZone $timezone
): array {
    if ($period === 'month') {
        $month_start = technician_report_month_start($month_value, $timezone);
        $days_in_month = (int) $month_start->format('t');
        $bucket_count = (int) ceil($days_in_month / 7);
        $values = array_fill(0, $bucket_count, 0);
        $labels = [];

        for ($index = 0; $index < $bucket_count; $index++) {
            $labels[] = 'สัปดาห์ที่ ' . ($index + 1);
        }

        foreach ($rows as $row) {
            $date = technician_report_timestamp($row[$date_field] ?? null, $timezone);
            if (!$date || $date->format('Y-m') !== $month_start->format('Y-m')) {
                continue;
            }

            $bucket = intdiv(((int) $date->format('j')) - 1, 7);
            if (isset($values[$bucket])) {
                $values[$bucket]++;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'range_label' => $month_start->format('m/Y'),
        ];
    }

    $week_start = technician_report_week_start($week_value, $timezone);
    $values = array_fill(0, 7, 0);
    $labels = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
    $week_end = $week_start->modify('+7 days');

    foreach ($rows as $row) {
        $date = technician_report_timestamp($row[$date_field] ?? null, $timezone);
        if (!$date || $date < $week_start || $date >= $week_end) {
            continue;
        }

        $day_index = (int) $week_start->diff($date->setTime(0, 0, 0))->days;
        if (isset($values[$day_index])) {
            $values[$day_index]++;
        }
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'range_label' => $week_start->format('d/m/Y') . ' - ' . $week_start->modify('+6 days')->format('d/m/Y'),
    ];
}

function technician_report_product_text(array $row): string
{
    $item_count = (int) ($row['item_count'] ?? 0);
    if ($item_count > 1) {
        return $item_count . ' รายการ';
    }

    $product_names = trim((string) ($row['product_names'] ?? ''));
    if ($product_names !== '') {
        return $product_names;
    }

    return trim((string) ($row['primary_product'] ?? '')) ?: '--';
}

$latest_assignment_join = '
    INNER JOIN assignment a
        ON a.setup_id = s.setup_id
       AND a.assign_id = (
            SELECT latest_a.assign_id
            FROM assignment latest_a
            WHERE latest_a.setup_id = s.setup_id
            ORDER BY latest_a.assign_date DESC, latest_a.assign_id DESC
            LIMIT 1
       )
';

$report_sql = "
    SELECT
        s.setup_id,
        s.setup_status,
        s.setup_date,
        s.completed_at,
        c.customer_name,
        p.pro_name AS primary_product,
        a.assign_id,
        a.tech_id,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_install_end_time,
        a.assign_status,
        EXISTS (
            SELECT 1
            FROM installation_result ir
            WHERE ir.setup_id = s.setup_id
        ) AS has_install_result,
        EXISTS (
            SELECT 1
            FROM product_receive pr
            WHERE pr.assign_id = a.assign_id
              AND pr.receive_status = 1
        ) AS has_receive,
        COALESCE(ds.item_count, 0) AS item_count,
        ds.product_names
    FROM setup s
    {$latest_assignment_join}
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    LEFT JOIN product p ON p.pro_id = s.pro_id
    LEFT JOIN (
        SELECT
            d.setup_id,
            COUNT(*) AS item_count,
            GROUP_CONCAT(
                DISTINCT COALESCE(NULLIF(TRIM(p2.pro_name), ''), d.pro_id)
                ORDER BY d.detail_id
                SEPARATOR ', '
            ) AS product_names
        FROM install_detail d
        LEFT JOIN product p2 ON p2.pro_id = d.pro_id
        GROUP BY d.setup_id
    ) ds ON ds.setup_id = s.setup_id
    WHERE a.tech_id = ?
    ORDER BY a.assign_date DESC, a.assign_id DESC
";

$report_stmt = $conn->prepare($report_sql);
$report_stmt->bind_param('s', $tech_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
$report_rows = [];

while ($row = $report_result->fetch_assoc()) {
    $row['status_key'] = technician_report_status_key($row);
    $report_rows[] = $row;
}

$completed_rows = array_values(array_filter($report_rows, 'technician_report_is_completed'));
usort($completed_rows, static function (array $left, array $right): int {
    $left_time = strtotime((string) ($left['completed_at'] ?? '')) ?: 0;
    $right_time = strtotime((string) ($right['completed_at'] ?? '')) ?: 0;

    return ($right_time <=> $left_time)
        ?: strcmp((string) ($right['setup_id'] ?? ''), (string) ($left['setup_id'] ?? ''));
});

$current_month = $report_now->format('Y-m');
$summary_counts = [
    'assigned' => count($report_rows),
    'in_progress' => 0,
    'review' => 0,
    'completed' => count($completed_rows),
    'completed_month' => 0,
];

foreach ($report_rows as $row) {
    if (in_array($row['status_key'], ['assigned', 'awaiting_receive', 'ready', 'installing'], true)) {
        $summary_counts['in_progress']++;
    }

    if ($row['status_key'] === 'review') {
        $summary_counts['review']++;
    }

    $completed_at = technician_report_timestamp($row['completed_at'] ?? null, $report_timezone);
    if ($completed_at && $completed_at->format('Y-m') === $current_month && technician_report_is_completed($row)) {
        $summary_counts['completed_month']++;
    }
}

$summary_cards = [
    ['label' => 'งานที่ได้รับทั้งหมด', 'value' => $summary_counts['assigned'], 'tone' => 'blue', 'icon' => 'fa-briefcase'],
    ['label' => 'กำลังดำเนินการ', 'value' => $summary_counts['in_progress'], 'tone' => 'cyan', 'icon' => 'fa-bars-progress'],
    ['label' => 'รอหัวหน้าช่างยืนยัน', 'value' => $summary_counts['review'], 'tone' => 'amber', 'icon' => 'fa-user-check'],
    ['label' => 'เสร็จสิ้นทั้งหมด', 'value' => $summary_counts['completed'], 'tone' => 'green', 'icon' => 'fa-circle-check'],
    ['label' => 'เสร็จเดือนนี้', 'value' => $summary_counts['completed_month'], 'tone' => 'violet', 'icon' => 'fa-calendar-check'],
];

$assigned_period = in_array((string) ($_GET['assigned_period'] ?? ''), ['week', 'month'], true)
    ? (string) $_GET['assigned_period']
    : 'week';
$completed_period = in_array((string) ($_GET['completed_period'] ?? ''), ['week', 'month'], true)
    ? (string) $_GET['completed_period']
    : 'week';

$default_week = $report_now->format('o-\\WW');
$default_month = $report_now->format('Y-m');
$assigned_week = technician_report_valid_week((string) ($_GET['assigned_value'] ?? ''), $report_timezone) ?: $default_week;
$assigned_month = technician_report_valid_month((string) ($_GET['assigned_value'] ?? ''), $report_timezone) ?: $default_month;
$completed_week = technician_report_valid_week((string) ($_GET['completed_value'] ?? ''), $report_timezone) ?: $default_week;
$completed_month = technician_report_valid_month((string) ($_GET['completed_value'] ?? ''), $report_timezone) ?: $default_month;

if ($assigned_period === 'month') {
    $assigned_value = $assigned_month;
} else {
    $assigned_value = $assigned_week;
}

if ($completed_period === 'month') {
    $completed_value = $completed_month;
} else {
    $completed_value = $completed_week;
}

$assigned_chart = technician_report_chart_data(
    $report_rows,
    $assigned_period,
    $assigned_week,
    $assigned_month,
    'assign_date',
    $report_timezone
);
$completed_chart = technician_report_chart_data(
    $completed_rows,
    $completed_period,
    $completed_week,
    $completed_month,
    'completed_at',
    $report_timezone
);

$trend_week = technician_report_valid_week((string) ($_GET['trend_week'] ?? ''), $report_timezone) ?: $default_week;
$trend_week_start = technician_report_week_start($trend_week, $report_timezone);
$trend_week_end = $trend_week_start->modify('+7 days');
$trend_values = array_fill(0, 7, 0);

foreach ($completed_rows as $row) {
    $completed_at = technician_report_timestamp($row['completed_at'] ?? null, $report_timezone);
    if (!$completed_at || $completed_at < $trend_week_start || $completed_at >= $trend_week_end) {
        continue;
    }

    $day_index = (int) $trend_week_start->diff($completed_at->setTime(0, 0, 0))->days;
    if (isset($trend_values[$day_index])) {
        $trend_values[$day_index]++;
    }
}

$trend_max = max(1, ...$trend_values);
$trend_svg_width = 820;
$trend_svg_height = 280;
$trend_plot_left = 52;
$trend_plot_right = 24;
$trend_plot_top = 24;
$trend_plot_bottom = 48;
$trend_plot_width = $trend_svg_width - $trend_plot_left - $trend_plot_right;
$trend_plot_height = $trend_svg_height - $trend_plot_top - $trend_plot_bottom;
$trend_x_positions = [];
$trend_points = [];

foreach ($trend_values as $index => $value) {
    $x = $trend_plot_left + ($trend_plot_width * ($index / 6));
    $y = $trend_plot_top + $trend_plot_height - (($value / $trend_max) * $trend_plot_height);
    $trend_x_positions[] = $x;
    $trend_points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
}

$trend_previous_query = $_GET;
$trend_previous_query['trend_week'] = $trend_week_start->modify('-7 days')->format('o-\\WW');
$trend_next_query = $_GET;
$trend_next_query['trend_week'] = $trend_week_start->modify('+7 days')->format('o-\\WW');
$trend_previous_url = app_system_url('technician/report.php') . '?' . http_build_query($trend_previous_query);
$trend_next_url = app_system_url('technician/report.php') . '?' . http_build_query($trend_next_query);

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && in_array($ajax_section, ['assigned', 'completed', 'trend'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    if ($ajax_section === 'trend') {
        echo json_encode([
            'success' => true,
            'section' => 'trend',
            'week' => $trend_week,
            'start' => $trend_week_start->format('d/m/Y'),
            'end' => $trend_week_start->modify('+6 days')->format('d/m/Y'),
            'previous_week' => $trend_week_start->modify('-7 days')->format('o-\\WW'),
            'next_week' => $trend_week_start->modify('+7 days')->format('o-\\WW'),
            'labels' => ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'],
            'values' => array_values(array_map('intval', $trend_values)),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $chart = $ajax_section === 'assigned' ? $assigned_chart : $completed_chart;
    echo json_encode([
        'success' => true,
        'section' => $ajax_section,
        'period' => $ajax_section === 'assigned' ? $assigned_period : $completed_period,
        'value' => $ajax_section === 'assigned' ? $assigned_value : $completed_value,
        'labels' => array_values($chart['labels']),
        'values' => array_values(array_map('intval', $chart['values'])),
        'total' => (int) array_sum($chart['values']),
        'range_label' => (string) $chart['range_label'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$latest_completed_rows = array_slice($completed_rows, 0, 10);

layout_header('รายงานของฉัน', 'technician_report', 'สรุปงานติดตั้งและผลงานของคุณ');
?>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/report.css')) ?>?v=<?= h(asset_version('technician/assets/css/report.css')) ?>"
>

<main class="technician-report-page">
  <header class="technician-report-hero">
    <div class="technician-report-hero-copy">
      <h1>รายงานของฉัน</h1>
      <p>สรุปงานติดตั้งและผลงานของคุณ</p>
    </div>
    <div class="technician-report-hero-actions">
      <button class="technician-report-print-button no-print" type="button" onclick="window.print()">
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        <span>พิมพ์รายงาน</span>
      </button>
    </div>
  </header>

  <section class="technician-report-summary-grid" aria-label="สรุปผลงานของฉัน">
    <?php foreach ($summary_cards as $summary_card): ?>
      <article class="technician-report-summary-card tone-<?= h($summary_card['tone']) ?>">
        <span class="technician-report-summary-icon" aria-hidden="true">
          <i class="fa-solid <?= h($summary_card['icon']) ?>"></i>
        </span>
        <div>
          <span><?= h($summary_card['label']) ?></span>
          <strong><?= h(number_format((int) $summary_card['value'])) ?></strong>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="technician-report-chart-grid" aria-label="กราฟผลงานของฉัน">
    <?php foreach ([
        ['key' => 'assigned', 'title' => 'งานที่ได้รับมอบหมาย', 'subtitle' => 'จำนวนงานที่คุณได้รับในแต่ละช่วงเวลา', 'period' => $assigned_period, 'value' => $assigned_value, 'week' => $assigned_week, 'month' => $assigned_month, 'chart' => $assigned_chart, 'field' => 'assigned'],
        ['key' => 'completed', 'title' => 'งานที่ทำเสร็จแล้ว', 'subtitle' => 'จำนวนงานที่ปิดงานสำเร็จตามช่วงเวลา', 'period' => $completed_period, 'value' => $completed_value, 'week' => $completed_week, 'month' => $completed_month, 'chart' => $completed_chart, 'field' => 'completed'],
    ] as $chart_card): ?>
      <article class="technician-report-panel technician-report-chart-card">
        <div class="technician-report-panel-head">
          <div>
            <h2><?= h($chart_card['title']) ?></h2>
            <p><?= h($chart_card['subtitle']) ?></p>
          </div>
          <form class="technician-report-period-form no-print" method="get" action="<?= h(app_system_url('technician/report.php')) ?>" data-technician-period-form>
            <input type="hidden" name="<?= h($chart_card['field']) ?>_period" value="<?= h($chart_card['period']) ?>" data-technician-period-field>
            <?php if ($chart_card['field'] === 'assigned'): ?>
              <input type="hidden" name="completed_period" value="<?= h($completed_period) ?>">
              <input type="hidden" name="completed_value" value="<?= h($completed_value) ?>">
            <?php else: ?>
              <input type="hidden" name="assigned_period" value="<?= h($assigned_period) ?>">
              <input type="hidden" name="assigned_value" value="<?= h($assigned_value) ?>">
            <?php endif; ?>
            <input type="hidden" name="trend_week" value="<?= h($trend_week) ?>">
            <div class="technician-report-period-tabs" role="group" aria-label="เลือกช่วงเวลา">
              <button type="button" class="technician-report-period-tab<?= $chart_card['period'] === 'week' ? ' is-active' : '' ?>" data-technician-period-option="week" aria-pressed="<?= $chart_card['period'] === 'week' ? 'true' : 'false' ?>">สัปดาห์</button>
              <button type="button" class="technician-report-period-tab<?= $chart_card['period'] === 'month' ? ' is-active' : '' ?>" data-technician-period-option="month" aria-pressed="<?= $chart_card['period'] === 'month' ? 'true' : 'false' ?>">เดือน</button>
            </div>
            <label class="technician-report-period-picker">
              <span class="sr-only">เลือกช่วงเวลา</span>
              <input
                type="<?= $chart_card['period'] === 'month' ? 'month' : 'week' ?>"
                name="<?= h($chart_card['field']) ?>_value"
                value="<?= h($chart_card['value']) ?>"
                data-technician-period-input
                data-week-value="<?= h($chart_card['week']) ?>"
                data-month-value="<?= h($chart_card['month']) ?>"
                aria-label="เลือก<?= $chart_card['period'] === 'month' ? 'เดือน' : 'สัปดาห์' ?>"
              >
            </label>
          </form>
        </div>

        <?php $chart_total = array_sum($chart_card['chart']['values']); ?>
        <?php $chart_is_empty = $chart_total === 0; ?>
        <div class="technician-report-chart-summary">
          <strong><?= h(number_format($chart_total)) ?> งาน</strong>
          <span><?= h($chart_card['chart']['range_label']) ?></span>
        </div>

        <div class="technician-report-bar-chart-wrap<?= $chart_is_empty ? ' is-empty' : '' ?>">
          <div class="technician-report-bar-chart<?= $chart_is_empty ? ' is-empty' : '' ?>" style="--technician-bar-count: <?= h((string) count($chart_card['chart']['labels'])) ?>;">
            <?php $bar_max = max(1, ...$chart_card['chart']['values']); ?>
            <?php foreach ($chart_card['chart']['values'] as $bar_index => $bar_value): ?>
              <?php $bar_height = $bar_value > 0 ? max(8, ($bar_value / $bar_max) * 100) : 0; ?>
              <div class="technician-report-bar-item<?= $bar_value === 0 ? ' is-zero' : '' ?>">
                <div class="technician-report-bar-stage">
                  <div class="technician-report-bar-fill" style="height: <?= h(number_format($bar_height, 2, '.', '')) ?>%;">
                    <?php if ($bar_value > 0): ?><strong><?= h(number_format($bar_value)) ?></strong><?php endif; ?>
                  </div>
                </div>
                <span><?= h($chart_card['chart']['labels'][$bar_index]) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($chart_is_empty): ?>
            <p class="technician-report-chart-empty">ยังไม่มีข้อมูลในช่วงเวลานี้</p>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="technician-report-panel technician-report-trend-card" aria-labelledby="technician-report-trend-title">
    <div class="technician-report-panel-head technician-report-trend-head">
      <div>
        <h2 id="technician-report-trend-title">แนวโน้มงานเสร็จในสัปดาห์</h2>
        <p>จำนวนงานที่เสร็จในแต่ละวัน</p>
      </div>
      <div class="technician-report-week-controls no-print" aria-label="เลือกสัปดาห์">
        <a href="<?= h($trend_previous_url) ?>" data-technician-trend-arrow="previous" data-week="<?= h($trend_week_start->modify('-7 days')->format('o-\\WW')) ?>" aria-label="สัปดาห์ก่อน">&lsaquo;</a>
        <span><?= h($trend_week_start->format('d/m/Y')) ?> - <?= h($trend_week_start->modify('+6 days')->format('d/m/Y')) ?></span>
        <a href="<?= h($trend_next_url) ?>" data-technician-trend-arrow="next" data-week="<?= h($trend_week_start->modify('+7 days')->format('o-\\WW')) ?>" aria-label="สัปดาห์ถัดไป">&rsaquo;</a>
      </div>
    </div>

    <div class="technician-report-line-chart-wrap">
      <svg class="technician-report-line-chart" viewBox="0 0 <?= h((string) $trend_svg_width) ?> <?= h((string) $trend_svg_height) ?>" preserveAspectRatio="none" role="img" aria-label="แนวโน้มงานเสร็จในสัปดาห์">
        <?php foreach ([0, .5, 1] as $grid_ratio): ?>
          <?php $grid_y = $trend_plot_top + $trend_plot_height - ($trend_plot_height * $grid_ratio); ?>
          <line class="technician-report-grid-line" x1="<?= h((string) $trend_plot_left) ?>" x2="<?= h((string) ($trend_svg_width - $trend_plot_right)) ?>" y1="<?= h((string) $grid_y) ?>" y2="<?= h((string) $grid_y) ?>"></line>
          <text class="technician-report-y-label" x="<?= h((string) ($trend_plot_left - 10)) ?>" y="<?= h((string) ($grid_y + 4)) ?>" text-anchor="end"><?= h(number_format((int) round($trend_max * $grid_ratio))) ?></text>
        <?php endforeach; ?>
        <polyline class="technician-report-line" points="<?= h(implode(' ', $trend_points)) ?>"></polyline>
        <?php foreach ($trend_values as $trend_index => $trend_value): ?>
          <?php $trend_y = $trend_plot_top + $trend_plot_height - (($trend_value / $trend_max) * $trend_plot_height); ?>
          <circle class="technician-report-point" cx="<?= h((string) $trend_x_positions[$trend_index]) ?>" cy="<?= h((string) $trend_y) ?>" r="4" tabindex="0">
            <title><?= h(['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'][$trend_index]) ?>: <?= h(number_format($trend_value)) ?> งาน</title>
          </circle>
          <text class="technician-report-x-label" x="<?= h((string) $trend_x_positions[$trend_index]) ?>" y="<?= h((string) ($trend_svg_height - 16)) ?>" text-anchor="middle"><?= h(['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'][$trend_index]) ?></text>
        <?php endforeach; ?>
      </svg>
    </div>
  </section>

  <section class="technician-report-panel technician-report-table-card" aria-labelledby="technician-report-latest-title">
    <div class="technician-report-panel-head">
      <div>
        <h2 id="technician-report-latest-title">งานที่เสร็จล่าสุด</h2>
        <p>รายการงานติดตั้งที่ปิดงานเรียบร้อยแล้ว</p>
      </div>
      <span class="technician-report-panel-total"><?= h(number_format(count($latest_completed_rows))) ?> รายการ</span>
    </div>

    <div class="technician-report-table-wrap">
      <table class="technician-report-table">
        <thead>
          <tr>
            <th>รหัสงาน</th>
            <th>ลูกค้า</th>
            <th>สินค้า</th>
            <th>วันที่ติดตั้ง</th>
            <th>เวลา</th>
            <th>วันที่เสร็จ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$latest_completed_rows): ?>
            <tr>
              <td colspan="6">
                <div class="technician-report-empty-state">
                  <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                  <span>ยังไม่มีงานที่เสร็จสิ้น</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($latest_completed_rows as $row): ?>
              <tr>
                <td><strong class="technician-report-job-id"><?= h($row['setup_id'] ?? '--') ?></strong></td>
                <td><?= h(trim((string) ($row['customer_name'] ?? '')) ?: '--') ?></td>
                <td><?= h(technician_report_product_text($row)) ?></td>
                <td><?= h(technician_report_format_date($row['assign_install_date'] ?? null, $report_timezone)) ?></td>
                <td><?= h(technician_report_format_time_range($row['assign_install_time'] ?? null, $row['assign_install_end_time'] ?? null, $report_timezone)) ?></td>
                <td><?= h(technician_report_format_date($row['completed_at'] ?? null, $report_timezone)) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script
  src="<?= h(app_asset_url('technician/assets/js/report.js')) ?>?v=<?= h(asset_version('technician/assets/js/report.js')) ?>"
  defer
></script>
<?php layout_footer(); ?>
