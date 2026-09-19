<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

$current_sale_id = trim((string) ($_SESSION['user_id'] ?? ''));

if ($current_sale_id === '') {
    redirect_to(app_system_url('login.php'));
}

function sale_report_prepare(mysqli $conn, string $sql, string $types = '', array $params = []): ?mysqli_stmt
{
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt || strlen($types) !== count($params)) {
            return null;
        }

        if ($types !== '') {
            $bind = [$types];
            foreach ($params as $index => $param) {
                $bind[] = &$params[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $stmt->execute();

        return $stmt;
    } catch (Throwable $e) {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        return null;
    }
}

function sale_report_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = sale_report_prepare($conn, $sql, $types, $params);
    if (!$stmt) {
        return [];
    }

    try {
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->free();
        $stmt->close();

        return $rows;
    } catch (Throwable $e) {
        $stmt->close();
        return [];
    }
}

function sale_report_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): float
{
    $stmt = sale_report_prepare($conn, $sql, $types, $params);
    if (!$stmt) {
        return 0.0;
    }

    try {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return (float) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        $stmt->close();
        return 0.0;
    }
}

function sale_report_valid_date(string $value): string
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

function sale_report_valid_week(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-W\d{2}$/', $value)) {
        return '';
    }

    $week = DateTimeImmutable::createFromFormat('!o-\\WW', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$week || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $week->format('o-\\WW') === $value ? $value : '';
}

function sale_report_valid_month(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        return '';
    }

    $month = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');
    $errors = DateTimeImmutable::getLastErrors();
    if (!$month || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $month->format('Y-m') === $value ? $value : '';
}

function sale_report_period_value(string $period, string $value, DateTimeImmutable $now): string
{
    return match ($period) {
        'week' => sale_report_valid_week($value) ?: $now->format('o-\\WW'),
        'month' => sale_report_valid_month($value) ?: $now->format('Y-m'),
        default => sale_report_valid_date($value) ?: $now->format('Y-m-d'),
    };
}

function sale_report_event_datetime($value, DateTimeZone $timezone): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    try {
        return new DateTimeImmutable($value, $timezone);
    } catch (Throwable $e) {
        return null;
    }
}

function sale_report_week_start(string $value, DateTimeZone $timezone): DateTimeImmutable
{
    $week = DateTimeImmutable::createFromFormat('!o-\\WW', $value, $timezone);
    if (!$week) {
        $week = new DateTimeImmutable('monday this week', $timezone);
    }

    return $week->setTime(0, 0, 0);
}

function sale_report_build_chart(
    array $rows,
    string $period,
    string $period_value,
    string $field,
    DateTimeZone $timezone
): array {
    $labels = [];
    $values = [];

    if ($period === 'week') {
        $labels = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
        $values = array_fill(0, 7, 0);
        $start = sale_report_week_start($period_value, $timezone);

        foreach ($rows as $row) {
            $event = sale_report_event_datetime($row[$field] ?? null, $timezone);
            if (!$event) {
                continue;
            }

            $offset = (int) $start->diff($event->setTime(0, 0, 0))->format('%r%a');
            if ($offset >= 0 && $offset < 7) {
                $values[$offset]++;
            }
        }

        $period_label = 'สัปดาห์เริ่ม ' . $start->format('d/m/Y');
    } elseif ($period === 'month') {
        $labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $values = array_fill(0, 12, 0);
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', $period_value . '-01', $timezone);
        $year = $month ? $month->format('Y') : date('Y');

        foreach ($rows as $row) {
            $event = sale_report_event_datetime($row[$field] ?? null, $timezone);
            if ($event && $event->format('Y') === $year) {
                $values[(int) $event->format('n') - 1]++;
            }
        }

        $period_label = 'ปี ' . $year;
    } else {
        $labels = ['เช้า', 'บ่าย', 'เย็น'];
        $values = array_fill(0, 3, 0);
        $selected_day = sale_report_valid_date($period_value);

        foreach ($rows as $row) {
            $event = sale_report_event_datetime($row[$field] ?? null, $timezone);
            if (!$event || $event->format('Y-m-d') !== $selected_day) {
                continue;
            }

            $hour = (int) $event->format('G');
            $slot = $hour < 12 ? 0 : ($hour < 17 ? 1 : 2);
            $values[$slot]++;
        }

        $period_label = 'วันที่ ' . date('d/m/Y', strtotime($selected_day));
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'total' => array_sum($values),
        'period_label' => $period_label,
    ];
}

