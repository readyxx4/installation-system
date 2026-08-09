<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function safe_count(mysqli $conn, string $sql): int
{
    try {
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_money(mysqli $conn, string $sql): float
{
    try {
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        return (float) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function icon_svg(string $name): string
{
    $icons = [
        'search' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>',
        'calendar' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
        'plus' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
        'users' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'box' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
        'check' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>',
        'list' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
        'report' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5"></path><path d="M4 19h16"></path><rect x="7" y="11" width="3" height="5" rx="1"></rect><rect x="12" y="8" width="3" height="8" rx="1"></rect><rect x="17" y="4" width="3" height="12" rx="1"></rect></svg>',
        'card' => '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18"></path><path d="M7 15h4"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

$total_setups = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup
");

$total_wait_payment = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product_payment
    WHERE paymentpro_status = 0
");

$total_paid = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product_payment
    WHERE paymentpro_status = 1
");

$total_cancel = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product_payment
    WHERE paymentpro_status = 2
");

$total_customers = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM `user`
    WHERE user_role = 0
");

$total_products = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product
");

$total_install_price = safe_money($conn, "
    SELECT COALESCE(SUM(install_total), 0) AS total
    FROM install_detail
");

$recent_setups = $conn->query("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_status,
        u.user_name,
        u.user_phone,
        p.pro_name,
        d.install_qty,
        d.install_total
    FROM setup s
    LEFT JOIN `user` u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN install_detail d ON s.setup_id = d.setup_id
    ORDER BY s.created_at DESC, s.setup_id DESC
    LIMIT 5
");

$recent_payments = $conn->query("
    SELECT
        pp.paymentpro_id,
        pp.paymentpro_date,
        pp.paymentpro_status,
        s.setup_id,
        u.user_name,
        p.pro_name
    FROM product_payment pp
    LEFT JOIN setup s ON pp.setup_id = s.setup_id
    LEFT JOIN `user` u ON pp.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    ORDER BY pp.paymentpro_date DESC, pp.paymentpro_id DESC
    LIMIT 5
");

$recent_setup_rows = [];
$setup_status_counts = [
    '0' => 0,
    '1' => 0,
    '2' => 0,
    '3' => 0,
    '4' => 0,
];

if ($recent_setups) {
    while ($row = $recent_setups->fetch_assoc()) {
        $recent_setup_rows[] = $row;
        $status_key = (string) ($row['setup_status'] ?? '');

        if (array_key_exists($status_key, $setup_status_counts)) {
            $setup_status_counts[$status_key]++;
        }
    }
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอมอบหมายงาน',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'ติดตั้งเสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function setup_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'green',
        '3' => 'blue',
        '4' => 'green',
        default => 'red',
    };
}

function payment_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอจ่ายสินค้า',
        '1' => 'จ่ายสินค้าแล้ว',
        '2' => 'ยกเลิก',
        default => 'ไม่ทราบสถานะ',
    };
}

function payment_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'green',
        '2' => 'red',
        default => 'blue',
    };
}

layout_header('หน้าหลักพนักงานขาย', 'dashboard');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('sale/assets/css/dashboard.css')) ?>"
>

<div class="admin-dashboard-v2 sales-dashboard-page">

  <div class="admin-dashboard-top">
    <div>
      <h1>ภาพรวมพนักงานขาย</h1>
      <p>สรุปข้อมูลงานติดตั้ง การจ่ายสินค้า และรายการล่าสุดที่ต้องติดตาม</p>
    </div>

    <div class="admin-dashboard-actions">
      <div class="admin-date-pill">
        <i class="fa-regular fa-calendar"></i>
        <?= h(date('d/m/Y')) ?>
      </div>
    </div>
  </div>

  <div class="admin-summary-grid sales-summary-grid">
    <div class="admin-summary-card">
      <div>
        <span>งานติดตั้งทั้งหมด</span>
        <strong><?= h((string) $total_setups) ?></strong>
        <small>รายการงานติดตั้งในระบบ</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-file-circle-plus"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>ลูกค้า</span>
        <strong><?= h((string) $total_customers) ?></strong>
        <small>ข้อมูลลูกค้าที่ใช้บริการ</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-user-group"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>สินค้า</span>
        <strong><?= h((string) $total_products) ?></strong>
        <small>รายการสินค้าและค่าติดตั้ง</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-box"></i>
      </div>
    </div>
  </div>

  <div class="admin-dashboard-info-grid sales-dashboard-info-grid">
    <div class="admin-widget sales-install-status-widget">
      <div class="admin-widget-head">
        <div>
          <h2>สถานะงานติดตั้ง</h2>
          <p>สรุปสถานะงานติดตั้งล่าสุด</p>
        </div>
      </div>

      <div class="admin-mini-list sales-status-list">
        <?php foreach ($setup_status_counts as $status_value => $status_total): ?>
          <div class="admin-mini-item">
            <div class="admin-mini-main">
              <strong><?= h(setup_status_name($status_value)) ?></strong>
              <span><?= h((string) $status_total) ?> รายการ</span>
            </div>

            <span class="badge <?= h(setup_status_badge($status_value)) ?>">
              <?= h(setup_status_name($status_value)) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="admin-bottom-grid sales-bottom-grid">
    <div class="admin-widget">
      <div class="admin-widget-head">
        <div>
          <h2>งานติดตั้งล่าสุด</h2>
          <p>รายการงานติดตั้งที่สร้างล่าสุด</p>
        </div>

        <a class="admin-widget-link" href="<?= h(app_system_url('sale/setups.php')) ?>">
          ดูทั้งหมด
        </a>
      </div>

      <div class="admin-mini-list sales-mini-list">
        <?php if (count($recent_setup_rows) === 0): ?>
          <div class="admin-empty-mini">ยังไม่มีงานติดตั้ง</div>
        <?php endif; ?>

        <?php foreach ($recent_setup_rows as $row): ?>
          <div class="admin-mini-item">
            <div class="admin-mini-main">
              <strong><?= h($row['setup_id']) ?> - <?= h($row['user_name'] ?? '-') ?></strong>
              <span>
                <?= h($row['pro_name'] ?? '-') ?>
                |
                <?= !empty($row['setup_date']) ? h(date('d/m/Y', strtotime($row['setup_date']))) : '-' ?>
              </span>
            </div>

            <span class="badge <?= h(setup_status_badge($row['setup_status'])) ?>">
              <?= h(setup_status_name($row['setup_status'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php
layout_footer();
