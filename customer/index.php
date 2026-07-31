<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('0');

$user_id = $_SESSION['user_id'] ?? '';

if ($user_id === '') {
    redirect_to(app_public_url('login.html?error=login'));
}

function safe_count_customer(mysqli $conn, string $sql, string $user_id): int
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
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

$total_setup = safe_count_customer($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE user_id = ?
", $user_id);

$total_waiting = safe_count_customer($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE user_id = ?
      AND setup_status = 0
", $user_id);

$total_assigned = safe_count_customer($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE user_id = ?
      AND setup_status = 1
", $user_id);

$total_accepted = safe_count_customer($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE user_id = ?
      AND setup_status = 2
", $user_id);

$total_finished = safe_count_customer($conn, "
    SELECT COUNT(*) AS total
    FROM setup
    WHERE user_id = ?
      AND setup_status = 4
", $user_id);

$stmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_location,
        s.setup_address,
        s.setup_note,
        s.setup_status,
        s.created_at,

        p.pro_name,
        p.pro_price_install,

        pt.protype_name,

        d.install_qty,
        d.install_price,
        d.install_total,

        a.assign_id,
        a.assign_date,
        a.assign_status,

        t.tech_name,
        t.tech_phone
    FROM setup s
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
    LEFT JOIN install_detail d ON s.setup_id = d.setup_id
    LEFT JOIN assignment a ON s.setup_id = a.setup_id AND a.assign_status IN (1, 2, 5)
    LEFT JOIN technicians t ON a.tech_id = t.tech_id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC, s.setup_id DESC
");

$stmt->bind_param('s', $user_id);
$stmt->execute();
$setups = $stmt->get_result();

layout_header('หน้าหลักลูกค้า', 'dashboard');
?>

<div class="role-hero">
  <div>
    <div class="hero-tag">Customer</div>

    <h2>หน้าหลักลูกค้า</h2>

    <p>
      ลูกค้าสามารถตรวจสอบรายการงานติดตั้งของตนเอง
      ดูสถานะการมอบหมายงาน และข้อมูลช่างที่รับผิดชอบงานได้
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">👤</div>
    <strong>ลูกค้า</strong>
    <span>ติดตามงานติดตั้งของฉัน</span>
  </div>
</div>

<div class="stat-grid customer-stat-grid">
  <div class="stat-card blue">
    <h3><?= h((string) $total_setup) ?></h3>
    <p>งานติดตั้งทั้งหมด</p>
  </div>

  <div class="stat-card orange">
    <h3><?= h((string) $total_waiting) ?></h3>
    <p>รอมอบหมายงาน</p>
  </div>

  <div class="stat-card purple">
    <h3><?= h((string) $total_assigned) ?></h3>
    <p>มอบหมายงานแล้ว</p>
  </div>

  <div class="stat-card green">
    <h3><?= h((string) $total_accepted) ?></h3>
    <p>ช่างรับงานแล้ว</p>
  </div>

  <div class="stat-card cyan">
    <h3><?= h((string) $total_finished) ?></h3>
    <p>ติดตั้งเสร็จสิ้น</p>
  </div>
</div>

<div class="panel">
  <div class="panel-title">รายการงานติดตั้งของฉัน</div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสงานติดตั้ง</th>
          <th>วันที่ติดตั้ง</th>
          <th>สินค้า</th>
          <th>ประเภทสินค้า</th>
          <th>จำนวน</th>
          <th>ค่าติดตั้ง</th>
          <th>ช่างติดตั้ง</th>
          <th>สถานะ</th>
          <th>รายละเอียด</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($setups->num_rows === 0): ?>
          <tr>
            <td colspan="9" class="empty-state">
              ยังไม่มีรายการงานติดตั้ง
            </td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $setups->fetch_assoc()): ?>
          <?php
            $install_address = $row['setup_address'] ?: ($row['setup_location'] ?? '-');
            $install_qty = $row['install_qty'] ?? 1;
            $install_total = $row['install_total'] ?? $row['pro_price_install'] ?? 0;
          ?>

          <tr>
            <td><?= h($row['setup_id']) ?></td>

            <td>
              <?= !empty($row['setup_date'])
                ? h(date('d/m/Y', strtotime($row['setup_date'])))
                : '-'
              ?>
            </td>

            <td><?= h($row['pro_name'] ?? '-') ?></td>

            <td><?= h($row['protype_name'] ?? '-') ?></td>

            <td><?= h((string) $install_qty) ?></td>

            <td><?= number_format((float) $install_total, 2) ?> บาท</td>

            <td>
              <?php if (!empty($row['tech_name'])): ?>
                <?= h($row['tech_name']) ?>
                <br>
                <small><?= h($row['tech_phone'] ?? '-') ?></small>
              <?php else: ?>
                <span class="muted-text">ยังไม่ได้มอบหมาย</span>
              <?php endif; ?>
            </td>

            <td>
              <span class="badge <?= h(setup_status_badge($row['setup_status'])) ?>">
                <?= h(setup_status_name($row['setup_status'])) ?>
              </span>
            </td>

            <td>
              <details>
                <summary>ดูรายละเอียด</summary>

                <div class="customer-detail-box">
                  <p>
                    <strong>ที่อยู่ติดตั้ง:</strong>
                    <?= h($install_address) ?>
                  </p>

                  <p>
                    <strong>หมายเหตุ:</strong>
                    <?= h($row['setup_note'] ?: '-') ?>
                  </p>

                  <p>
                    <strong>วันที่สร้างรายการ:</strong>
                    <?= !empty($row['created_at'])
                      ? h(date('d/m/Y H:i', strtotime($row['created_at'])))
                      : '-'
                    ?>
                  </p>

                  <p>
                    <strong>วันที่มอบหมาย:</strong>
                    <?= !empty($row['assign_date'])
                      ? h(date('d/m/Y H:i', strtotime($row['assign_date'])))
                      : '-'
                    ?>
                  </p>
                </div>
              </details>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
layout_footer();