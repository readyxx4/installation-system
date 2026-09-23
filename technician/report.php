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

function technician_report_valid_date(string $value): string
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

function technician_report_contains(string $value, string $query): bool
{
    return $query === '' || mb_stripos($value, $query, 0, 'UTF-8') !== false;
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

function technician_report_status_label(array $row, array $labels): string
{
    $status_key = (string) ($row['status_key'] ?? '');
    return $labels[$status_key] ?? 'สถานะไม่ระบุ';
}

function technician_report_status_class(array $row): string
{
    $classes = [
        'assigned' => 'is-assigned',
        'awaiting_receive' => 'is-awaiting-receive',
        'ready' => 'is-ready',
        'installing' => 'is-installing',
        'review' => 'is-review',
        'done' => 'is-done',
        'rejected' => 'is-rejected',
        'cancelled' => 'is-cancelled',
    ];

    return $classes[(string) ($row['status_key'] ?? '')] ?? 'is-assigned';
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

$technician_status_labels = [
    '' => 'สถานะทั้งหมด',
    'assigned' => 'มอบหมายแล้ว',
    'awaiting_receive' => 'ช่างกำลังไปรับสินค้า',
    'ready' => 'ยืนยันรับสินค้าแล้ว',
    'installing' => 'กำลังติดตั้ง',
    'review' => 'รอหัวหน้าช่างยืนยัน',
    'done' => 'งานเสร็จสิ้นแล้ว',
    'rejected' => 'ช่างปฏิเสธงาน',
    'cancelled' => 'ยกเลิกแล้ว',
];

$table_search = trim((string) ($_GET['q'] ?? ''));
$table_status = trim((string) ($_GET['status'] ?? ''));
if (!array_key_exists($table_status, $technician_status_labels)) {
    $table_status = '';
}

$table_period = in_array((string) ($_GET['table_period'] ?? ''), ['all', 'today', 'month', 'year', 'custom'], true)
    ? (string) $_GET['table_period']
    : 'all';
$table_date_from = technician_report_valid_date((string) ($_GET['table_date_from'] ?? ''));
$table_date_to = technician_report_valid_date((string) ($_GET['table_date_to'] ?? ''));
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

$technician_report_matches_period = static function ($value, ?DateTimeImmutable $period_start, ?DateTimeImmutable $period_end) use ($report_timezone): bool {
    if (!$period_start && !$period_end) {
        return true;
    }

    $date = technician_report_timestamp($value, $report_timezone);
    return $date instanceof DateTimeImmutable
        && (!$period_start || $date >= $period_start)
        && (!$period_end || $date < $period_end);
};

$technician_report_matches_search_and_period = static function (array $row) use ($table_search, $table_period_start, $table_period_end, $technician_report_matches_period): bool {
    $search_matches = technician_report_contains((string) ($row['setup_id'] ?? ''), $table_search)
        || technician_report_contains((string) ($row['customer_name'] ?? ''), $table_search);
    $time_matches = $technician_report_matches_period($row['assign_date'] ?? null, $table_period_start, $table_period_end);

    return $search_matches && $time_matches;
};

$filtered_rows = array_values(array_filter($report_rows, static function (array $row) use ($table_status, $technician_report_matches_search_and_period): bool {
    return $technician_report_matches_search_and_period($row)
        && ($table_status === '' || (string) ($row['status_key'] ?? '') === $table_status);
}));

$completed_rows = array_values(array_filter($filtered_rows, 'technician_report_is_completed'));
usort($completed_rows, static function (array $left, array $right): int {
    $left_time = strtotime((string) ($left['completed_at'] ?? '')) ?: 0;
    $right_time = strtotime((string) ($right['completed_at'] ?? '')) ?: 0;

    return ($right_time <=> $left_time)
        ?: strcmp((string) ($right['setup_id'] ?? ''), (string) ($left['setup_id'] ?? ''));
});

$current_month = $report_now->format('Y-m');
$summary_counts = [
    'assigned' => count($filtered_rows),
    'in_progress' => 0,
    'review' => 0,
    'completed' => count($completed_rows),
    'completed_month' => 0,
];

foreach ($filtered_rows as $row) {
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
    ['key' => 'assigned', 'label' => 'งานที่ได้รับทั้งหมด', 'value' => $summary_counts['assigned'], 'tone' => 'blue', 'icon' => 'fa-briefcase'],
    ['key' => 'in_progress', 'label' => 'กำลังดำเนินการ', 'value' => $summary_counts['in_progress'], 'tone' => 'cyan', 'icon' => 'fa-bars-progress'],
    ['key' => 'review', 'label' => 'รอหัวหน้าช่างยืนยัน', 'value' => $summary_counts['review'], 'tone' => 'amber', 'icon' => 'fa-user-check'],
    ['key' => 'completed', 'label' => 'เสร็จสิ้นทั้งหมด', 'value' => $summary_counts['completed'], 'tone' => 'green', 'icon' => 'fa-circle-check'],
    ['key' => 'completed_month', 'label' => 'เสร็จเดือนนี้', 'value' => $summary_counts['completed_month'], 'tone' => 'violet', 'icon' => 'fa-calendar-check'],
];

$all_rows = $filtered_rows;
$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && $ajax_section === 'global') {
    header('Content-Type: application/json; charset=utf-8');

    $format_row = static function (array $row) use ($report_timezone, $technician_status_labels): array {
        return [
            'setup_id' => (string) ($row['setup_id'] ?? '--'),
            'customer_name' => trim((string) ($row['customer_name'] ?? '')) ?: '--',
            'product_text' => technician_report_product_text($row),
            'install_date' => technician_report_format_date($row['assign_install_date'] ?? null, $report_timezone),
            'install_time' => technician_report_format_time_range($row['assign_install_time'] ?? null, $row['assign_install_end_time'] ?? null, $report_timezone),
            'status_label' => technician_report_status_label($row, $technician_status_labels),
            'status_class' => technician_report_status_class($row),
        ];
    };
    $all_payload_rows = array_map($format_row, $all_rows);
    echo json_encode([
        'success' => true,
        'section' => 'global',
        'filters' => [
            'q' => $table_search,
            'status' => $table_status,
            'table_period' => $table_period,
            'table_date_from' => $table_date_from,
            'table_date_to' => $table_date_to,
        ],
        'metrics' => $summary_counts,
        'all_rows' => $all_payload_rows,
        'all_total' => count($all_payload_rows),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$technician_report_company = system_company_data($conn);
$technician_report_print_date = (new DateTimeImmutable('now', $report_timezone))->format('d/m/Y');

layout_header('รายงานของฉัน', 'technician_report', 'สรุปงานติดตั้งและผลงานของคุณ');
?>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/report.css')) ?>?v=<?= h(asset_version('technician/assets/css/report.css')) ?>"
>

<main class="technician-report-page">
  <header class="report-print-document-header" aria-label="หัวเอกสารรายงาน">
    <div class="report-print-brand">
      <?php if ($technician_report_company['system_logo_url'] !== ''): ?>
        <img class="report-print-logo" src="<?= h($technician_report_company['system_logo_url']) ?>" alt="โลโก้บริษัท">
      <?php else: ?>
        <span class="report-print-logo-placeholder" aria-label="ไม่มีโลโก้บริษัท">-</span>
      <?php endif; ?>
      <div class="report-print-company-copy">
        <p class="report-print-company-name"><?= h($technician_report_company['system_name']) ?></p>
        <p class="report-print-company-address"><?= h($technician_report_company['company_address']) ?></p>
        <p class="report-print-company-tax">เลขประจำตัวผู้เสียภาษี: <?= h($technician_report_company['tax_id']) ?></p>
      </div>
    </div>
    <div class="report-print-meta">
      <h2>รายงานของฉัน</h2>
      <p data-technician-report-print-date data-timezone="<?= h(date_default_timezone_get()) ?>">วันที่พิมพ์: <?= h($technician_report_print_date) ?></p>
    </div>
  </header>

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

  <section class="technician-report-filter" aria-label="ตัวกรองรายงานงานติดตั้ง">
    <form class="technician-report-filter-form" method="get" action="<?= h(app_system_url('technician/report.php')) ?>" data-technician-report-filter-form>
      <label class="technician-report-search-field">
        <span class="sr-only">ค้นหาใบงานหรือลูกค้า</span>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= h($table_search) ?>" placeholder="ค้นหาใบงาน/ลูกค้า" autocomplete="off">
      </label>
      <label class="technician-report-filter-field">
        <span class="sr-only">สถานะงาน</span>
        <select name="status">
          <?php foreach ($technician_status_labels as $status_key => $status_label): ?>
            <option value="<?= h($status_key) ?>" <?= $table_status === $status_key ? 'selected' : '' ?>><?= h($status_label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="technician-report-filter-field technician-report-period-field">
        <span class="sr-only">ช่วงเวลา</span>
        <select name="table_period" data-technician-table-period>
          <option value="all" <?= $table_period === 'all' ? 'selected' : '' ?>>ช่วงเวลาทั้งหมด</option>
          <option value="today" <?= $table_period === 'today' ? 'selected' : '' ?>>วันนี้</option>
          <option value="month" <?= $table_period === 'month' ? 'selected' : '' ?>>เดือนนี้</option>
          <option value="year" <?= $table_period === 'year' ? 'selected' : '' ?>>ปีนี้</option>
          <option value="custom" <?= $table_period === 'custom' ? 'selected' : '' ?>>กำหนดช่วงวันที่</option>
        </select>
      </label>
      <div class="technician-report-date-range" data-technician-table-date-range<?= $table_period === 'custom' ? '' : ' hidden' ?>>
        <label class="technician-report-date-field">
          <span>วันที่เริ่มต้น</span>
          <input type="date" name="table_date_from" value="<?= h($table_date_from) ?>" data-technician-table-date-from>
        </label>
        <label class="technician-report-date-field">
          <span>วันที่สิ้นสุด</span>
          <input type="date" name="table_date_to" value="<?= h($table_date_to) ?>" data-technician-table-date-to>
        </label>
      </div>
      <div class="technician-report-filter-actions">
        <button class="technician-report-filter-submit" type="submit">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <span>กรอง</span>
        </button>
        <button class="technician-report-filter-reset" type="button" data-technician-report-filter-reset>ล้างตัวกรอง</button>
      </div>
    </form>
  </section>

  <section class="technician-report-summary-grid" aria-label="สรุปผลงานของฉัน">
    <?php foreach ($summary_cards as $summary_card): ?>
      <article class="technician-report-summary-card tone-<?= h($summary_card['tone']) ?>" data-technician-summary-card="<?= h($summary_card['key']) ?>">
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

  <section class="technician-report-panel technician-report-table-card technician-report-all-jobs-card" aria-labelledby="technician-report-all-title">
    <div class="technician-report-panel-head">
      <div>
        <h2 id="technician-report-all-title">รายการงานทั้งหมด</h2>
        <p>รายการงานทั้งหมดที่คุณได้รับมอบหมาย</p>
      </div>
      <span class="technician-report-panel-total" data-technician-all-count><?= h(number_format(count($all_rows))) ?> รายการ</span>
    </div>

    <div class="technician-report-table-wrap">
      <table class="technician-report-table technician-report-job-table">
        <thead>
          <tr>
            <th>รหัสงาน</th>
            <th>ลูกค้า</th>
            <th>สินค้า</th>
            <th>วันที่ติดตั้ง</th>
            <th>เวลา</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody data-technician-all-body>
          <?php if (!$all_rows): ?>
            <tr>
              <td colspan="6">
                <div class="technician-report-empty-state technician-report-empty-state-compact">
                  <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                  <span>ไม่มีงานที่ได้รับมอบหมาย</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_rows as $row): ?>
              <tr>
                <td><strong class="technician-report-job-id"><?= h($row['setup_id'] ?? '--') ?></strong></td>
                <td><?= h(trim((string) ($row['customer_name'] ?? '')) ?: '--') ?></td>
                <td><?= h(technician_report_product_text($row)) ?></td>
                <td><?= h(technician_report_format_date($row['assign_install_date'] ?? null, $report_timezone)) ?></td>
                <td><?= h(technician_report_format_time_range($row['assign_install_time'] ?? null, $row['assign_install_end_time'] ?? null, $report_timezone)) ?></td>
                <td><span class="badge technician-report-status-badge <?= h(technician_report_status_class($row)) ?>"><?= h(technician_report_status_label($row, $technician_status_labels)) ?></span></td>
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
