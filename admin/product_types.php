<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

function product_type_has_product(mysqli $conn, string $protype_id): bool
{
    $check_product = $conn->prepare("
        SELECT pro_id
        FROM product
        WHERE protype_id = ?
        LIMIT 1
    ");
    $check_product->bind_param('s', $protype_id);
    $check_product->execute();

    return $check_product->get_result()->num_rows > 0;
}

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/product_types.php?status=error'));
    }

    try {
        if (product_type_has_product($conn, $delete_id)) {
            redirect_to(app_system_url('admin/product_types.php?status=product_type_linked'));
        }

        $stmt = $conn->prepare("DELETE FROM product_type WHERE protype_id = ?");
        $stmt->bind_param('s', $delete_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/product_types.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/product_types.php?status=error'));
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT protype_id, protype_name, protype_detail
        FROM product_type
        WHERE protype_id LIKE ?
           OR protype_name LIKE ?
           OR protype_detail LIKE ?
        ORDER BY protype_id DESC
    ");

    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $product_types = $stmt->get_result();
} else {
    $product_types = $conn->query("
        SELECT protype_id, protype_name, protype_detail
        FROM product_type
        ORDER BY protype_id DESC
    ");
}

layout_header('จัดการประเภทสินค้า', 'product_types');
?>

<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/admin_lists.css')) ?>?v=<?= h(asset_version('admin/assets/css/admin_lists.css')) ?>">

<?= flash_message() ?>

<div class="panel admin-list-page admin-product-types-page">
  <div class="panel-title">รายการข้อมูลประเภทสินค้าทั้งหมด</div>

  <form class="toolbar product-type-toolbar" method="GET" action="<?= h(app_system_url('admin/product_types.php')) ?>">
    <input
      type="text"
      name="q"
      placeholder="ค้นหารหัสประเภทสินค้า ชื่อประเภทสินค้า หรือรายละเอียด"
      value="<?= h($search) ?>"
    >

    <button class="btn btn-search" type="submit">
      <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="11" cy="11" r="7"></circle>
        <path d="M20 20l-3.5-3.5"></path>
      </svg>
      <span>ค้นหา</span>
    </button>

    <a class="btn btn-reset" href="<?= h(app_system_url('admin/product_types.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
        <path d="M3 4v6h6"></path>
      </svg>
      <span>ล้างค้นหา</span>
    </a>

    <a class="btn btn-add product-type-add-in-toolbar" href="<?= h(app_system_url('admin/product_type_add.php')) ?>">
      <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 5v14"></path>
        <path d="M5 12h14"></path>
      </svg>
      <span>เพิ่มประเภทสินค้า</span>
    </a>
  </form>

  <div class="table-wrap">
    <table class="data-table product-type-table">
      <thead>
        <tr>
          <th>รหัสประเภทสินค้า</th>
          <th>ชื่อประเภทสินค้า</th>
          <th>รายละเอียด</th>
          <th style="width:190px;">จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($product_types->num_rows === 0): ?>
          <tr>
            <td colspan="4" class="empty-state">ไม่พบข้อมูลประเภทสินค้า</td>
          </tr>
        <?php endif; ?>

        <?php while ($row = $product_types->fetch_assoc()): ?>
          <?php $has_product = product_type_has_product($conn, $row['protype_id']); ?>
          <tr>
            <td><?= h($row['protype_id']) ?></td>
            <td><?= h($row['protype_name']) ?></td>
            <td class="address-cell product-type-detail-cell">
              <?php
                $detail = (string) $row['protype_detail'];
                $is_long = mb_strlen($detail, 'UTF-8') > 25;

                $short_detail = mb_strlen($detail, 'UTF-8') > 25
                  ? mb_substr($detail, 0, 25, 'UTF-8') . '...'
                  : $detail;

              ?>

              <?php if ($is_long): ?>
                <span class="address-short">
                  <?= h($short_detail) ?>
                </span>

                <span class="address-full product-type-detail-full" style="display:none;">
                  <?= nl2br(h($detail)) ?>
                </span>

                <button type="button" class="text-more-btn" data-toggle-address>
                  ดูเพิ่มเติม
                </button>
              <?php else: ?>
                <?= h($detail) ?>
              <?php endif; ?>
            </td>

            <td>
              <div class="table-action-buttons">
                <a
                  class="btn btn-edit"
                  href="<?= h(app_system_url('admin/product_type_edit.php?id=' . urlencode($row['protype_id']))) ?>"
                >
                  <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                  </svg>
                  <span>แก้ไข</span>
                </a>

                <?php if ($has_product): ?>
                  <span
                    class="btn btn-delete disabled-link"
                    aria-disabled="true"
                    title="ไม่สามารถลบได้ เนื่องจากมีสินค้าอยู่ในประเภทนี้"
                  >
                    <svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M3 6h18"></path>
                      <path d="M8 6V4h8v2"></path>
                      <path d="M19 6l-1 14H6L5 6"></path>
                      <path d="M10 11v6"></path>
                      <path d="M14 11v6"></path>
                    </svg>
                    <span>ลบ</span>
                  </span>
                <?php else: ?>
                  <a
                    class="btn btn-delete"
                    href="<?= h(app_system_url('admin/product_types.php?action=delete&id=' . urlencode($row['protype_id']))) ?>"
                    data-confirm-delete="ยืนยันการลบประเภทสินค้านี้หรือไม่?"
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
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/table_actions.css')) ?>?v=<?= h(asset_version('admin/assets/css/table_actions.css')) ?>">

<script src="<?= h(app_asset_url('admin/assets/js/toggle_address.js')) ?>?v=<?= h(asset_version('admin/assets/js/toggle_address.js')) ?>"></script>
<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>

<?php
layout_footer();
