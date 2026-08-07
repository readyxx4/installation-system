<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }

    try {
        foreach ([
            ['setup', 'setup_id'],
            ['assignment', 'assign_id'],
            ['product_payment', 'paymentpro_id'],
        ] as [$table, $idColumn]) {
            $stmt = $conn->prepare("SELECT {$idColumn} FROM {$table} WHERE user_id = ? LIMIT 1");
            $stmt->bind_param('s', $delete_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                redirect_to(app_system_url('admin/customers.php?status=customer_linked'));
            }
        }

        $stmt = $conn->prepare("DELETE FROM `user` WHERE user_id = ? AND user_role = 0");
        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/customers.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT user_id, user_name, user_phone, user_email, user_address
        FROM `user`
        WHERE user_role = 0
          AND (
              user_id LIKE ?
           OR user_name LIKE ?
           OR user_phone LIKE ?
           OR user_email LIKE ?
           OR user_address LIKE ?
          )
        ORDER BY user_id ASC
    ");
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = $conn->query("
        SELECT user_id, user_name, user_phone, user_email, user_address
        FROM `user`
        WHERE user_role = 0
        ORDER BY user_id ASC
    ");
}

layout_header('จัดการข้อมูลลูกค้า', 'customers');
?>

<?= flash_message() ?>

<div class="panel">
  <div class="panel-title">รายการข้อมูลลูกค้าทั้งหมด</div>

  <form class="toolbar customer-toolbar" method="GET" action="<?= h(app_system_url('admin/customers.php')) ?>">
    <input type="text" name="q" placeholder="ค้นหารหัสลูกค้า ชื่อ-นามสกุล เบอร์โทร อีเมล หรือที่อยู่" value="<?= h($search) ?>">
    <button class="btn btn-search" type="submit">ค้นหา</button>
    <a class="btn btn-reset" href="<?= h(app_system_url('admin/customers.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
        <path d="M3 4v6h6"></path>
      </svg>
      <span>ล้างค้นหา</span>
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสลูกค้า</th>
          <th>ชื่อ-นามสกุล</th>
          <th>เบอร์โทรศัพท์</th>
          <th>อีเมล</th>
          <th>ที่อยู่</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($customers->num_rows === 0): ?>
          <tr><td colspan="6" class="empty-state">ไม่พบข้อมูลลูกค้า</td></tr>
        <?php endif; ?>

        <?php while ($row = $customers->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['user_id']) ?></td>
            <td><?= h($row['user_name'] ?: '-') ?></td>
            <td><?= h($row['user_phone']) ?></td>
            <td><?= h($row['user_email']) ?></td>
            <td><?= h(mb_strlen($row['user_address'], 'UTF-8') > 35 ? mb_substr($row['user_address'], 0, 35, 'UTF-8') . '...' : $row['user_address']) ?></td>
            <td>
              <a class="btn btn-edit"
                 href="<?= h(app_system_url('admin/customer_edit.php?id=' . urlencode($row['user_id']))) ?>">
                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 20h9"></path>
                  <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                </svg>
                <span>แก้ไข</span>
              </a>

              <a class="btn btn-delete"
                 href="<?= h(app_system_url('admin/customers.php?action=delete&id=' . urlencode($row['user_id']))) ?>"
                 data-confirm-delete="ยืนยันการลบข้อมูลลูกค้านี้หรือไม่?">
                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3 6h18"></path>
                  <path d="M8 6V4h8v2"></path>
                  <path d="M19 6l-1 14H6L5 6"></path>
                  <path d="M10 11v6"></path>
                  <path d="M14 11v6"></path>
                </svg>
                <span>ลบ</span>
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>

<?php layout_footer(); ?>
