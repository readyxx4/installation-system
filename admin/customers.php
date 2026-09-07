<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../customer_profiles.php';

require_login('3');
ensure_customer_profiles_schema($conn);

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

function customer_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function customer_has_related_work(mysqli $conn, string $customer_id): bool
{
    foreach (['setup', 'assignment', 'product_payment'] as $table) {
        if (!customer_table_has_column($conn, $table, 'customer_id')) {
            continue;
        }

        $stmt = $conn->prepare("SELECT 1 FROM `{$table}` WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param('s', $customer_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            return true;
        }
    }

    return false;
}

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/customers.php?status=error'));
    }

    try {
        if (customer_has_related_work($conn, $delete_id)) {
            redirect_to(app_system_url('admin/customers.php?status=error'));
        }

        $stmt = $conn->prepare('DELETE FROM customers WHERE customer_id = ? LIMIT 1');
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
        SELECT
          c.customer_id,
          c.customer_name AS user_name,
          c.customer_phone AS user_phone,
          c.customer_email AS user_email,
          c.customer_address AS user_address
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
          c.customer_address AS user_address
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

    <button class="btn btn-search" type="submit">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
      <span>ค้นหา</span>
    </button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/customers.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
        <path d="M3 4v6h6"></path>
      </svg>
      <span>ล้างค้นหา</span>
    </a>

    <a class="btn btn-add customer-add-in-toolbar" href="<?= h(app_system_url('admin/customer_add.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
      <span>เพิ่มข้อมูลลูกค้า</span>
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
            <td><?= h($row['customer_id'] ?: '-') ?></td>
            <td><?= h($row['user_name'] ?: '-') ?></td>
            <td><?= h($row['user_phone']) ?></td>
            <td><?= h($row['user_email']) ?></td>
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
              <?php $can_delete_customer = !customer_has_related_work($conn, $row['customer_id']); ?>
              <a class="btn btn-edit action-btn customer-action-btn"
                 href="<?= h(app_system_url('admin/customer_edit.php?id=' . urlencode($row['customer_id']))) ?>">
                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 20h9"></path>
                  <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                </svg>
                <span>แก้ไข</span>
              </a>

              <?php if ($can_delete_customer): ?>
                <a class="btn btn-delete action-btn customer-action-btn"
                   href="<?= h(app_system_url('admin/customers.php?action=delete&id=' . urlencode($row['customer_id']))) ?>"
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
              <?php else: ?>
                <span class="btn btn-delete disabled-link action-btn customer-action-btn"
                      aria-disabled="true"
                      title="ไม่สามารถลบได้ เนื่องจากมีประวัติใบงานหรือข้อมูลที่เกี่ยวข้อง">
                  <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M19 6l-1 14H6L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                  <span>ลบ</span>
                </span>
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


