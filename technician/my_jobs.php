<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';
$search = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

if ($tech_id === '') {
    redirect_to(app_public_url('login.html?error=login'));
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

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอมอบหมายงาน',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
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

function count_my_job(mysqli $conn, string $tech_id, int $status): int
{
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM assignment
            WHERE tech_id = ?
              AND assign_status = ?
        ");

        $stmt->bind_param('si', $tech_id, $status);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$total_assigned = count_my_job($conn, $tech_id, 1);
$total_accepted = count_my_job($conn, $tech_id, 2);
$total_rejected = count_my_job($conn, $tech_id, 3);
$total_finished = count_my_job($conn, $tech_id, 5);

$sql = "
    SELECT
        a.assign_id,
        a.assign_date,
        a.assign_status,

        s.setup_id,
        s.setup_date,
        s.setup_address,
        s.setup_location,
        s.setup_status,

        u.user_name,
        u.user_phone,
        u.user_email,

        p.pro_name,

        d.install_qty,
        d.install_total
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN `user` u ON a.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN install_detail d ON s.setup_id = d.setup_id
    WHERE a.tech_id = ?
";

$params = [$tech_id];
$types = 's';

if ($status_filter !== '' && in_array($status_filter, ['1', '2', '3', '4', '5'], true)) {
    $sql .= " AND a.assign_status = ? ";
    $status_int = (int) $status_filter;
    $params[] = $status_int;
    $types .= 'i';
}

if ($search !== '') {
    $sql .= "
        AND (
            a.assign_id LIKE ?
         OR s.setup_id LIKE ?
         OR u.user_name LIKE ?
         OR u.user_phone LIKE ?
         OR u.user_email LIKE ?
         OR p.pro_name LIKE ?
         OR s.setup_address LIKE ?
         OR s.setup_location LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}

$sql .= "
    ORDER BY a.assign_date DESC, a.assign_id DESC
";

$stmt_jobs = $conn->prepare($sql);
$stmt_jobs->bind_param($types, ...$params);
$stmt_jobs->execute();
$jobs = $stmt_jobs->get_result();

layout_header('งานของฉัน', 'my_jobs');
page_head('งานของฉัน', 'ช่างติดตั้ง > งานของฉัน');
?>

<?= flash_message() ?>

<div class="role-hero">
  <div>
    <div class="hero-tag">My Jobs</div>

    <h2>งานติดตั้งของฉัน</h2>

    <p>
      แสดงรายการงานติดตั้งที่ได้รับมอบหมายจากหัวหน้าช่าง
      สามารถตรวจสอบข้อมูลลูกค้า สินค้า สถานที่ติดตั้ง และสถานะงานได้
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">📋</div>
    <strong>งานของฉัน</strong>
    <span>ติดตามงานที่ได้รับมอบหมาย</span>
  </div>
</div>

<div class="stat-grid technician-stat-grid">
  <div class="stat-card blue">
    <h3><?= h((string) $total_assigned) ?></h3>
    <p>รอรับงาน</p>
  </div>

  <div class="stat-card green">
    <h3><?= h((string) $total_accepted) ?></h3>
    <p>รับงานแล้ว</p>
  </div>

  <div class="stat-card orange">
    <h3><?= h((string) $total_rejected) ?></h3>
    <p>ปฏิเสธงาน</p>
  </div>

  <div class="stat-card purple">
    <h3><?= h((string) $total_finished) ?></h3>
    <p>เสร็จสิ้น</p>
  </div>
</div>

<div class="panel">
  <div class="panel-title">รายการงานของฉัน</div>

  <form class="toolbar my-jobs-toolbar" method="GET" action="<?= h(app_system_url('technician/my_jobs.php')) ?>">
    <input
      type="text"
      name="q"
      placeholder="ค้นหารหัสงาน ลูกค้า เบอร์โทร สินค้า หรือที่อยู่"
      value="<?= h($search) ?>"
    >

    <select name="status">
      <option value="">ทุกสถานะ</option>
      <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>มอบหมายแล้ว</option>
      <option value="2" <?= $status_filter === '2' ? 'selected' : '' ?>>ช่างรับงานแล้ว</option>
      <option value="3" <?= $status_filter === '3' ? 'selected' : '' ?>>ช่างปฏิเสธงาน</option>
      <option value="5" <?= $status_filter === '5' ? 'selected' : '' ?>>เสร็จสิ้น</option>
    </select>

    <button class="btn" type="submit">ค้นหา</button>

    <a class="btn-secondary" href="<?= h(app_system_url('technician/my_jobs.php')) ?>">
      ล้างค้นหา
    </a>

    <a class="btn my-jobs-accept-link" href="<?= h(app_system_url('technician/accept_job.php')) ?>">
      ไปหน้ายืนยันรับงาน
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสมอบหมาย</th>
          <th>รหัสงานติดตั้ง</th>
          <th>วันที่ติดตั้ง</th>
          <th>ข้อมูลลูกค้า</th>
          <th>สินค้า</th>
          <th>จำนวน</th>
          <th>รวมค่าติดตั้ง</th>
          <th>สถานะมอบหมาย</th>
          <th>สถานะติดตั้ง</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($jobs->num_rows === 0): ?>
          <tr>
            <td colspan="9" class="empty-state">ไม่พบข้อมูลงานของฉัน</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $jobs->fetch_assoc()): ?>
          <?php
            $install_address = $row['setup_address'] ?: ($row['setup_location'] ?? '-');
          ?>

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
              <br>
              <small><?= h($row['user_email'] ?? '-') ?></small>
            </td>

            <td>
              <?= h($row['pro_name'] ?? '-') ?>
              <br>
              <small><?= h($install_address) ?></small>
            </td>

            <td><?= h((string) ($row['install_qty'] ?? '-')) ?></td>

            <td>
              <?= number_format((float) ($row['install_total'] ?? 0), 2) ?> บาท
            </td>

            <td>
              <span class="badge <?= h(assign_status_badge($row['assign_status'])) ?>">
                <?= h(assign_status_name($row['assign_status'])) ?>
              </span>
            </td>

            <td>
              <span class="badge <?= h(setup_status_badge($row['setup_status'])) ?>">
                <?= h(setup_status_name($row['setup_status'])) ?>
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
