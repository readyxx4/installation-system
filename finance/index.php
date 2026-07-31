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

<div class="sales-dashboard-page">

  <div class="admin-dashboard-head">
    <div>
      <h1>ภาพรวมพนักงานขาย</h1>
      <p>สรุปข้อมูลงานติดตั้ง การจ่ายสินค้า และเมนูการทำงานหลักของพนักงานขาย</p>
    </div>

    <div class="admin-dashboard-tools">
      <div class="admin-search-box">
        <?= icon_svg('search') ?>
        <input type="text" placeholder="ค้นหาข้อมูลงานติดตั้ง..." readonly>
      </div>

      <div class="admin-date-box">
        <?= icon_svg('calendar') ?>
        <?= h(date('d/m/Y')) ?>
      </div>
    </div>
  </div>

  <div class="admin-summary-grid sales-summary-grid">
    <div class="admin-summary-card green">
      <div>
        <p>งานติดตั้งทั้งหมด</p>
        <h2><?= h((string) $total_setups) ?></h2>
        <span>รายการงานติดตั้งในระบบ</span>
      </div>
      <div class="summary-icon"><?= icon_svg('plus') ?></div>
    </div>

    <div class="admin-summary-card blue">
      <div>
        <p>ลูกค้า</p>
        <h2><?= h((string) $total_customers) ?></h2>
        <span>ข้อมูลลูกค้าที่ใช้บริการ</span>
      </div>
      <div class="summary-icon"><?= icon_svg('users') ?></div>
    </div>

    <div class="admin-summary-card orange">
      <div>
        <p>รอจ่ายสินค้า</p>
        <h2><?= h((string) $total_wait_payment) ?></h2>
        <span>รายการที่ยังไม่จ่ายสินค้า</span>
      </div>
      <div class="summary-icon"><?= icon_svg('box') ?></div>
    </div>

    <div class="admin-summary-card cyan">
      <div>
        <p>จ่ายสินค้าแล้ว</p>
        <h2><?= h((string) $total_paid) ?></h2>
        <span>รายการที่จ่ายสินค้าเรียบร้อย</span>
      </div>
      <div class="summary-icon"><?= icon_svg('check') ?></div>
    </div>

    <div class="admin-summary-card purple">
      <div>
        <p>สินค้า</p>
        <h2><?= h((string) $total_products) ?></h2>
        <span>รายการสินค้าและค่าติดตั้ง</span>
      </div>
      <div class="summary-icon"><?= icon_svg('box') ?></div>
    </div>
  </div>

  <div class="sales-dashboard-grid">
    <div class="dashboard-panel sales-menu-panel">
      <div class="dashboard-panel-head">
        <div>
          <h2>เมนูสำหรับพนักงานขาย</h2>
          <p>เมนูสำหรับสร้างงานติดตั้ง บันทึกการจ่ายสินค้า และดูรายงาน</p>
        </div>

        <span class="dashboard-pill">
          ยอดรวม <?= number_format($total_install_price, 2) ?> บาท
        </span>
      </div>

      <div class="admin-menu-grid sales-menu-grid">
          <a class="admin-menu-card" href="<?= h(app_system_url('finance/setups.php')) ?>">
          <div class="menu-icon-box">
            <?= icon_svg('list') ?>
          </div>
          <h3>รายการงานติดตั้ง</h3>
          <p>ตรวจสอบรายการงานติดตั้งและสถานะงานทั้งหมด</p>
        </a>

        <a class="admin-menu-card" href="<?= h(app_system_url('finance/create_setup.php')) ?>">
          <div class="menu-icon-box">
            <?= icon_svg('plus') ?>
          </div>
          <h3>สร้างงานติดตั้ง</h3>
          <p>สร้างใบติดตั้งใหม่ให้ลูกค้า เลือกลูกค้าและสินค้า</p>
        </a>

      
        <a class="admin-menu-card" href="<?= h(app_system_url('finance/payment.php')) ?>">
          <div class="menu-icon-box">
            <?= icon_svg('box') ?>
          </div>
          <h3>บันทึกการจ่ายสินค้า</h3>
          <p>บันทึกสถานะการจ่ายสินค้าให้กับงานติดตั้ง</p>
        </a>

        <a class="admin-menu-card" href="<?= h(app_system_url('finance/report.php')) ?>">
          <div class="menu-icon-box">
            <?= icon_svg('report') ?>
          </div>
          <h3>รายงาน</h3>
          <p>ดูรายงานงานติดตั้ง การจ่ายสินค้า และยอดรวม</p>
        </a>
      </div>
    </div>

    <div class="dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <h2>สถานะการจ่ายสินค้า</h2>
          <p>สรุปสถานะการจ่ายสินค้าล่าสุด</p>
        </div>
      </div>

      <div class="sales-payment-status">
        <div class="payment-status-row wait">
          <div>
            <strong>รอจ่ายสินค้า</strong>
            <span><?= h((string) $total_wait_payment) ?> รายการ</span>
          </div>
          <b>รอ</b>
        </div>

        <div class="payment-status-row paid">
          <div>
            <strong>จ่ายสินค้าแล้ว</strong>
            <span><?= h((string) $total_paid) ?> รายการ</span>
          </div>
          <b>สำเร็จ</b>
        </div>

        <div class="payment-status-row cancel">
          <div>
            <strong>ยกเลิก</strong>
            <span><?= h((string) $total_cancel) ?> รายการ</span>
          </div>
          <b>ยกเลิก</b>
        </div>
      </div>
    </div>
  </div>

  <div class="sales-dashboard-grid lower">
    <div class="dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <h2>งานติดตั้งล่าสุด</h2>
          <p>รายการงานติดตั้งที่สร้างล่าสุด</p>
        </div>

        <a class="dashboard-link" href="<?= h(app_system_url('finance/setups.php')) ?>">
          ดูทั้งหมด
        </a>
      </div>

      <div class="simple-list">
        <?php if ($recent_setups->num_rows === 0): ?>
          <div class="empty-dashboard">ยังไม่มีงานติดตั้ง</div>
        <?php endif; ?>

        <?php while ($row = $recent_setups->fetch_assoc()): ?>
          <div class="simple-list-item">
            <div>
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
        <?php endwhile; ?>
      </div>
    </div>

    <div class="dashboard-panel">
      <div class="dashboard-panel-head">
        <div>
          <h2>การจ่ายสินค้าล่าสุด</h2>
          <p>รายการบันทึกการจ่ายสินค้าล่าสุด</p>
        </div>

        <a class="dashboard-link" href="<?= h(app_system_url('finance/payment.php')) ?>">
          ดูทั้งหมด
        </a>
      </div>

      <div class="simple-list">
        <?php if ($recent_payments->num_rows === 0): ?>
          <div class="empty-dashboard">ยังไม่มีข้อมูลการจ่ายสินค้า</div>
        <?php endif; ?>

        <?php while ($row = $recent_payments->fetch_assoc()): ?>
          <div class="simple-list-item">
            <div>
              <strong><?= h($row['paymentpro_id']) ?> - <?= h($row['user_name'] ?? '-') ?></strong>
              <span>
                <?= h($row['setup_id'] ?? '-') ?>
                |
                <?= !empty($row['paymentpro_date']) ? h(date('d/m/Y H:i', strtotime($row['paymentpro_date']))) : '-' ?>
              </span>
            </div>

            <span class="badge <?= h(payment_status_badge($row['paymentpro_status'])) ?>">
              <?= h(payment_status_name($row['paymentpro_status'])) ?>
            </span>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>

</div>

<?php
layout_footer();
