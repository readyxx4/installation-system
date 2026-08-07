<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function table_exists(mysqli $conn, string $table): bool
{
  $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");

  $stmt->bind_param('s', $table);
  $stmt->execute();
  $result = $stmt->get_result();

  return $result->num_rows > 0;
}

function get_system_table(mysqli $conn): string
{
  if (table_exists($conn, 'system')) {
    return 'system';
  }

  $conn->query("
        CREATE TABLE `system` (
            `system_name` VARCHAR(255) NOT NULL,
            `system_logo` VARCHAR(255),
            `system_desc` VARCHAR(255) NOT NULL
        )
    ");

  return 'system';
}

function system_logo_url(string $logo): string
{
  $logo = trim($logo);

  if ($logo === '') {
    return '';
  }

  if (preg_match('/^https?:\/\//i', $logo)) {
    return $logo;
  }

  return app_public_url($logo);
}

$table = get_system_table($conn);

$result = $conn->query("
    SELECT system_name, system_logo, system_desc
    FROM `$table`
    LIMIT 1
");

if ($result->num_rows > 0) {
  $system = $result->fetch_assoc();
} else {
  $system = [
    'system_name' => 'ห้างโอวเปงฮง จำกัด',
    'system_logo' => '',
    'system_desc' => 'Installation System',
  ];

  $stmt = $conn->prepare("
        INSERT INTO `$table`
        (system_name, system_logo, system_desc)
        VALUES (?, ?, ?)
    ");

  $stmt->bind_param(
    'sss',
    $system['system_name'],
    $system['system_logo'],
    $system['system_desc']
  );

  $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $system_name = trim($_POST['system_name'] ?? '');
  $system_desc = trim($_POST['system_desc'] ?? '');
  $old_logo = trim($_POST['old_logo'] ?? '');
  $remove_logo = $_POST['remove_logo'] ?? '';

  $system_logo = $old_logo;

  if ($system_name === '' || $system_desc === '') {
    redirect_to(app_system_url('admin/system.php?status=error'));
  }

  try {
    if ($remove_logo === '1') {
      if ($old_logo !== '' && !preg_match('/^https?:\/\//i', $old_logo)) {
        $old_file = __DIR__ . '/../' . $old_logo;

        if (file_exists($old_file)) {
          unlink($old_file);
        }
      }

      $system_logo = '';
    }

    if (!empty($_FILES['system_logo']['name'])) {
      $file = $_FILES['system_logo'];

      if ($file['error'] !== UPLOAD_ERR_OK) {
        redirect_to(app_system_url('admin/system.php?status=error'));
      }

      $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

      if (!in_array($ext, $allowed_ext, true)) {
        redirect_to(app_system_url('admin/system.php?status=error'));
      }

      $upload_dir = __DIR__ . '/../uploads/system';

      if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
      }

      $new_name = 'system_logo_' . date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;
      $target = $upload_dir . '/' . $new_name;

      if (!move_uploaded_file($file['tmp_name'], $target)) {
        redirect_to(app_system_url('admin/system.php?status=error'));
      }

      if ($old_logo !== '' && !preg_match('/^https?:\/\//i', $old_logo)) {
        $old_file = __DIR__ . '/../' . $old_logo;

        if (file_exists($old_file)) {
          unlink($old_file);
        }
      }

      $system_logo = 'uploads/system/' . $new_name;
    }

    $stmt = $conn->prepare("
            UPDATE `$table`
            SET system_name = ?,
                system_logo = ?,
                system_desc = ?
            LIMIT 1
        ");

    $stmt->bind_param(
      'sss',
      $system_name,
      $system_logo,
      $system_desc
    );

    $stmt->execute();

    redirect_to(app_system_url('admin/system.php?status=updated'));
  } catch (Throwable $e) {
    redirect_to(app_system_url('admin/system.php?status=error'));
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

layout_header('จัดการข้อมูลระบบ', 'system');
?>
<?= flash_message() ?>
<div class="staff-form-page">
  <div class="staff-page-back-row"><a class="staff-back-link" href="<?= h(app_system_url('admin/index.php')) ?>"><?= admin_form_icon_svg('back') ?> กลับหน้าหลัก</a></div>
  <form class="staff-create-card" method="POST" action="<?= h(app_system_url('admin/system.php')) ?>" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="old_logo" value="<?= h($system['system_logo']) ?>">
    <div class="staff-create-head staff-create-head-clean"><div><h2>แก้ไขข้อมูลระบบ</h2></div></div>
    <div class="staff-form-grid">
      <div class="staff-field"><label for="system_name">ชื่อระบบ *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('text') ?><input type="text" id="system_name" name="system_name" maxlength="255" value="<?= h($system['system_name']) ?>" required></div></div>
      <div class="staff-field"><label for="system_logo">รูปภาพ / โลโก้ระบบ</label><div class="staff-input-wrap file-input-wrap"><?= admin_form_icon_svg('image') ?><input type="file" id="system_logo" name="system_logo" accept="image/*"></div></div>
      <div class="staff-field staff-field-full"><label for="system_desc">รายละเอียดระบบ *</label><div class="staff-textarea-wrap"><?= admin_form_icon_svg('text') ?><textarea id="system_desc" name="system_desc" maxlength="255" required><?= h($system['system_desc']) ?></textarea></div></div>
      <div class="staff-field staff-field-full"><div class="system-logo-preview"><p id="logoPreviewTitle"><?= !empty($system['system_logo']) ? 'รูปภาพปัจจุบัน' : 'ตัวอย่างรูปภาพ' ?></p><div class="system-logo-image-box"><img id="systemLogoPreview" src="<?= !empty($system['system_logo']) ? h(system_logo_url($system['system_logo'])) : '' ?>" alt="โลโก้ระบบ" style="<?= empty($system['system_logo']) ? 'display:none;' : '' ?>"><?php if (!empty($system['system_logo'])): ?><button type="button" class="logo-remove-x" title="ลบรูปภาพ">×</button><?php endif; ?></div><span id="systemLogoFileName"><?= !empty($system['system_logo']) ? h($system['system_logo']) : 'ยังไม่มีรูปภาพ' ?></span><input type="hidden" id="remove_logo" name="remove_logo" value="0"></div></div>
    </div>
    <div class="staff-form-actions"><a class="staff-cancel-btn" href="<?= h(app_system_url('admin/index.php')) ?>">ยกเลิก</a><button class="staff-save-btn" type="submit"><?= admin_form_icon_svg('save') ?> บันทึกข้อมูล</button></div>
  </form>
</div>
<script src="<?= h(app_asset_url('admin/assets/js/system_logo.js')) ?>?v=<?= h(asset_version('admin/assets/js/system_logo.js')) ?>"></script>
<?php layout_footer(); ?>