function sale_report_chart_svg(array $chart, string $aria_label, string $line_color): string
{
    $width = 760;
    $height = 248;
    $plot_left = 42;
    $plot_right = 16;
    $plot_top = 18;
    $plot_bottom = 42;
    $plot_width = $width - $plot_left - $plot_right;
    $plot_height = $height - $plot_top - $plot_bottom;
    $values = array_map(static fn ($value): int => max(0, (int) $value), $chart['values']);
    $max_value = max(1, ...$values);
    $count = max(1, count($values));
    $step_x = $count > 1 ? $plot_width / ($count - 1) : $plot_width / 2;
    $points = [];

    foreach ($values as $index => $value) {
        $x = $count > 1 ? $plot_left + ($step_x * $index) : $plot_left + ($plot_width / 2);
        $y = $plot_top + $plot_height - (($value / $max_value) * $plot_height);
        $points[] = [$x, $y];
    }

    ob_start();
    ?>
    <svg class="sale-report-line-chart" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="<?= h($aria_label) ?>">
      <?php for ($grid = 0; $grid <= 4; $grid++): ?>
        <?php
        $grid_y = $plot_top + (($plot_height / 4) * $grid);
        $grid_value = (int) round($max_value - (($max_value / 4) * $grid));
        ?>
        <line class="sale-report-chart-grid" x1="<?= $plot_left ?>" y1="<?= round($grid_y, 2) ?>" x2="<?= $width - $plot_right ?>" y2="<?= round($grid_y, 2) ?>"></line>
        <text class="sale-report-chart-y-label" x="<?= $plot_left - 9 ?>" y="<?= round($grid_y + 4, 2) ?>" text-anchor="end"><?= h((string) $grid_value) ?></text>
      <?php endfor; ?>

      <polyline class="sale-report-chart-line" points="<?php foreach ($points as $point): ?><?= round($point[0], 2) ?>,<?= round($point[1], 2) ?> <?php endforeach; ?>" style="stroke: <?= h($line_color) ?>"></polyline>

      <?php foreach ($points as $index => $point): ?>
        <circle class="sale-report-chart-point" cx="<?= round($point[0], 2) ?>" cy="<?= round($point[1], 2) ?>" r="4" style="stroke: <?= h($line_color) ?>">
          <title><?= h((string) ($chart['labels'][$index] ?? 'ช่วงเวลา')) ?>: <?= h((string) $values[$index]) ?> งาน</title>
        </circle>
        <text class="sale-report-chart-x-label" x="<?= round($point[0], 2) ?>" y="<?= $height - 14 ?>" text-anchor="middle"><?= h((string) ($chart['labels'][$index] ?? '')) ?></text>
      <?php endforeach; ?>
    </svg>
    <?php

    return (string) ob_get_clean();
}

function sale_report_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'สร้างใบงานแล้ว',
        '1', '2', '3' => 'กำลังดำเนินการ',
        '4' => 'เสร็จสิ้น',
        '5' => 'ยกเลิกแล้ว',
        default => 'ไม่ทราบสถานะ',
    };
}

function sale_report_status_tone($status): string
{
    return match ((string) $status) {
        '0' => 'amber',
        '1', '2', '3' => 'blue',
        '4' => 'green',
        '5' => 'red',
        default => 'slate',
    };
}

