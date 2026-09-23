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

$table_search = trim((string) ($_GET['q'] ?? ''));
$table_search = function_exists('mb_substr') ? mb_substr($table_search, 0, 120) : substr($table_search, 0, 120);
$table_status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($table_status, ['', 'created', 'progress', 'done', 'cancelled'], true)) {
    $table_status = '';
}
$created_period = trim((string) ($_GET['created_period'] ?? 'all'));
if (!in_array($created_period, ['all', 'today', 'month', 'year', 'custom'], true)) {
    $created_period = 'all';
}
$created_date_from = sale_report_valid_date((string) ($_GET['created_date_from'] ?? ''));
$created_date_to = sale_report_valid_date((string) ($_GET['created_date_to'] ?? ''));

$sale_filter_where = 'WHERE s.sale_id = ?';
$sale_filter_types = 's';
$sale_filter_params = [$current_sale_id];
if ($table_search !== '') {
    $sale_filter_where .= ' AND (s.setup_id LIKE ? OR c.customer_name LIKE ?)';
    $sale_filter_types .= 'ss';
    $sale_search_like = '%' . $table_search . '%';
    $sale_filter_params[] = $sale_search_like;
    $sale_filter_params[] = $sale_search_like;
}

switch ($table_status) {
    case 'created':
        $sale_filter_where .= ' AND s.setup_status = 0';
        break;
    case 'progress':
        $sale_filter_where .= ' AND s.setup_status IN (1, 2, 3)';
        break;
    case 'done':
        $sale_filter_where .= ' AND s.setup_status = 4';
        break;
    case 'cancelled':
        $sale_filter_where .= ' AND s.setup_status = 5';
        break;
}

$created_now = new DateTimeImmutable('now');
switch ($created_period) {
    case 'today':
        $today_start = $created_now->setTime(0, 0, 0);
        $sale_filter_where .= ' AND s.created_at >= ? AND s.created_at < ?';
        $sale_filter_types .= 'ss';
        $sale_filter_params[] = $today_start->format('Y-m-d H:i:s');
        $sale_filter_params[] = $today_start->modify('+1 day')->format('Y-m-d H:i:s');
        break;
    case 'month':
        $month_start = $created_now->modify('first day of this month')->setTime(0, 0, 0);
        $sale_filter_where .= ' AND s.created_at >= ? AND s.created_at < ?';
        $sale_filter_types .= 'ss';
        $sale_filter_params[] = $month_start->format('Y-m-d H:i:s');
        $sale_filter_params[] = $month_start->modify('+1 month')->format('Y-m-d H:i:s');
        break;
    case 'year':
        $year_start = $created_now->setDate((int) $created_now->format('Y'), 1, 1)->setTime(0, 0, 0);
        $sale_filter_where .= ' AND s.created_at >= ? AND s.created_at < ?';
        $sale_filter_types .= 'ss';
        $sale_filter_params[] = $year_start->format('Y-m-d H:i:s');
        $sale_filter_params[] = $year_start->modify('+1 year')->format('Y-m-d H:i:s');
        break;
    case 'custom':
        if ($created_date_from !== '') {
            $sale_filter_where .= ' AND s.created_at >= ?';
            $sale_filter_types .= 's';
            $sale_filter_params[] = $created_date_from . ' 00:00:00';
        }
        if ($created_date_to !== '') {
            $sale_filter_where .= ' AND s.created_at <= ?';
            $sale_filter_types .= 's';
            $sale_filter_params[] = $created_date_to . ' 23:59:59';
        }
        break;
}

$status_counts = array_fill_keys(['0', '1', '2', '3', '4', '5'], 0);
$status_rows = sale_report_rows(
    $conn,
    "SELECT s.setup_status, COUNT(*) AS total
     FROM setup s
     LEFT JOIN customers c ON c.customer_id = s.customer_id
     {$sale_filter_where}
     GROUP BY s.setup_status",
    $sale_filter_types,
    $sale_filter_params
);
foreach ($status_rows as $status_row) {
    $status_key = (string) ($status_row['setup_status'] ?? '');
    if (array_key_exists($status_key, $status_counts)) {
        $status_counts[$status_key] = (int) $status_row['total'];
    }
}

$total_setups = (int) sale_report_scalar(
    $conn,
    "SELECT COUNT(*) AS total
     FROM setup s
     LEFT JOIN customers c ON c.customer_id = s.customer_id
     {$sale_filter_where}",
    $sale_filter_types,
    $sale_filter_params
);
$total_install_price = sale_report_scalar(
    $conn,
    'SELECT COALESCE(SUM(d.install_total), 0) AS total
     FROM setup s
     LEFT JOIN customers c ON c.customer_id = s.customer_id
     LEFT JOIN install_detail d ON d.setup_id = s.setup_id
     ' . $sale_filter_where,
    $sale_filter_types,
    $sale_filter_params
);

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
    {$sale_filter_where}
