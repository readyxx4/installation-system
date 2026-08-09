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
        '0' => 'ยังไม่ได้มอบหมาย',
        '1' => 'มอบหมายงานติดตั้งแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
        '5' => 'ยกเลิกแล้ว',
        default => 'ไม่ทราบสถานะ',
    };
}

function setup_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'cyan',
        '3' => 'purple',
        '4' => 'green',
        '5' => 'slate',
        default => 'slate',
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

<div class="customer-page customer-dashboard-v2">
  <div class="admin-dashboard-top customer-dashboard-head">
    <div>
      <h1>ภาพรวมลูกค้า</h1>
      <p>ติดตามสถานะใบงานติดตั้งและข้อมูลช่างที่รับผิดชอบงานของคุณ</p>
    </div>

    <div class="admin-date-pill customer-date-pill">
      <i class="fa-regular fa-calendar"></i>
      <?= h(date('d/m/Y')) ?>
    </div>
  </div>

  <div class="admin-summary-grid customer-stat-grid">
    <div class="admin-summary-card customer-summary-card">
      <div>
        <span>งานติดตั้งทั้งหมด</span>
        <strong><?= h((string) $total_setup) ?></strong>
        <small>รายการใบงานทั้งหมดของฉัน</small>
      </div>

      <div class="summary-icon">
        <i class="fa-solid fa-clipboard-list"></i>
      </div>
    </div>

    <div class="admin-summary-card customer-summary-card">
      <div>
        <span>ยังไม่ได้มอบหมาย</span>
        <strong><?= h((string) $total_waiting) ?></strong>
        <small>รอหัวหน้าช่างตรวจสอบ</small>
      </div>

      <div class="summary-icon">
        <i class="fa-solid fa-hourglass-half"></i>
      </div>
    </div>

    <div class="admin-summary-card customer-summary-card">
      <div>
        <span>มอบหมายงานติดตั้งแล้ว</span>
        <strong><?= h((string) $total_assigned) ?></strong>
        <small>มีช่างรับผิดชอบงานแล้ว</small>
      </div>

      <div class="summary-icon">
        <i class="fa-solid fa-user-check"></i>
      </div>
    </div>

    <div class="admin-summary-card customer-summary-card">
      <div>
        <span>ช่างรับงานแล้ว</span>
        <strong><?= h((string) $total_accepted) ?></strong>
        <small>ช่างยืนยันรับงานติดตั้ง</small>
      </div>

      <div class="summary-icon">
        <i class="fa-solid fa-screwdriver-wrench"></i>
      </div>
    </div>

    <div class="admin-summary-card customer-summary-card">
      <div>
        <span>เสร็จสิ้น</span>
        <strong><?= h((string) $total_finished) ?></strong>
        <small>งานติดตั้งที่ปิดงานแล้ว</small>
      </div>

      <div class="summary-icon">
        <i class="fa-solid fa-circle-check"></i>
      </div>
    </div>
  </div>

  <div class="admin-widget customer-work-panel">
    <div class="admin-widget-head">
      <div>
        <h2>รายการงานติดตั้งของฉัน</h2>
        <p>ตรวจสอบวันติดตั้ง สินค้า ช่างที่รับผิดชอบ และสถานะล่าสุด</p>
      </div>
    </div>

    <div class="table-wrap customer-table-wrap">
      <table class="data-table customer-data-table">
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
              <td><strong><?= h($row['setup_id']) ?></strong></td>

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
                  <strong><?= h($row['tech_name']) ?></strong>
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
                <details class="customer-detail-collapse">
                  <summary class="btn-small customer-detail-button">
                    <i class="fa-regular fa-eye"></i>
                    ดูรายละเอียด
                  </summary>

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
</div>

<?php
layout_footer();
