<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function ensure_user_fullname_column(mysqli $conn): void
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = 'user'\n          AND COLUMN_NAME = 'user_fullname'\n        LIMIT 1\n    ");
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $conn->query("ALTER TABLE `user` ADD COLUMN user_fullname VARCHAR(100) NOT NULL DEFAULT '' AFTER user_name");
    }
}

ensure_user_fullname_column($conn);

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }

    try {
        $check_setup = $conn->prepare("
            SELECT setup_id
            FROM setup
            WHERE user_id = ?
            LIMIT 1
        ");
        $check_setup->bind_param('s', $delete_id);
        $check_setup->execute();

        if ($check_setup->get_result()->num_rows > 0) {
            redirect_to(app_system_url('admin/customers.php?status=customer_linked'));
        }

        $check_assignment = $conn->prepare("
            SELECT assign_id
            FROM assignment
            WHERE user_id = ?
            LIMIT 1
        ");
        $check_assignment->bind_param('s', $delete_id);
        $check_assignment->execute();

        if ($check_assignment->get_result()->num_rows > 0) {
            redirect_to(app_system_url('admin/customers.php?status=customer_linked'));
        }

        $check_payment = $conn->prepare("
            SELECT paymentpro_id
            FROM product_payment
            WHERE user_id = ?
            LIMIT 1
        ");
        $check_payment->bind_param('s', $delete_id);
        $check_payment->execute();

        if ($check_payment->get_result()->num_rows > 0) {
            redirect_to(app_system_url('admin/customers.php?status=customer_linked'));
        }

        $stmt = $conn->prepare("
            DELETE FROM `user`
            WHERE user_id = ?
              AND user_role = 0
        ");

        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/customers.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("\n        SELECT user_id, user_name, user_fullname, user_phone, user_email, user_address\n        FROM `user`\n        WHERE user_role = 0\n          AND (\n              user_id LIKE ?\n           OR user_name LIKE ?\n           OR user_fullname LIKE ?\n           OR user_phone LIKE ?\n           OR user_email LIKE ?\n           OR user_address LIKE ?\n          )\n        ORDER BY user_id ASC\n    ");

    $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = $conn->query("\n        SELECT user_id, user_name, user_fullname, user_phone, user_email, user_address\n        FROM `user`\n        WHERE user_role = 0\n        ORDER BY user_id ASC\n    ");
}

layout_header('จัดการข้อมูลลูกค้า', 'customers');
// page_head('จัดการข้อมูลลูกค้า');
?>

<?= flash_message() ?>

<div class="panel">
  <div class="panel-title">รายการข้อมูลลูกค้าทั้งหมด</div>

  <form class="toolbar customer-toolbar" method="GET" action="<?= h(app_system_url('admin/customers.php')) ?>">
    <input
      type="text"
      name="q"
      placeholder="ค้นหารหัสลูกค้า ชื่อผู้ใช้ ชื่อ-นามสกุล เบอร์โทร อีเมล หรือที่อยู่"
      value="<?= h($search) ?>"
    >

    <button class="btn btn-search" type="submit"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg><span>ค้นหา</span></button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/customers.php')) ?>">
      ล้างค้นหา
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสลูกค้า</th>
          <th>ชื่อผู้ใช้</th>
          <th>ชื่อ-นามสกุลจริง</th>
          <th>เบอร์โทรศัพท์</th>
          <th>อีเมล</th>
          <th>ที่อยู่</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($customers->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="empty-state">ไม่พบข้อมูลลูกค้า</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $customers->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['user_id']) ?></td>
            <td><?= h($row['user_name']) ?></td>
            <td><?= h($row['user_fullname'] ?: '-') ?></td>
            <td><?= h($row['user_phone']) ?></td>
            <td><?= h($row['user_email']) ?></td>
            <td>
              <?php
                $address = (string) $row['user_address'];
                echo mb_strlen($address, 'UTF-8') > 35
                  ? h(mb_substr($address, 0, 35, 'UTF-8')) . '...'
                  : h($address);
              ?>
            </td>
            <td>
              <a class="btn btn-edit" href="<?= h(app_system_url('admin/customer_edit.php?id=' . urlencode($row['user_id']))) ?>">แก้ไข</a>
              <a class="btn btn-delete" href="<?= h(app_system_url('admin/customers.php?action=delete&id=' . urlencode($row['user_id']))) ?>" onclick="return confirm('ยืนยันการลบข้อมูลลูกค้านี้หรือไม่?')">ลบ</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
layout_footer();