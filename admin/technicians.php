<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

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

function technician_has_assignment(mysqli $conn, string $tech_id): bool
{
    $check_assignment = $conn->prepare("
        SELECT assign_id
        FROM assignment
        WHERE tech_id = ?
        LIMIT 1
    ");
    $check_assignment->bind_param('s', $tech_id);
    $check_assignment->execute();

    return $check_assignment->get_result()->num_rows > 0;
}

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/technicians.php?status=error'));
    }

    try {
        if (technician_has_assignment($conn, $delete_id)) {
            redirect_to(app_system_url('admin/technicians.php?status=tech_assigned'));
        }

        $stmt = $conn->prepare("DELETE FROM technicians WHERE tech_id = ?");
        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/technicians.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/technicians.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT tech_id, tech_name, tech_phone, tech_email, tech_status
        FROM technicians
        WHERE tech_id LIKE ?
           OR tech_name LIKE ?
           OR tech_phone LIKE ?
           OR tech_email LIKE ?
        ORDER BY tech_id DESC
    ");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $technicians = $stmt->get_result();
} else {
    $technicians = $conn->query("
        SELECT tech_id, tech_name, tech_phone, tech_email, tech_status
        FROM technicians
        ORDER BY tech_id DESC
    ");
}

layout_header('จัดการข้อมูลช่างติดตั้ง', 'technicians');
?>

<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/admin_lists.css')) ?>?v=<?= h(asset_version('admin/assets/css/admin_lists.css')) ?>">

<?= flash_message() ?>

<div class="panel admin-list-page admin-technicians-page">
  <div class="panel-title">รายการข้อมูลช่างทั้งหมด</div>

  <form class="toolbar technician-toolbar" method="GET" action="<?= h(app_system_url('admin/technicians.php')) ?>">
    <input type="text" name="q" placeholder="ค้นหารหัส ชื่อ-นามสกุล เบอร์โทร หรืออีเมล" value="<?= h($search) ?>">

    <button class="btn btn-search" type="submit">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
      <span>ค้นหา</span>
    </button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/technicians.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
        <path d="M3 4v6h6"></path>
      </svg>
      <span>ล้างค้นหา</span>
    </a>

    <a class="btn btn-add technician-add-in-toolbar" href="<?= h(app_system_url('admin/technician_add.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
      <span>เพิ่มข้อมูลช่าง</span>
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสช่าง</th>
          <th>ชื่อ-นามสกุล</th>
          <th>เบอร์โทรศัพท์</th>
          <th>อีเมล</th>
          <th>สถานะช่าง</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($technicians->num_rows === 0): ?>
          <tr><td colspan="6" class="empty-state">ไม่พบข้อมูลช่าง</td></tr>
        <?php endif; ?>

        <?php while ($row = $technicians->fetch_assoc()): ?>
          <?php $has_assignment = technician_has_assignment($conn, $row['tech_id']); ?>
          <tr>
            <td><?= h($row['tech_id']) ?></td>
            <td><?= h($row['tech_name'] ?: '-') ?></td>
            <td><?= h($row['tech_phone']) ?></td>
            <td><?= h($row['tech_email']) ?></td>
            <td>
              <span class="badge <?= h(tech_status_badge($row['tech_status'])) ?>">
                <?= h(tech_status_name($row['tech_status'])) ?>
              </span>
            </td>
            <td>
              <a class="btn btn-edit"
                 href="<?= h(app_system_url('admin/technician_edit.php?id=' . urlencode($row['tech_id']))) ?>">
                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 20h9"></path>
                  <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                </svg>
                <span>แก้ไข</span>
              </a>

              <?php if ($has_assignment): ?>
                <span class="btn btn-delete disabled-link"
                      aria-disabled="true"
                      title="ไม่สามารถลบได้ เนื่องจากมีประวัติการมอบหมายงาน">
                  <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M19 6l-1 14H6L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                  <span>ลบ</span>
                </span>
              <?php else: ?>
                <a class="btn btn-delete"
                   href="<?= h(app_system_url('admin/technicians.php?action=delete&id=' . urlencode($row['tech_id']))) ?>"
                   data-confirm-delete="ยืนยันการลบข้อมูลช่างนี้หรือไม่?">
                  <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M19 6l-1 14H6L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                  <span>ลบ</span>
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>

<?php layout_footer(); ?>
