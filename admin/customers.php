<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../customer_profiles.php';

require_login('3');
ensure_customer_profiles_schema($conn);

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'set_status') {
    $customer_id = trim($_GET['id'] ?? '');
    $status_to = trim($_GET['to'] ?? '');

    if ($customer_id === '' || !in_array($status_to, ['active', 'suspended'], true)) {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }

    $customer_status = $status_to === 'suspended' ? 0 : 1;

    try {
        $check = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE customer_id = ?
            LIMIT 1
        ");
        $check->bind_param('s', $customer_id);
        $check->execute();
        $customer = $check->get_result()->fetch_assoc();

        if (!$customer) {
            redirect_to(app_system_url('admin/customers.php?status=error'));
        }

        $update = $conn->prepare("
            UPDATE customers
            SET customer_status = ?
            WHERE customer_id = ?
            LIMIT 1
        ");
        $update->bind_param('is', $customer_status, $customer_id);
        $update->execute();

        $status = $customer_status === 0 ? 'customer_suspended' : 'customer_restored';
        redirect_to(app_system_url('admin/customers.php?status=' . $status));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }
}

if ($action === 'delete') {
    redirect_to(app_system_url('admin/customers.php?status=customer_delete_disabled'));
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT
          c.customer_id,
          c.customer_name AS user_name,
          c.customer_phone AS user_phone,
          c.customer_email AS user_email,
          c.customer_address AS user_address,
          c.customer_status
        FROM customers c
        WHERE (
              c.customer_id LIKE ?
           OR c.customer_name LIKE ?
           OR c.customer_phone LIKE ?
           OR c.customer_email LIKE ?
           OR c.customer_address LIKE ?
          )
        ORDER BY c.customer_id DESC
    ");
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = $conn->query("
        SELECT
          c.customer_id,
          c.customer_name AS user_name,
          c.customer_phone AS user_phone,
          c.customer_email AS user_email,
          c.customer_address AS user_address,
          c.customer_status
        FROM customers c
        ORDER BY c.customer_id DESC
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
      <table class="data-table admin-customers-table">
      <thead>
        <tr>
          <th>รหัสลูกค้า</th>
          <th>ชื่อ-นามสกุล</th>
          <th>เบอร์โทรศัพท์</th>
          <th>อีเมล</th>
          <th>สถานะบัญชี</th>
          <th>ที่อยู่</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($customers->num_rows === 0): ?>
          <tr><td colspan="7" class="empty-state">ไม่พบข้อมูลลูกค้า</td></tr>
        <?php endif; ?>

        <?php while ($row = $customers->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['customer_id'] ?: '-') ?></td>
            <td><?= h($row['user_name'] ?: '-') ?></td>
            <td><?= h($row['user_phone']) ?></td>
            <td><?= h($row['user_email']) ?></td>
            <td>
              <span class="badge <?= h(customer_account_status_badge($row['customer_status'])) ?>">
                <?= h(customer_account_status_name($row['customer_status'])) ?>
              </span>
            </td>
            <td class="address-cell admin-address-cell">
              <?php
                $address = (string) ($row['user_address'] ?? '');
                $is_long = mb_strlen($address, 'UTF-8') > 35;
                $short_address = $is_long
                  ? mb_substr($address, 0, 35, 'UTF-8') . '...'
                  : $address;
              ?>

              <?php if ($is_long): ?>
                <span class="address-short"><?= h($short_address) ?></span>
                <span class="address-full admin-address-full" style="display:none;"><?= nl2br(h($address)) ?></span>
                <button type="button" class="text-more-btn" data-toggle-address>ดูเพิ่มเติม</button>
              <?php else: ?>
                <?= h($address) ?>
              <?php endif; ?>
            </td>
            <td class="customer-action-cell">
              <a class="btn btn-edit action-btn customer-action-btn"
                 href="<?= h(app_system_url('admin/customer_edit.php?id=' . urlencode($row['customer_id']))) ?>">
                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 20h9"></path>
                  <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                </svg>
                <span>แก้ไข</span>
              </a>

              <?php if ((int) $row['customer_status'] === 0): ?>
                <a class="btn btn-restore action-btn customer-action-btn"
                   href="<?= h(app_system_url('admin/customers.php?action=set_status&to=active&id=' . urlencode($row['customer_id']))) ?>"
                   data-confirm-delete="ยืนยันการกู้คืนบัญชีลูกค้านี้หรือไม่?">
                  <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                    <path d="M3 4v6h6"></path>
                  </svg>
                  <span>กู้คืนบัญชี</span>
                </a>
              <?php else: ?>
                <a class="btn btn-delete action-btn customer-action-btn"
                   href="<?= h(app_system_url('admin/customers.php?action=set_status&to=suspended&id=' . urlencode($row['customer_id']))) ?>"
                   data-confirm-delete="ยืนยันการระงับบัญชีลูกค้านี้หรือไม่? ลูกค้าจะเข้าสู่ระบบไม่ได้">
                  <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M5.7 5.7l12.6 12.6"></path>
                  </svg>
                  <span>ระงับบัญชี</span>
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
<script src="<?= h(app_asset_url('admin/assets/js/toggle_address.js')) ?>?v=<?= h(asset_version('admin/assets/js/toggle_address.js')) ?>"></script>

<?php layout_footer(); ?>