function sale_report_format_date($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

$report_timezone = new DateTimeZone('Asia/Bangkok');
$report_now = new DateTimeImmutable('now', $report_timezone);

$created_period_request = (string) ($_GET['created_period'] ?? $_GET['created_period_current'] ?? 'day');
$completed_period_request = (string) ($_GET['completed_period'] ?? $_GET['completed_period_current'] ?? 'day');
$created_period = in_array($created_period_request, ['day', 'week', 'month'], true)
    ? $created_period_request
    : 'day';
$completed_period = in_array($completed_period_request, ['day', 'week', 'month'], true)
    ? $completed_period_request
    : 'day';
$created_value = sale_report_period_value($created_period, (string) ($_GET['created_value'] ?? ''), $report_now);
$completed_value = sale_report_period_value($completed_period, (string) ($_GET['completed_value'] ?? ''), $report_now);

$status_counts = array_fill_keys(['0', '1', '2', '3', '4', '5'], 0);
$status_rows = sale_report_rows(
    $conn,
    'SELECT s.setup_status, COUNT(*) AS total FROM setup s WHERE s.sale_id = ? GROUP BY s.setup_status',
    's',
    [$current_sale_id]
);
foreach ($status_rows as $status_row) {
    $status_key = (string) ($status_row['setup_status'] ?? '');
    if (array_key_exists($status_key, $status_counts)) {
        $status_counts[$status_key] = (int) $status_row['total'];
    }
}

$total_setups = (int) sale_report_scalar(
    $conn,
    'SELECT COUNT(*) AS total FROM setup s WHERE s.sale_id = ?',
    's',
    [$current_sale_id]
);
$total_install_price = sale_report_scalar(
    $conn,
    'SELECT COALESCE(SUM(d.install_total), 0) AS total
     FROM setup s
     LEFT JOIN install_detail d ON d.setup_id = s.setup_id
     WHERE s.sale_id = ?',
    's',
    [$current_sale_id]
);

$created_event_rows = sale_report_rows(
    $conn,
    'SELECT s.created_at FROM setup s WHERE s.sale_id = ? AND s.created_at IS NOT NULL ORDER BY s.created_at ASC, s.setup_id ASC',
    's',
    [$current_sale_id]
);
$completed_event_rows = sale_report_rows(
    $conn,
    'SELECT s.completed_at FROM setup s WHERE s.sale_id = ? AND s.setup_status = 4 AND s.completed_at IS NOT NULL ORDER BY s.completed_at ASC, s.setup_id ASC',
    's',
    [$current_sale_id]
);

$created_chart = sale_report_build_chart($created_event_rows, $created_period, $created_value, 'created_at', $report_timezone);
$completed_chart = sale_report_build_chart($completed_event_rows, $completed_period, $completed_value, 'completed_at', $report_timezone);

$table_search = trim((string) ($_GET['q'] ?? ''));
$table_status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($table_status, ['', 'created', 'progress', 'done', 'cancelled'], true)) {
    $table_status = '';
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

$table_sql = "
    SELECT
        s.setup_id,
        s.created_at,
        s.setup_status,
        c.customer_name,
        a.assign_install_date,
        COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), a.tech_id, '-') AS technician_name,
        COALESCE(detail_summary.install_total, 0) AS install_total
    FROM setup s
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    {$latest_assignment_join}
    LEFT JOIN technicians t ON t.tech_id = a.tech_id
    LEFT JOIN (
        SELECT d.setup_id, SUM(COALESCE(d.install_total, 0)) AS install_total
        FROM install_detail d
        GROUP BY d.setup_id
    ) detail_summary ON detail_summary.setup_id = s.setup_id
    WHERE s.sale_id = ?
";
$table_types = 's';
$table_params = [$current_sale_id];

if ($table_search !== '') {
    $table_sql .= ' AND (s.setup_id LIKE ? OR c.customer_name LIKE ?)';
    $table_types .= 'ss';
    $search_like = '%' . $table_search . '%';
    $table_params[] = $search_like;
    $table_params[] = $search_like;
}

switch ($table_status) {
    case 'created':
        $table_sql .= ' AND s.setup_status = 0';
        break;
    case 'progress':
        $table_sql .= ' AND s.setup_status IN (1, 2, 3)';
        break;
    case 'done':
        $table_sql .= ' AND s.setup_status = 4';
        break;
    case 'cancelled':
        $table_sql .= ' AND s.setup_status = 5';
        break;
}