";
$table_sql .= ' ORDER BY s.created_at DESC, s.setup_id DESC LIMIT 10';
$recent_setups = sale_report_rows($conn, $table_sql, $sale_filter_types, $sale_filter_params);

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && $ajax_section === 'table') {
    header('Content-Type: application/json; charset=utf-8');
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
    echo json_encode([
        'success' => true,
        'section' => 'table',
        'count' => $total_setups,
        'metrics' => [
            'total' => $total_setups,
            'created' => $status_counts['0'],
            'progress' => $status_counts['1'] + $status_counts['2'] + $status_counts['3'],
            'done' => $status_counts['4'],
            'cancelled' => $status_counts['5'],
            'install_total' => $total_install_price,
        ],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sale_report_system = system_company_data($conn);
$sale_report_system_logo_url = $sale_report_system['system_logo_url'];
$sale_report_system_name = $sale_report_system['system_name'];
$sale_report_company_address = $sale_report_system['company_address'];
$sale_report_company_tax_id = $sale_report_system['tax_id'];
$sale_report_print_date = (new DateTimeImmutable('now'))->format('d/m/Y');

layout_header('รายงานงานติดตั้ง', 'report', 'สรุปข้อมูลใบงานติดตั้งที่คุณสร้าง');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/report.css')) ?>?v=<?= h(asset_version('sale/assets/css/report.css')) ?>"
>
<style>
  .sale-report-page .sale-report-filter-bar {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }
</style>

<div class="sale-report-page">
  <header class="report-print-document-header" aria-label="หัวเอกสารรายงาน">
    <div class="report-print-brand">
      <?php if ($sale_report_system_logo_url !== ''): ?>
        <img class="report-print-logo" src="<?= h($sale_report_system_logo_url) ?>" alt="โลโก้บริษัท">
      <?php else: ?>
        <span class="report-print-logo-placeholder" aria-label="ไม่มีโลโก้บริษัท">-</span>
      <?php endif; ?>
      <div class="report-print-company-copy">
        <p class="report-print-company-name"><?= h($sale_report_system_name) ?></p>
        <p class="report-print-company-address"><?= h($sale_report_company_address) ?></p>
        <p class="report-print-company-tax">เลขประจำตัวผู้เสียภาษี: <?= h($sale_report_company_tax_id) ?></p>
      </div>
    </div>
    <div class="report-print-meta">
      <h2>รายงานงานติดตั้ง</h2>
      <p data-sale-report-print-date data-timezone="<?= h(date_default_timezone_get()) ?>">วันที่พิมพ์: <?= h($sale_report_print_date) ?></p>
    </div>
  </header>

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

  <form class="sale-report-table-filter sale-report-filter-bar no-print" method="get" action="<?= h(app_system_url('sale/report.php')) ?>" data-sale-report-table-form data-sale-report-filter-bar role="search" aria-label="ตัวกรองรายงานงานติดตั้ง">
    <label class="sale-report-search-field">
      <span class="sr-only">ค้นหาใบงาน</span>
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input type="search" name="q" value="<?= h($table_search) ?>" placeholder="ค้นหาใบงาน/ลูกค้า">
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

    <label class="sale-report-period-field">
      <span class="sr-only">ช่วงเวลาสร้างใบงาน</span>
      <select name="created_period" data-sale-created-period>
        <option value="all" <?= $created_period === 'all' ? 'selected' : '' ?>>ช่วงเวลาทั้งหมด</option>
        <option value="today" <?= $created_period === 'today' ? 'selected' : '' ?>>วันนี้</option>
        <option value="month" <?= $created_period === 'month' ? 'selected' : '' ?>>เดือนนี้</option>
        <option value="year" <?= $created_period === 'year' ? 'selected' : '' ?>>ปีนี้</option>
        <option value="custom" <?= $created_period === 'custom' ? 'selected' : '' ?>>กำหนดช่วงวันที่</option>
      </select>
    </label>

    <div class="sale-report-date-range" data-sale-date-range<?= $created_period === 'custom' ? '' : ' hidden' ?>>
      <label class="sale-report-date-field">
        <span>วันที่เริ่มต้น</span>
        <input type="date" name="created_date_from" value="<?= h($created_date_from) ?>" data-sale-created-date-from>
      </label>
      <label class="sale-report-date-field">
        <span>วันที่สิ้นสุด</span>
        <input type="date" name="created_date_to" value="<?= h($created_date_to) ?>" data-sale-created-date-to>
      </label>
    </div>

    <button class="sale-report-filter-button" type="submit">กรอง</button>
    <a class="sale-report-clear-filter" href="<?= h(app_system_url('sale/report.php')) ?>" data-sale-clear-filter<?= $table_search !== '' || $table_status !== '' || $created_period !== 'all' || $created_date_from !== '' || $created_date_to !== '' ? '' : ' hidden' ?>>ล้างตัวกรอง</a>
  </form>

  <section class="sale-report-summary-grid" data-sale-report-summary aria-label="สรุปใบงานติดตั้งของคุณ">
    <article class="sale-report-summary-card tone-blue">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
      <div>
        <span>สร้างใบงานทั้งหมด</span>
        <strong data-sale-report-metric="total"><?= h(number_format($total_setups)) ?></strong>
      </div>
    </article>

    <article class="sale-report-summary-card tone-blue">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-bars-progress"></i></span>
      <div>
        <span>กำลังดำเนินการ</span>
        <strong data-sale-report-metric="progress"><?= h(number_format($status_counts['1'] + $status_counts['2'] + $status_counts['3'])) ?></strong>
      </div>
    </article>

    <article class="sale-report-summary-card tone-green">
      <span class="sale-report-summary-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      <div>
        <span>เสร็จสิ้น</span>
        <strong data-sale-report-metric="done"><?= h(number_format($status_counts['4'])) ?></strong>
      </div>
    </article>

  </section>

  <section class="sale-report-panel sale-report-table-panel" aria-labelledby="sale-report-table-title">
    <div class="sale-report-panel-head sale-report-table-head">
      <div>
        <h2 id="sale-report-table-title">ใบงานติดตั้งล่าสุด</h2>
        <p>แสดงใบงานที่คุณสร้าง เรียงจากใหม่ไปเก่า</p>
      </div>
      <span class="sale-report-result-count" data-sale-report-result-count aria-live="polite">พบ <?= h(number_format($total_setups)) ?> รายการ</span>
    </div>

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
