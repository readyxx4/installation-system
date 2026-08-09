<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/products.php?status=error'));
    }

    try {
        $check_setup = $conn->prepare("
            SELECT setup_id
            FROM setup
            WHERE pro_id = ?
            LIMIT 1
        ");
        $check_setup->bind_param('s', $delete_id);
        $check_setup->execute();

        if ($check_setup->get_result()->num_rows > 0) {
            redirect_to(app_system_url('admin/products.php?status=product_linked'));
        }

        $check_detail = $conn->prepare("
            SELECT detail_id
            FROM install_detail
            WHERE pro_id = ?
            LIMIT 1
        ");
        $check_detail->bind_param('s', $delete_id);
        $check_detail->execute();

        if ($check_detail->get_result()->num_rows > 0) {
            redirect_to(app_system_url('admin/products.php?status=product_linked'));
        }

        $stmt = $conn->prepare("DELETE FROM product WHERE pro_id = ?");
        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/products.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/products.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT 
            p.pro_id,
            p.pro_name,
            p.pro_price,
            p.pro_price_install,
            p.protype_id,
            pt.protype_name
        FROM product p
        LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
        WHERE p.pro_id LIKE ?
           OR p.pro_name LIKE ?
           OR p.protype_id LIKE ?
           OR pt.protype_name LIKE ?
        ORDER BY p.pro_id DESC
    ");

    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    $products = $conn->query("
        SELECT 
            p.pro_id,
            p.pro_name,
            p.pro_price,
            p.pro_price_install,
            p.protype_id,
            pt.protype_name
        FROM product p
        LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
        ORDER BY p.pro_id DESC
    ");
}

layout_header('จัดการสินค้า', 'products');
?>

<?= flash_message() ?>

<div class="panel">
  <div class="panel-title">รายการข้อมูลสินค้าทั้งหมด</div>

  <form class="toolbar product-toolbar" method="GET" action="<?= h(app_system_url('admin/products.php')) ?>">
    <input
      type="text"
      name="q"
      placeholder="ค้นหารหัสสินค้า ชื่อสินค้า หรือประเภทสินค้า"
      value="<?= h($search) ?>"
    >

    <button class="btn btn-search" type="submit">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="11" cy="11" r="7"></circle>
        <path d="M20 20l-3.5-3.5"></path>
      </svg>
      <span>ค้นหา</span>
    </button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/products.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
        <path d="M3 4v6h6"></path>
      </svg>
      <span>ล้างค้นหา</span>
    </a>

    <a class="btn btn-add product-add-in-toolbar" href="<?= h(app_system_url('admin/product_add.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 5v14"></path>
        <path d="M5 12h14"></path>
      </svg>
      <span>เพิ่มสินค้า</span>
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสสินค้า</th>
          <th>ชื่อสินค้า</th>
          <th>ประเภทสินค้า</th>
          <th>ราคาสินค้า</th>
          <th>ราคาค่าติดตั้ง</th>
          <th style="width:160px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($products->num_rows === 0): ?>
          <tr>
            <td colspan="6" class="empty-state">ไม่พบข้อมูลสินค้า</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $products->fetch_assoc()): ?>
          <tr>
            <td><?= h($row['pro_id']) ?></td>
            <td><?= h($row['pro_name']) ?></td>
            <td><?= h($row['protype_name'] ?: '-') ?></td>
            <td><?= h(number_format((float) $row['pro_price'], 2)) ?> บาท</td>
            <td><?= h(number_format((float) $row['pro_price_install'], 2)) ?> บาท</td>
            <td>
              <div class="table-action-buttons">
                <a
                  class="btn btn-edit"
                  href="<?= h(app_system_url('admin/product_edit.php?id=' . urlencode($row['pro_id']))) ?>"
                >
                  <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                  </svg>
                  <span>แก้ไข</span>
                </a>

                <a
                  class="btn btn-delete"
                  href="<?= h(app_system_url('admin/products.php?action=delete&id=' . urlencode($row['pro_id']))) ?>"
                  data-confirm-delete="ยืนยันการลบสินค้านี้หรือไม่?"
                >
                  <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M19 6l-1 14H6L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                  </svg>
                  <span>ลบ</span>
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>


<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/table_actions.css')) ?>?v=<?= h(asset_version('admin/assets/css/table_actions.css')) ?>">
<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>

<?php
layout_footer();
