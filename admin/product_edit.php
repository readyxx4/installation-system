<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function get_product_by_id(mysqli $conn, string $pro_id): ?array
{
  $stmt = $conn->prepare("SELECT * FROM product WHERE pro_id = ? LIMIT 1");
  $stmt->bind_param('s', $pro_id);
  $stmt->execute();

  $result = $stmt->get_result();

  return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

$pro_id = trim($_GET['id'] ?? $_POST['pro_id'] ?? '');

if ($pro_id === '') {
  redirect_to(app_system_url('admin/products.php?status=error'));
}

$product = get_product_by_id($conn, $pro_id);

if (!$product) {
  redirect_to(app_system_url('admin/products.php?status=error'));
}

$product_types = $conn->query("
    SELECT protype_id, protype_name
    FROM product_type
    ORDER BY protype_id ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pro_name = trim($_POST['pro_name'] ?? '');
  $pro_price = trim($_POST['pro_price'] ?? '');
  $pro_price_install = trim($_POST['pro_price_install'] ?? '');
  $protype_id = trim($_POST['protype_id'] ?? '');

  if (
    $pro_name === '' ||
    $pro_price === '' ||
    $pro_price_install === '' ||
    $protype_id === ''
  ) {
    redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=error'));
  }

  if (!is_numeric($pro_price) || (float) $pro_price < 0) {
    redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=error'));
  }

  if (!is_numeric($pro_price_install) || (float) $pro_price_install < 0) {
    redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=error'));
  }

  $pro_price = (float) $pro_price;
  $pro_price_install = (float) $pro_price_install;

  try {
    // ตรวจสอบว่าประเภทสินค้ามีจริง
    $type_check = $conn->prepare("
            SELECT protype_id
            FROM product_type
            WHERE protype_id = ?
            LIMIT 1
        ");
    $type_check->bind_param('s', $protype_id);
    $type_check->execute();
    $type_result = $type_check->get_result();

    if ($type_result->num_rows === 0) {
      redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=error'));
    }

    // ตรวจสอบชื่อสินค้าซ้ำ ยกเว้นข้อมูลของตัวเอง
    $check = $conn->prepare("
            SELECT pro_id, pro_name
            FROM product
            WHERE pro_id <> ?
              AND pro_name = ?
            LIMIT 1
        ");

    $check->bind_param(
      'ss',
      $pro_id,
      $pro_name
    );

    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
      redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=product_duplicate_name'));
    }

    $stmt = $conn->prepare("
            UPDATE product
            SET pro_name = ?,
                pro_price = ?,
                pro_price_install = ?,
                protype_id = ?
            WHERE pro_id = ?
        ");

    $stmt->bind_param(
      'sddss',
      $pro_name,
      $pro_price,
      $pro_price_install,
      $protype_id,
      $pro_id
    );

    $stmt->execute();

    redirect_to(app_system_url('admin/products.php?status=updated'));
  } catch (Throwable $e) {
    redirect_to(app_system_url('admin/product_edit.php?id=' . urlencode($pro_id) . '&status=error'));
  }
}


if (!function_exists('admin_form_icon_svg')) {
  function admin_form_icon_svg(string $name): string
  {
    $icons = [
      'id' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="M8 10h8"></path><path d="M8 14h5"></path></svg>',
      'user' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
      'mail' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M4 7l8 6 8-6"></path></svg>',
      'phone' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.5 2.1L8 9.5a16 16 0 0 0 6.5 6.5l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2z"></path></svg>',
      'role' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7l8-4z"></path><path d="M9 12l2 2 4-5"></path></svg>',
      'lock' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>',
      'map' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"></path><path d="M9 3v15"></path><path d="M15 6v15"></path></svg>',
      'box' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
      'money' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle><path d="M6 9v0"></path><path d="M18 15v0"></path></svg>',
      'text' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path></svg>',
      'image' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="M8 13l2.5-2.5L16 16"></path><circle cx="16" cy="8" r="1.5"></circle></svg>',
      'save' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>',
      'back' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>',
    ];
    return $icons[$name] ?? '';
  }
}

layout_header('แก้ไขสินค้า', 'products');
?>
<?= flash_message() ?>
<div class="staff-form-page">
  <div class="staff-page-back-row"><a class="staff-back-link"
      href="<?= h(app_system_url('admin/products.php')) ?>"><?= admin_form_icon_svg('back') ?> กลับรายการสินค้า</a>
  </div>
  <form class="staff-create-card" method="POST" action="<?= h(app_system_url('admin/product_edit.php')) ?>"
    autocomplete="off">
    <input type="hidden" name="pro_id" value="<?= h($product['pro_id']) ?>">
    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>แก้ไขข้อมูลสินค้า</h2>
      </div>
    </div>
    <div class="staff-form-grid">
      <div class="staff-field readonly-field"><label for="pro_id_show">รหัสสินค้า *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('id') ?><input type="text" id="pro_id_show"
            value="<?= h($product['pro_id']) ?>" readonly></div>
      </div>
      <div class="staff-field"><label for="protype_id">ประเภทสินค้า *</label>
        <div class="staff-input-wrap select-wrap"><?= admin_form_icon_svg('box') ?><select id="protype_id"
            name="protype_id" required>
            <option value="" disabled>เลือกประเภทสินค้า</option><?php while ($type = $product_types->fetch_assoc()): ?>
              <option value="<?= h($type['protype_id']) ?>" <?= $product['protype_id'] === $type['protype_id'] ? 'selected' : '' ?>><?= h($type['protype_name']) ?></option><?php endwhile; ?>
          </select></div>
      </div>
      <div class="staff-field staff-field-full"><label for="pro_name">ชื่อสินค้า *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('box') ?><input type="text" id="pro_name" name="pro_name"
            maxlength="50" value="<?= h($product['pro_name']) ?>" required></div>
      </div>
      <div class="staff-field"><label for="pro_price">ราคาสินค้า *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('money') ?><input type="number" id="pro_price"
            name="pro_price" min="0" step="0.01"
            value="<?= h(number_format((float) $product['pro_price'], 2, '.', '')) ?>" required></div>
      </div>
      <div class="staff-field"><label for="pro_price_install">ราคาค่าติดตั้ง *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('money') ?><input type="number" id="pro_price_install"
            name="pro_price_install" min="0" step="0.01"
            value="<?= h(number_format((float) $product['pro_price_install'], 2, '.', '')) ?>" required></div>
      </div>
    </div>
    <div class="staff-form-actions"><a class="staff-cancel-btn"
        href="<?= h(app_system_url('admin/products.php')) ?>">ยกเลิก</a><button class="staff-save-btn"
        type="submit"><?= admin_form_icon_svg('save') ?> บันทึกข้อมูล</button></div>
  </form>
</div>
<?php layout_footer(); ?>
