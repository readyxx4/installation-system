<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function ensure_tech_fullname_column(mysqli $conn): void
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = 'technicians'\n          AND COLUMN_NAME = 'tech_fullname'\n        LIMIT 1\n    ");
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $conn->query("ALTER TABLE technicians ADD COLUMN tech_fullname VARCHAR(100) NOT NULL DEFAULT '' AFTER tech_name");
    }
}

function tech_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'พร้อมรับงาน',
        '1' => 'ไม่พร้อมรับงาน',
        default => 'ไม่ทราบสถานะ',
    };
}

function tech_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'green',
        '1' => 'red',
        default => 'orange',
    };
}

ensure_tech_fullname_column($conn);

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/technicians.php?status=error'));
    }

    try {
        /*
          ตรวจสอบก่อนว่าช่างคนนี้เคยถูกมอบหมายงานหรือไม่
          ถ้าเคยมีข้อมูลใน assignment จะไม่อนุญาตให้ลบ
        */
        $check_assignment = $conn->prepare("
            SELECT assign_id
            FROM assignment
            WHERE tech_id = ?
            LIMIT 1
        ");

        $check_assignment->bind_param('s', $delete_id);
        $check_assignment->execute();
        $assignment_result = $check_assignment->get_result();

        if ($assignment_result->num_rows > 0) {
            redirect_to(app_system_url('admin/technicians.php?status=tech_assigned'));
        }

        $stmt = $conn->prepare("
            DELETE FROM technicians
            WHERE tech_id = ?
        ");

        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/technicians.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/technicians.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("\n        SELECT tech_id, tech_name, tech_fullname, tech_phone, tech_email, tech_status\n        FROM technicians\n        WHERE tech_id LIKE ?\n           OR tech_name LIKE ?\n           OR tech_fullname LIKE ?\n           OR tech_phone LIKE ?\n           OR tech_email LIKE ?\n        ORDER BY tech_id ASC\n    ");

    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $technicians = $stmt->get_result();
} else {
    $technicians = $conn->query("\n        SELECT tech_id, tech_name, tech_fullname, tech_phone, tech_email, tech_status\n        FROM technicians\n        ORDER BY tech_id ASC\n    ");
}

layout_header('จัดการข้อมูลช่างติดตั้ง', 'technicians');
// page_head('จัดการข้อมูลช่างติดตั้ง');
?>

<?= flash_message() ?>

<div class="panel">
  <div class="panel-title">รายการข้อมูลช่างทั้งหมด</div>

  <form class="toolbar technician-toolbar" method="GET" action="<?= h(app_system_url('admin/technicians.php')) ?>">
    <input
      type="text"
      name="q"
      placeholder="ค้นหารหัส ชื่อผู้ใช้ ชื่อ-นามสกุล เบอร์โทร หรืออีเมล"
      value="<?= h($search) ?>"
    >

    <button class="btn btn-search" type="submit"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg><span>ค้นหา</span></button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/technicians.php')) ?>">
      ล้างค้นหา
    </a>

    <a class="btn btn-add technician-add-in-toolbar" href="<?= h(app_system_url('admin/technician_add.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg><span>เพิ่มข้อมูลช่าง</span>
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสช่าง</th>
          <th>ชื่อผู้ใช้</th>
          <th>ชื่อ-นามสกุลจริง</th>
          <th>เบอร์โทรศัพท์</th>
          <th>อีเมล</th>
          <th>สถานะช่าง</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($technicians->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="empty-state">ไม่พบข้อมูลช่าง</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $technicians->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['tech_id']) ?></td>
            <td><?= h($row['tech_name']) ?></td>
            <td><?= h($row['tech_fullname'] ?: '-') ?></td>
            <td><?= h($row['tech_phone']) ?></td>
            <td><?= h($row['tech_email']) ?></td>
            <td>
              <span class="badge <?= h(tech_status_badge($row['tech_status'])) ?>">
                <?= h(tech_status_name($row['tech_status'])) ?>
              </span>
            </td>
            <td>
              <a
                class="btn btn-edit"
                href="<?= h(app_system_url('admin/technician_edit.php?id=' . urlencode($row['tech_id']))) ?>"
              >
                แก้ไข
              </a>

              <a
                class="btn btn-delete"
                href="<?= h(app_system_url('admin/technicians.php?action=delete&id=' . urlencode($row['tech_id']))) ?>"
                onclick="return confirm('ยืนยันการลบข้อมูลช่างนี้หรือไม่?')"
              >
                ลบ
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
layout_footer();