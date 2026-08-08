<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assign_id = trim($_POST['assign_id'] ?? '');
    $action = trim($_POST['action'] ?? '');

    if ($assign_id === '' || !in_array($action, ['accept', 'reject'], true)) {
        redirect_to(app_system_url('technician/accept_job.php?status=error'));
    }

    try {
        $stmt = $conn->prepare("
            SELECT assign_id, setup_id, tech_id, assign_status
            FROM assignment
            WHERE assign_id = ?
              AND tech_id = ?
            LIMIT 1
        ");

        $stmt->bind_param('ss', $assign_id, $tech_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            redirect_to(app_system_url('technician/accept_job.php?status=error'));
        }

        $assignment = $result->fetch_assoc();

        if ((int) $assignment['assign_status'] !== 1) {
            redirect_to(app_system_url('technician/accept_job.php?status=error'));
        }

        $setup_id = $assignment['setup_id'];

        $conn->begin_transaction();

        if ($action === 'accept') {
            $assign_status = 2;
            $setup_status = 2;

            $update_assign = $conn->prepare("
                UPDATE assignment
                SET assign_status = ?
                WHERE assign_id = ?
                  AND tech_id = ?
            ");

            $update_assign->bind_param(
                'iss',
                $assign_status,
                $assign_id,
                $tech_id
            );

            $update_assign->execute();

            $update_setup = $conn->prepare("
                UPDATE setup
                SET setup_status = ?
                WHERE setup_id = ?
            ");

            $update_setup->bind_param(
                'is',
                $setup_status,
                $setup_id
            );

            $update_setup->execute();

            $conn->commit();

            redirect_to(app_system_url('technician/accept_job.php?status=updated'));
        }

        if ($action === 'reject') {
            $assign_status = 3;
            $setup_status = 0;

            $update_assign = $conn->prepare("
                UPDATE assignment
                SET assign_status = ?
                WHERE assign_id = ?
                  AND tech_id = ?
            ");

            $update_assign->bind_param(
                'iss',
                $assign_status,
                $assign_id,
                $tech_id
            );

            $update_assign->execute();

            $update_setup = $conn->prepare("
                UPDATE setup
                SET setup_status = ?
                WHERE setup_id = ?
            ");

            $update_setup->bind_param(
                'is',
                $setup_status,
                $setup_id
            );

            $update_setup->execute();

            $conn->commit();

            redirect_to(app_system_url('technician/accept_job.php?status=updated'));
        }
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // ข้าม
        }

        redirect_to(app_system_url('technician/accept_job.php?status=error'));
    }
}

$stmt_jobs = $conn->prepare("
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
    ORDER BY a.assign_date DESC, a.assign_id DESC
");

$stmt_jobs->bind_param('s', $tech_id);
$stmt_jobs->execute();
$jobs_result = $stmt_jobs->get_result();

layout_header('ยืนยันการรับงาน', 'accept_job');
page_head('ยืนยันการรับงาน', 'Process 6 : ยืนยันการรับงาน');
?>

<?= flash_message() ?>

<div class="role-hero">
  <div>
    <div class="hero-tag">Process 6</div>

    <h2>ยืนยันการรับงานติดตั้ง</h2>

    <p>
      ช่างติดตั้งตรวจสอบงานที่ได้รับมอบหมายจากหัวหน้าช่าง
      แล้วกดยืนยันรับงานหรือปฏิเสธงาน
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">✅</div>
    <strong>Accept Job</strong>
    <span>ยืนยันการรับงานติดตั้ง</span>
  </div>
</div>

<div class="panel">
  <div class="panel-title">งานที่ได้รับมอบหมาย</div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสมอบหมาย</th>
          <th>รหัสงานติดตั้ง</th>
          <th>วันที่ติดตั้ง</th>
          <th>ลูกค้า</th>
          <th>สินค้า / ที่อยู่ติดตั้ง</th>
          <th>จำนวน</th>
          <th>สถานะมอบหมาย</th>
          <th>สถานะติดตั้ง</th>
          <th style="width:190px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($jobs_result->num_rows === 0): ?>
          <tr>
            <td colspan="9" class="empty-state">ยังไม่มีงานที่ได้รับมอบหมาย</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $jobs_result->fetch_assoc()): ?>
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
              <span class="badge <?= h(assign_status_badge($row['assign_status'])) ?>">
                <?= h(assign_status_name($row['assign_status'])) ?>
              </span>
            </td>

            <td>
              <?= h(setup_status_name($row['setup_status'])) ?>
            </td>

            <td>
              <?php if ((int) $row['assign_status'] === 1): ?>
                <form method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>" style="display:inline;">
                  <input type="hidden" name="assign_id" value="<?= h($row['assign_id']) ?>">
                  <input type="hidden" name="action" value="accept">

                  <button
                    class="btn-small"
                    type="submit"
                    onclick="return confirm('ยืนยันรับงานนี้หรือไม่?')"
                  >
                    รับงาน
                  </button>
                </form>

                <form method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>" style="display:inline;">
                  <input type="hidden" name="assign_id" value="<?= h($row['assign_id']) ?>">
                  <input type="hidden" name="action" value="reject">

                  <button
                    class="btn-danger"
                    type="submit"
                    onclick="return confirm('ต้องการปฏิเสธงานนี้หรือไม่?')"
                  >
                    ปฏิเสธ
                  </button>
                </form>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
layout_footer();
