<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';

function safe_count(mysqli $conn, string $sql, string $tech_id): int
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $tech_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function assign_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่มอบหมาย',
        '1' => 'มอบหมายแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกแล้ว',
        '5' => 'เสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function assign_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'green',
        '3' => 'red',
        '4' => 'red',
        '5' => 'green',
        default => 'red',
    };
}

$total_assigned = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 1
", $tech_id);

$total_accepted = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 2
", $tech_id);

$total_rejected = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 3
", $tech_id);

$total_finished = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 5
", $tech_id);

$stmt_jobs = $conn->prepare("
    SELECT
        a.assign_id,
        a.assign_date,
        a.assign_status,

        s.setup_id,
        s.setup_date,
        s.setup_address,
        s.setup_location,

        c.customer_name AS user_name,
        c.customer_phone AS user_phone,

        p.pro_name
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON COALESCE(a.customer_id, s.customer_id) = c.customer_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    WHERE a.tech_id = ?
    ORDER BY a.assign_date DESC, a.assign_id DESC
    LIMIT 5
");

$stmt_jobs->bind_param('s', $tech_id);
$stmt_jobs->execute();
$recent_jobs = $stmt_jobs->get_result();

layout_header('หน้าหลักช่างติดตั้ง', 'dashboard');
?>

<div class="role-hero">
  <div>
    <div class="hero-tag">Technician</div>

    <h2>หน้าหลักช่างติดตั้ง</h2>

    <p>
      ช่างติดตั้งสามารถตรวจสอบงานที่ได้รับมอบหมาย
      ยืนยันการรับงาน และติดตามสถานะงานติดตั้งของตนเองได้
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">🛠️</div>
    <strong>ช่างติดตั้ง</strong>
    <span>จัดการงานติดตั้งของฉัน</span>
  </div>
</div>

<div class="stat-grid technician-stat-grid">
  <div class="stat-card blue">
    <h3><?= h((string) $total_assigned) ?></h3>
    <p>งานที่รอรับ</p>
  </div>

  <div class="stat-card green">
    <h3><?= h((string) $total_accepted) ?></h3>
    <p>งานที่รับแล้ว</p>
  </div>

  <div class="stat-card orange">
    <h3><?= h((string) $total_rejected) ?></h3>
    <p>งานที่ปฏิเสธ</p>
  </div>

  <div class="stat-card purple">
    <h3><?= h((string) $total_finished) ?></h3>
    <p>เสร็จสิ้น</p>
  </div>
</div>

<div class="panel">
  <div class="panel-title-row">
    <div class="panel-title">งานล่าสุดของฉัน</div>

    <a class="btn-small" href="<?= h(app_system_url('technician/accept_job.php')) ?>">
      ดูงานทั้งหมด
    </a>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสมอบหมาย</th>
          <th>รหัสงานติดตั้ง</th>
          <th>วันที่ติดตั้ง</th>
          <th>ลูกค้า</th>
          <th>สินค้า</th>
          <th>วันที่มอบหมาย</th>
          <th>สถานะ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($recent_jobs->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="empty-state">ยังไม่มีงานที่ได้รับมอบหมาย</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $recent_jobs->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['assign_id']) ?></td>
            <td><?= h($row['setup_id'] ?? '-') ?></td>

            <td>
              <?= !empty($row['setup_date'])
                ? h(date('d/m/Y', strtotime($row['setup_date'])))
                : '-'
              ?>
            </td>

            <td>
              <?= h($row['user_name'] ?? '-') ?>
              <br>
              <small><?= h($row['user_phone'] ?? '-') ?></small>
            </td>

            <td><?= h($row['pro_name'] ?? '-') ?></td>

            <td>
              <?= !empty($row['assign_date'])
                ? h(date('d/m/Y H:i', strtotime($row['assign_date'])))
                : '-'
              ?>
            </td>

            <td>
              <span class="badge <?= h(assign_status_badge($row['assign_status'])) ?>">
                <?= h(assign_status_name($row['assign_status'])) ?>
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
