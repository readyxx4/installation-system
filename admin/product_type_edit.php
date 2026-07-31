<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function get_product_type_by_id(mysqli $conn, string $protype_id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM product_type WHERE protype_id = ? LIMIT 1");
    $stmt->bind_param('s', $protype_id);
    $stmt->execute();

    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

$protype_id = trim($_GET['id'] ?? $_POST['protype_id'] ?? '');

if ($protype_id === '') {
    redirect_to(app_system_url('admin/product_types.php?status=error'));
}

$product_type = get_product_type_by_id($conn, $protype_id);

if (!$product_type) {
    redirect_to(app_system_url('admin/product_types.php?status=error'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $protype_name = trim($_POST['protype_name'] ?? '');
    $protype_detail = trim($_POST['protype_detail'] ?? '');

    if ($protype_name === '' || $protype_detail === '') {
        redirect_to(app_system_url('admin/product_type_edit.php?id=' . urlencode($protype_id) . '&status=error'));
    }

    try {
        // ตรวจสอบชื่อประเภทสินค้าซ้ำ ยกเว้นข้อมูลของตัวเอง
        $check = $conn->prepare("
            SELECT protype_id, protype_name
            FROM product_type
            WHERE protype_id <> ?
              AND protype_name = ?
            LIMIT 1
        ");

        $check->bind_param(
            'ss',
            $protype_id,
            $protype_name
        );

        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            redirect_to(app_system_url('admin/product_type_edit.php?id=' . urlencode($protype_id) . '&status=duplicate_name'));
        }

        $stmt = $conn->prepare("
            UPDATE product_type
            SET protype_name = ?,
                protype_detail = ?
            WHERE protype_id = ?
        ");

        $stmt->bind_param(
            'sss',
            $protype_name,
            $protype_detail,
            $protype_id
        );

        $stmt->execute();

        redirect_to(app_system_url('admin/product_types.php?status=updated'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/product_type_edit.php?id=' . urlencode($protype_id) . '&status=error'));
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

layout_header('แก้ไขประเภทสินค้า', 'product_types');
?>
<?= flash_message() ?>
<div class="staff-form-page">
  <div class="staff-page-back-row"><a class="staff-back-link" href="<?= h(app_system_url('admin/product_types.php')) ?>"><?= admin_form_icon_svg('back') ?> กลับรายการประเภทสินค้า</a></div>
  <form class="staff-create-card" method="POST" action="<?= h(app_system_url('admin/product_type_edit.php')) ?>" autocomplete="off">
    <input type="hidden" name="protype_id" value="<?= h($product_type['protype_id']) ?>">
    <div class="staff-create-head staff-create-head-clean"><div><h2>แก้ไขประเภทสินค้า</h2></div></div>
    <div class="staff-form-grid">
      <div class="staff-field readonly-field"><label for="protype_id_show">รหัสประเภทสินค้า *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('id') ?><input type="text" id="protype_id_show" value="<?= h($product_type['protype_id']) ?>" readonly></div></div>
      <div class="staff-field"><label for="protype_name">ชื่อประเภทสินค้า *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('box') ?><input type="text" id="protype_name" name="protype_name" maxlength="50" value="<?= h($product_type['protype_name']) ?>" required></div></div>
      <div class="staff-field staff-field-full"><label for="protype_detail">รายละเอียดประเภทสินค้า *</label><div class="staff-textarea-wrap"><?= admin_form_icon_svg('text') ?><textarea id="protype_detail" name="protype_detail" maxlength="255" required><?= h($product_type['protype_detail']) ?></textarea></div></div>
    </div>
    <div class="staff-form-actions"><a class="staff-cancel-btn" href="<?= h(app_system_url('admin/product_types.php')) ?>">ยกเลิก</a><button class="staff-save-btn" type="submit"><?= admin_form_icon_svg('save') ?> บันทึกข้อมูล</button></div>
  </form>
</div>
<?php layout_footer(); ?>