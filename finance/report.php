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

function safe_sum(mysqli $conn, string $sql): float
{
    try {
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        return (float) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
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

function payment_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอการจ่ายสินค้า',
        '1' => 'จ่ายสินค้าแล้ว',
        '2' => 'ยกเลิกการจ่ายสินค้า',
        default => 'ไม่ทราบสถานะ',
    };
}

function badge_color($status): string
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

$total_customers = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM `user`
    WHERE user_role = 0
");

$total_setups = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup
");

$total_wait_assign = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE setup_status = 0
");

$total_success = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE setup_status = 4
");

$total_install_price = safe_sum($conn, "
    SELECT SUM(install_total) AS total
    FROM install_detail
");

$total_paid = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product_payment
    WHERE paymentpro_status = 1
");

$total_wait_payment = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM product_payment
    WHERE paymentpro_status = 0
");

$recent_setups = $conn->query("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_status,
        s.setup_address,
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
    LIMIT 10
");

$recent_payments = $conn->query("
    SELECT
        pp.paymentpro_id,
        pp.paymentpro_date,
        pp.paymentpro_status,
        s.setup_id,
        u.user_name,
        u.user_phone,
        p.pro_name
    FROM product_payment pp
    LEFT JOIN setup s ON pp.setup_id = s.setup_id
    LEFT JOIN `user` u ON pp.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    ORDER BY pp.paymentpro_date DESC, pp.paymentpro_id DESC
    LIMIT 10
");

layout_header('รายงาน', 'report');
page_head('รายงาน', 'หน้าหลัก > รายงาน');
?>

<div class="role-hero no-print">
  <div>
    <div class="hero-tag">Sales Report</div>
    <h2>รายงานพนักงานขาย</h2>
    <p>
      สรุปข้อมูลลูกค้า งานติดตั้ง ค่าติดตั้ง และสถานะการจ่ายสินค้า
      เพื่อใช้ตรวจสอบภาพรวมการดำเนินงาน
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">📊</div>
    <strong>รายงาน</strong>
    <span>สรุปข้อมูลงานติดตั้ง</span>
  </div>
</div>

<div class="stat-grid sales-report-stat-grid">
  <div class="stat-card blue">
    <h3><?= h((string) $total_customers) ?></h3>
    <p>ลูกค้าทั้งหมด</p>
  </div>

  <div class="stat-card cyan">
    <h3><?= h((string) $total_setups) ?></h3>
    <p>งานติดตั้งทั้งหมด</p>
  </div>

  <div class="stat-card orange">
    <h3><?= h((string) $total_wait_assign) ?></h3>
    <p>รอมอบหมายงาน</p>
  </div>

  <div class="stat-card green">
    <h3><?= h((string) $total_success) ?></h3>
    <p>ติดตั้งเสร็จสิ้น</p>
  </div>

  <div class="stat-card purple">
    <h3><?= number_format($total_install_price, 2) ?></h3>
    <p>รวมค่าติดตั้ง</p>
  </div>
</div>

<div class="stat-grid sales-report-stat-grid-small">
  <div class="stat-card green">
    <h3><?= h((string) $total_paid) ?></h3>
    <p>จ่ายสินค้าแล้ว</p>
  </div>

  <div class="stat-card orange">
    <h3><?= h((string) $total_wait_payment) ?></h3>
    <p>รอการจ่ายสินค้า</p>
  </div>
</div>

<div class="panel">
  <div class="panel-title-row">
    <div class="panel-title">รายงานงานติดตั้งล่าสุด</div>

    <button class="btn no-print" type="button" onclick="window.print()">
      พิมพ์รายงาน
    </button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสงานติดตั้ง</th>
          <th>วันที่ติดตั้ง</th>
          <th>ลูกค้า</th>
          <th>เบอร์โทร</th>
          <th>สินค้า</th>
          <th>จำนวน</th>
          <th>รวมค่าติดตั้ง</th>
          <th>สถานะ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($recent_setups->num_rows === 0): ?>
          <tr>
            <td colspan="8" class="empty-state">ยังไม่มีข้อมูลงานติดตั้ง</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $recent_setups->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['setup_id']) ?></td>

            <td>
              <?= !empty($row['setup_date'])
                ? h(date('d/m/Y', strtotime($row['setup_date'])))
                : '-'
              ?>
            </td>

            <td><?= h($row['user_name'] ?? '-') ?></td>
            <td><?= h($row['user_phone'] ?? '-') ?></td>
            <td><?= h($row['pro_name'] ?? '-') ?></td>
            <td><?= h((string) ($row['install_qty'] ?? '-')) ?></td>

            <td>
              <?= number_format((float) ($row['install_total'] ?? 0), 2) ?> บาท
            </td>

            <td>
              <span class="badge <?= h(badge_color($row['setup_status'])) ?>">
                <?= h(setup_status_name($row['setup_status'])) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-title">รายงานการจ่ายสินค้าล่าสุด</div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสการจ่ายสินค้า</th>
          <th>วันที่จ่ายสินค้า</th>
          <th>รหัสงานติดตั้ง</th>
          <th>ลูกค้า</th>
          <th>เบอร์โทร</th>
          <th>สินค้า</th>
          <th>สถานะ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($recent_payments->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="empty-state">ยังไม่มีข้อมูลการจ่ายสินค้า</td>
          </tr>
        <?php endif; ?>

        <?php while ($payment = $recent_payments->fetch_assoc()): ?>
          <tr>
            <td><?= h($payment['paymentpro_id']) ?></td>

            <td>
              <?= !empty($payment['paymentpro_date'])
                ? h(date('d/m/Y H:i', strtotime($payment['paymentpro_date'])))
                : '-'
              ?>
            </td>

            <td><?= h($payment['setup_id'] ?? '-') ?></td>
            <td><?= h($payment['user_name'] ?? '-') ?></td>
            <td><?= h($payment['user_phone'] ?? '-') ?></td>
            <td><?= h($payment['pro_name'] ?? '-') ?></td>

            <td>
              <span class="badge <?= h(badge_color($payment['paymentpro_status'])) ?>">
                <?= h(payment_status_name($payment['paymentpro_status'])) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
layout_footer();