$table_sql .= ' ORDER BY s.created_at DESC, s.setup_id DESC LIMIT 10';
$recent_setups = sale_report_rows($conn, $table_sql, $table_types, $table_params);

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && in_array($ajax_section, ['created', 'completed', 'table'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    if ($ajax_section === 'table') {
        $rows = array_map(static function (array $row): array {
            return [
                'setup_id' => (string) ($row['setup_id'] ?? '-'),
                'customer_name' => trim((string) ($row['customer_name'] ?? '')) ?: '-',
                'created_display' => sale_report_format_date($row['created_at'] ?? null),
                'install_display' => sale_report_format_date($row['assign_install_date'] ?? null),
                'technician_name' => trim((string) ($row['technician_name'] ?? '')) ?: '-',
                'status_label' => sale_report_status_name($row['setup_status'] ?? null),
                'status_tone' => sale_report_status_tone($row['setup_status'] ?? null),
                'install_display_money' => number_format((float) ($row['install_total'] ?? 0), 2) . ' บาท',
            ];
        }, $recent_setups);
        echo json_encode(['success' => true, 'section' => 'table', 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $chart = $ajax_section === 'created' ? $created_chart : $completed_chart;
    echo json_encode([
        'success' => true,
        'section' => $ajax_section,
        'period' => $ajax_section === 'created' ? $created_period : $completed_period,
        'value' => $ajax_section === 'created' ? $created_value : $completed_value,
        'labels' => array_values($chart['labels']),
        'values' => array_values(array_map('intval', $chart['values'])),
        'total' => (int) $chart['total'],
        'period_label' => (string) $chart['period_label'],
        'chart_html' => sale_report_chart_svg($chart, 'แนวโน้มรายงาน', $ajax_section === 'created' ? '#2563eb' : '#16a34a'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$created_chart_empty = $created_chart['total'] === 0;
$completed_chart_empty = $completed_chart['total'] === 0;

layout_header('รายงานงานติดตั้ง', 'report', 'สรุปข้อมูลใบงานติดตั้งที่คุณสร้าง');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/report.css')) ?>?v=<?= h(asset_version('sale/assets/css/report.css')) ?>"
>

<div class="sale-report-page">
  <header class="sale-report-hero">
    <div class="sale-report-hero-copy">
      <h1>รายงานงานติดตั้ง</h1>
      <p>สรุปข้อมูลใบงานติดตั้งที่คุณสร้าง</p>
    </div>

    <div class="sale-report-hero-actions">
      <button class="sale-report-print-button no-print" type="button" onclick="window.print()">
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        <span>พิมพ์รายงาน</span>
      </button>
    </div>
  </header>

  <section class="sale-report-summary-grid" aria-label="สรุปใบงานติดตั้งของคุณ">
    <article class="sale-report-summary-card tone-blue">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
      <div>
        <strong><?= h(number_format($total_setups)) ?></strong>
        <span>ใบงานทั้งหมด</span>
      </div>
    </article>

    <article class="sale-report-summary-card tone-amber">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-file-circle-plus"></i></span>
      <div>
        <strong><?= h(number_format($status_counts['0'])) ?></strong>
        <span>สร้างใบงานแล้ว</span>
      </div>
    </article>

    <article class="sale-report-summary-card tone-blue">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-bars-progress"></i></span>
      <div>
        <strong><?= h(number_format($status_counts['1'] + $status_counts['2'] + $status_counts['3'])) ?></strong>
        <span>กำลังดำเนินการ</span>
      </div>
    </article>

    <article class="sale-report-summary-card tone-green">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      <div>
        <strong><?= h(number_format($status_counts['4'])) ?></strong>
        <span>เสร็จสิ้น</span>
      </div>
    </article>

    <article class="sale-report-summary-card tone-red">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-ban"></i></span>
      <div>
        <strong><?= h(number_format($status_counts['5'])) ?></strong>
        <span>ยกเลิกแล้ว</span>
      </div>
    </article>

    <article class="sale-report-summary-card tone-purple">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-baht-sign"></i></span>
      <div>
        <strong><?= h(number_format($total_install_price, 2)) ?></strong>
        <span>รวมค่าติดตั้ง</span>
      </div>
    </article>
  </section>

  <section class="sale-report-chart-grid" aria-label="แนวโน้มใบงานของคุณ">
    <article class="sale-report-panel sale-report-chart-panel" data-sale-report-chart="created">
      <div class="sale-report-panel-head">
        <div>
          <h2>แนวโน้มการสร้างใบงาน</h2>
          <p>จำนวนใบงานที่สร้างตามช่วงเวลา</p>
        </div>

        <form class="sale-report-period-form no-print" method="get" action="<?= h(app_system_url('sale/report.php')) ?>" data-sale-period-form data-sale-report-field="created">
          <input type="hidden" name="created_period_current" value="<?= h($created_period) ?>" data-sale-period-current>
          <input type="hidden" name="completed_period" value="<?= h($completed_period) ?>">
          <input type="hidden" name="completed_value" value="<?= h($completed_value) ?>">
          <input type="hidden" name="q" value="<?= h($table_search) ?>">
          <input type="hidden" name="status" value="<?= h($table_status) ?>">
          <div class="sale-report-period-tabs" role="group" aria-label="ช่วงเวลาแนวโน้มการสร้างใบงาน">
            <?php foreach (['day' => 'วัน', 'week' => 'สัปดาห์', 'month' => 'เดือน'] as $period_key => $period_label): ?>
              <button type="button" class="sale-report-period-tab<?= $created_period === $period_key ? ' is-active' : '' ?>" name="created_period" value="<?= h($period_key) ?>" data-sale-period-tab="<?= h($period_key) ?>" aria-pressed="<?= $created_period === $period_key ? 'true' : 'false' ?>"><?= h($period_label) ?></button>
            <?php endforeach; ?>
          </div>
          <input
            class="sale-report-period-input"
            name="created_value"
            type="<?= h($created_period === 'week' ? 'week' : ($created_period === 'month' ? 'month' : 'date')) ?>"
            value="<?= h($created_value) ?>"
            data-sale-period-input
            data-day="<?= h(sale_report_period_value('day', $created_period === 'day' ? $created_value : '', $report_now)) ?>"
            data-week="<?= h(sale_report_period_value('week', $created_period === 'week' ? $created_value : '', $report_now)) ?>"
            data-month="<?= h(sale_report_period_value('month', $created_period === 'month' ? $created_value : '', $report_now)) ?>"
            aria-label="เลือกช่วงเวลา"
          >
        </form>
      </div>

      <div class="sale-report-chart-summary" data-sale-chart-summary="created">
        <strong><?= h((string) $created_chart['total']) ?> งาน</strong>
        <span><?= h($created_chart['period_label']) ?></span>
      </div>

      <div class="sale-report-chart-wrap" data-sale-chart-wrap="created">
        <?= sale_report_chart_svg($created_chart, 'แนวโน้มจำนวนใบงานที่สร้าง', '#2563eb') ?>
      </div>

      <p class="sale-report-chart-empty" data-sale-chart-empty="created"<?= $created_chart_empty ? '' : ' hidden' ?>>ยังไม่มีใบงานที่สร้างในช่วงเวลานี้</p>
    </article>

    <article class="sale-report-panel sale-report-chart-panel" data-sale-report-chart="completed">
      <div class="sale-report-panel-head">
        <div>
          <h2>แนวโน้มงานเสร็จสิ้น</h2>
          <p>จำนวนงานที่ปิดเสร็จตามช่วงเวลา</p>
        </div>

        <form class="sale-report-period-form no-print" method="get" action="<?= h(app_system_url('sale/report.php')) ?>" data-sale-period-form data-sale-report-field="completed">
          <input type="hidden" name="completed_period_current" value="<?= h($completed_period) ?>" data-sale-period-current>
          <input type="hidden" name="created_period" value="<?= h($created_period) ?>">
          <input type="hidden" name="created_value" value="<?= h($created_value) ?>">
          <input type="hidden" name="q" value="<?= h($table_search) ?>">
          <input type="hidden" name="status" value="<?= h($table_status) ?>">
          <div class="sale-report-period-tabs" role="group" aria-label="ช่วงเวลางานเสร็จสิ้น">
            <?php foreach (['day' => 'วัน', 'week' => 'สัปดาห์', 'month' => 'เดือน'] as $period_key => $period_label): ?>
              <button type="button" class="sale-report-period-tab<?= $completed_period === $period_key ? ' is-active' : '' ?>" name="completed_period" value="<?= h($period_key) ?>" data-sale-period-tab="<?= h($period_key) ?>" aria-pressed="<?= $completed_period === $period_key ? 'true' : 'false' ?>"><?= h($period_label) ?></button>
            <?php endforeach; ?>
          </div>
          <input
            class="sale-report-period-input"
            name="completed_value"
            type="<?= h($completed_period === 'week' ? 'week' : ($completed_period === 'month' ? 'month' : 'date')) ?>"
            value="<?= h($completed_value) ?>"
            data-sale-period-input
            data-day="<?= h(sale_report_period_value('day', $completed_period === 'day' ? $completed_value : '', $report_now)) ?>"
            data-week="<?= h(sale_report_period_value('week', $completed_period === 'week' ? $completed_value : '', $report_now)) ?>"
            data-month="<?= h(sale_report_period_value('month', $completed_period === 'month' ? $completed_value : '', $report_now)) ?>"
            aria-label="เลือกช่วงเวลา"
          >
        </form>
      </div>

      <div class="sale-report-chart-summary" data-sale-chart-summary="completed">
        <strong><?= h((string) $completed_chart['total']) ?> งาน</strong>
        <span><?= h($completed_chart['period_label']) ?></span>
      </div>

      <div class="sale-report-chart-wrap" data-sale-chart-wrap="completed">
        <?= sale_report_chart_svg($completed_chart, 'แนวโน้มจำนวนงานที่เสร็จสิ้น', '#16a34a') ?>
      </div>

      <p class="sale-report-chart-empty" data-sale-chart-empty="completed"<?= $completed_chart_empty ? '' : ' hidden' ?>>ยังไม่มีงานที่ปิดเสร็จในช่วงเวลานี้</p>
    </article>
  </section>

  <section class="sale-report-panel sale-report-table-panel" aria-labelledby="sale-report-table-title">
    <div class="sale-report-panel-head sale-report-table-head">
      <div>
        <h2 id="sale-report-table-title">ใบงานติดตั้งล่าสุด</h2>
        <p>แสดงใบงานที่คุณสร้าง เรียงจากใหม่ไปเก่า</p>
      </div>
    </div>

    <form class="sale-report-table-filter no-print" method="get" action="<?= h(app_system_url('sale/report.php')) ?>" data-sale-report-table-form>
      <input type="hidden" name="created_period" value="<?= h($created_period) ?>">
      <input type="hidden" name="created_value" value="<?= h($created_value) ?>">
      <input type="hidden" name="completed_period" value="<?= h($completed_period) ?>">
      <input type="hidden" name="completed_value" value="<?= h($completed_value) ?>">
      <label class="sale-report-search-field">
        <span class="sr-only">ค้นหาใบงาน</span>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= h($table_search) ?>" placeholder="ค้นหารหัสใบงานหรือชื่อลูกค้า">
      </label>

      <label>
        <span class="sr-only">กรองสถานะ</span>
        <select name="status">
          <option value="" <?= $table_status === '' ? 'selected' : '' ?>>สถานะทั้งหมด</option>
          <option value="created" <?= $table_status === 'created' ? 'selected' : '' ?>>สร้างใบงานแล้ว</option>
          <option value="progress" <?= $table_status === 'progress' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
          <option value="done" <?= $table_status === 'done' ? 'selected' : '' ?>>เสร็จสิ้น</option>
          <option value="cancelled" <?= $table_status === 'cancelled' ? 'selected' : '' ?>>ยกเลิกแล้ว</option>
        </select>
      </label>

      <button class="sale-report-filter-button" type="submit">กรอง</button>
      <a class="sale-report-clear-filter" href="<?= h(app_system_url('sale/report.php')) ?>" data-sale-clear-filter<?= $table_search !== '' || $table_status !== '' ? '' : ' hidden' ?>>ล้างตัวกรอง</a>
    </form>

    <div class="table-wrap sale-report-table-wrap">
      <table class="data-table sale-report-table">
        <thead>
          <tr>
            <th>รหัสใบงาน</th>
            <th>ลูกค้า</th>
            <th>วันที่สร้าง</th>
            <th>วันที่ติดตั้ง</th>
            <th>ช่าง</th>
            <th>สถานะ</th>
            <th>ค่าติดตั้ง</th>
          </tr>
        </thead>
        <tbody data-sale-report-table-body>
          <?php if ($recent_setups === []): ?>
            <tr>
              <td colspan="7" class="empty-state">ยังไม่มีใบงานติดตั้ง</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($recent_setups as $row): ?>
            <tr>
              <td class="sale-report-setup-id"><?= h((string) $row['setup_id']) ?></td>
              <td><?= h((string) ($row['customer_name'] ?? '-')) ?></td>
              <td><?= h(sale_report_format_date($row['created_at'] ?? null)) ?></td>
              <td><?= h(sale_report_format_date($row['assign_install_date'] ?? null)) ?></td>
              <td><?= h((string) ($row['technician_name'] ?? '-')) ?></td>
              <td>
                <span class="sale-report-status-badge tone-<?= h(sale_report_status_tone($row['setup_status'] ?? null)) ?>">
                  <?= h(sale_report_status_name($row['setup_status'] ?? null)) ?>
                </span>
              </td>
              <td class="sale-report-money"><?= h(number_format((float) ($row['install_total'] ?? 0), 2)) ?> บาท</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script
  src="<?= h(app_asset_url('sale/assets/js/report.js')) ?>?v=<?= h(asset_version('sale/assets/js/report.js')) ?>"
></script>

<?php
layout_footer();
