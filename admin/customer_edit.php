<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function get_user_by_id(mysqli $conn, string $user_id): ?array
{
  $stmt = $conn->prepare("
    SELECT user_id, user_name, user_password, user_phone, user_email, user_address, user_role
    FROM `user`
    WHERE user_id = ?
      AND user_role = 0
    LIMIT 1
  ");
  $stmt->bind_param('s', $user_id);
  $stmt->execute();

  $result = $stmt->get_result();
  return $result->num_rows === 1 ? $result->fetch_assoc() : null;
}

function normalize_full_name(string $name): string
{
  return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
}

function is_valid_full_name(string $name): bool
{
  // ต้องมีอย่างน้อย 2 ส่วน และคั่นด้วยช่องว่าง เช่น สมชาย ใจดี
  return (bool) preg_match('/^\S+(?:\s+\S+)+$/u', $name);
}

$user_id = trim($_GET['id'] ?? $_POST['user_id'] ?? '');

if ($user_id === '') {
  redirect_to(app_system_url('admin/customers.php?status=error'));
}

$user = get_user_by_id($conn, $user_id);

if (!$user) {
  redirect_to(app_system_url('admin/customers.php?status=error'));
}


if (
  $_SERVER['REQUEST_METHOD'] === 'GET' &&
  ($_GET['ajax'] ?? '') === 'check_duplicate'
) {
  header('Content-Type: application/json; charset=utf-8');

  $field = trim($_GET['field'] ?? '');
  $value = trim($_GET['value'] ?? '');
  $exclude_user_id = trim($_GET['exclude_user_id'] ?? '');

  if (
    $value === '' ||
    $exclude_user_id === '' ||
    !in_array($field, ['user_phone', 'user_email'], true)
  ) {
    echo json_encode([
      'duplicate' => false,
      'user_id' => '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($field === 'user_phone') {
    $stmt = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE user_phone = ?
        AND user_id <> ?
      LIMIT 1
    ");
    $stmt->bind_param('ss', $value, $exclude_user_id);
  } else {
    $stmt = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE LOWER(user_email) = LOWER(?)
        AND user_id <> ?
      LIMIT 1
    ");
    $stmt->bind_param('ss', $value, $exclude_user_id);
  }

  $stmt->execute();
  $duplicate = $stmt->get_result()->fetch_assoc();

  echo json_encode([
    'duplicate' => (bool) $duplicate,
    'user_id' => $duplicate['user_id'] ?? '',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user_name = normalize_full_name($_POST['user_name'] ?? '');
  $user_password = trim($_POST['user_password'] ?? '');
  $change_password = ($_POST['change_password'] ?? '') === '1';
  $user_phone = trim($_POST['user_phone'] ?? '');
  $user_email = trim($_POST['user_email'] ?? '');
  $user_address = trim($_POST['user_address'] ?? '');
  $user_role = 0;

  if (
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $user_address === '' 
  ) {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }

  if (!is_valid_full_name($user_name)) {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=name_space'));
  }

  if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=phone'));
  }

  // รับอีเมลทุกโดเมน แต่ต้องเป็นรูปแบบอีเมลที่ถูกต้องและมี @
  if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || strpos($user_email, '@') === false) {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=email'));
  }

  
  if ($change_password && $user_password === '') {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }

  $role_int = 0;

  try {
    // ชื่อ-นามสกุลสามารถซ้ำได้
    // ตรวจเฉพาะเบอร์โทรศัพท์และอีเมล โดยแยกทีละช่องเพื่อแจ้งเตือนให้ตรงสาเหตุ

    $check_phone = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE user_phone = ?
        AND user_id <> ?
      LIMIT 1
    ");
    $check_phone->bind_param('ss', $user_phone, $user_id);
    $check_phone->execute();
    $duplicate_phone = $check_phone->get_result()->fetch_assoc();

    if ($duplicate_phone) {
      redirect_to(
        app_system_url(
          'admin/customer_edit.php?id=' . urlencode($user_id) .
          '&status=duplicate_phone' .
          '&duplicate_user=' . urlencode($duplicate_phone['user_id'])
        )
      );
    }

    $check_email = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE LOWER(user_email) = LOWER(?)
        AND user_id <> ?
      LIMIT 1
    ");
    $check_email->bind_param('ss', $user_email, $user_id);
    $check_email->execute();
    $duplicate_email = $check_email->get_result()->fetch_assoc();

    if ($duplicate_email) {
      redirect_to(
        app_system_url(
          'admin/customer_edit.php?id=' . urlencode($user_id) .
          '&status=duplicate_email' .
          '&duplicate_user=' . urlencode($duplicate_email['user_id'])
        )
      );
    }

    if ($change_password) {
      $stmt = $conn->prepare("
        UPDATE `user`
        SET user_name = ?,
            user_password = ?,
            user_phone = ?,
            user_email = ?,
            user_address = ?,
            user_role = ?
        WHERE user_id = ?
      ");
      $stmt->bind_param(
        'sssssis',
        $user_name,
        $user_password,
        $user_phone,
        $user_email,
        $user_address,
        $role_int,
        $user_id
      );
    } else {
      $stmt = $conn->prepare("
        UPDATE `user`
        SET user_name = ?,
            user_phone = ?,
            user_email = ?,
            user_address = ?,
            user_role = ?
        WHERE user_id = ?
      ");
      $stmt->bind_param(
        'ssssis',
        $user_name,
        $user_phone,
        $user_email,
        $user_address,
        $role_int,
        $user_id
      );
    }

    $stmt->execute();
    redirect_to(app_system_url('admin/customers.php?status=updated'));
  } catch (Throwable $e) {
    redirect_to(app_system_url('admin/customer_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }
}

if (!function_exists('admin_form_icon_svg')) {
  function admin_form_icon_svg(string $name): string
  {
    $icons = [
      'id' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="M8 10h8"></path><path d="M8 14h5"></path></svg>',
      'user' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
      'mail' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M4 7l8 6 8-6"></path></svg>',
      'phone' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7"></path></svg>',
      'role' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7l8-4z"></path><path d="M9 12l2 2 4-5"></path></svg>',
      'lock' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>',
      'map' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"></path><path d="M9 3v15"></path><path d="M15 6v15"></path></svg>',
      'save' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path></svg>',
      'back' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>',
    ];

    return $icons[$name] ?? '';
  }
}

$provinces = [];
try {
  $province_result = $conn->query("SELECT id, name_th FROM provinces ORDER BY name_th ASC");
  while ($province = $province_result->fetch_assoc()) {
    $provinces[] = $province;
  }
} catch (Throwable $e) {
  $provinces = [];
}


$address_detail_value = (string) ($user['user_address'] ?? '');
$selected_province_name = '';
$selected_district_name = '';
$selected_subdistrict_name = '';
$selected_zip_code = '';

if (preg_match(
  '/^(.*?)\s+ต\.?\s*(.*?)\s+อ\.?\s*(.*?)\s+จ\.?\s*(.*?)\s+(\d{5})$/u',
  trim($address_detail_value),
  $address_matches
)) {
  $address_detail_value = trim($address_matches[1]);
  $selected_subdistrict_name = trim($address_matches[2]);
  $selected_district_name = trim($address_matches[3]);
  $selected_province_name = trim($address_matches[4]);
  $selected_zip_code = trim($address_matches[5]);
}

layout_header('แก้ไขข้อมูลลูกค้า', 'users');
?>

<?= flash_message() ?>
<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/staff_forms.css')) ?>?v=<?= h(asset_version('admin/assets/css/staff_forms.css')) ?>">

<div class="staff-form-page">
  <div class="staff-page-back-row">
    <a class="staff-back-link" href="<?= h(app_system_url('admin/customers.php')) ?>">
      <?= admin_form_icon_svg('back') ?> กลับรายการลูกค้า
    </a>
  </div>

  <form
    class="staff-create-card"
    method="POST"
    action="<?= h(app_system_url('admin/customer_edit.php')) ?>"
    autocomplete="off"
    id="userEditForm"
    data-duplicate-url="<?= h(app_system_url('admin/customer_edit.php')) ?>"
    data-current-user-id="<?= h($user['user_id']) ?>"
    data-original-password="<?= h($user['user_password'] ?: '1234') ?>"
    data-district-url="<?= h(app_system_url('ajax/get_districts.php')) ?>"
    data-sub-district-url="<?= h(app_system_url('ajax/get_subdistricts.php')) ?>"
    data-selected-province-name="<?= h($selected_province_name) ?>"
    data-selected-district-name="<?= h($selected_district_name) ?>"
    data-selected-subdistrict-name="<?= h($selected_subdistrict_name) ?>"
    data-selected-zip-code="<?= h($selected_zip_code) ?>"
  >
    <input type="hidden" name="user_id" value="<?= h($user['user_id']) ?>">
    <input type="hidden" name="change_password" id="change_password" value="0">

    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>แก้ไขข้อมูลลูกค้า</h2>
      </div>
    </div>

    <div class="staff-form-grid">
      <div class="staff-field readonly-field">
        <label for="user_id_show">รหัสผู้ใช้ *</label>
        <div class="staff-input-wrap">
          <?= admin_form_icon_svg('id') ?>
          <input type="text" id="user_id_show" value="<?= h($user['user_id']) ?>" readonly>
        </div>
      </div>

      <div class="staff-field readonly-field">
        <label>สิทธิ์การใช้งาน</label>
        <div class="staff-input-wrap">
          <?= admin_form_icon_svg('role') ?>
          <input type="text" value="ลูกค้า" readonly>
        </div>
      </div>

      <div class="staff-field staff-field-full">
        <label for="user_name">ชื่อ-นามสกุล *</label>
        <div class="staff-input-wrap">
          <?= admin_form_icon_svg('user') ?>
          <input
            type="text"
            id="user_name"
            name="user_name"
            maxlength="100"
            value="<?= h($user['user_name']) ?>"
            placeholder="เช่น สมชาย ใจดี"
            required
          >
        </div>
      </div>

      <div class="staff-field">
        <label for="user_phone">เบอร์โทรศัพท์ *</label>
        <div class="staff-input-wrap">
          <?= admin_form_icon_svg('phone') ?>
          <input
            type="text"
            id="user_phone"
            name="user_phone"
            maxlength="10"
            inputmode="numeric"
            value="<?= h($user['user_phone']) ?>"
            required
          >
        </div>
        <div class="field-live-error" id="phoneDuplicateError" aria-live="polite"></div>
      </div>

      <div class="staff-field">
        <label for="user_email">อีเมล *</label>
        <div class="staff-input-wrap">
          <?= admin_form_icon_svg('mail') ?>
          <input
            type="email"
            id="user_email"
            name="user_email"
            maxlength="100"
            value="<?= h($user['user_email']) ?>"
            placeholder="example@gmail.com หรือ example@domain.com"
            required
          >
        </div>
        <div class="field-live-error" id="emailDuplicateError" aria-live="polite"></div>
      </div>

      <div class="staff-field staff-field-full">
        <label for="user_password">รหัสผ่าน</label>

        <div class="password-inline-row">
          <div class="staff-input-wrap password-input-wrap">
            <?= admin_form_icon_svg('lock') ?>
            <input
              type="text"
              id="user_password"
              name="user_password"
              maxlength="50"
              value="<?= h($user['user_password'] ?: '1234') ?>"
              readonly
            >
          </div>

          <button type="button" class="password-change-trigger password-change-trigger-small" id="passwordChangeTrigger">
            แก้รหัสผ่าน
          </button>
        </div>
      </div>

      <input type="hidden" id="user_address" name="user_address" value="<?= h($user['user_address']) ?>">

      <div class="staff-field staff-field-full staff-address-section">
        <div class="staff-address-title">
          <?= admin_form_icon_svg('map') ?>
          <label>ข้อมูลที่อยู่ *</label>
        </div>

        <div class="staff-address-grid">
          <div class="staff-field staff-address-detail">
            <label for="address_detail">บ้านเลขที่ / หมู่ / ถนน *</label>
            <div class="staff-input-wrap">
              <?= admin_form_icon_svg('map') ?>
              <input
                type="text"
                id="address_detail"
                name="address_detail"
                maxlength="255"
                value="<?= h($address_detail_value) ?>"
                required
              >
            </div>
          </div>

          <div class="staff-field">
            <label for="province_id">จังหวัด</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= admin_form_icon_svg('map') ?>
              <select id="province_id" name="province_id">
                <option value="">เลือกจังหวัด</option>
                <?php foreach ($provinces as $province): ?>
                  <option
                    value="<?= h($province['id']) ?>"
                    <?= $province['name_th'] === $selected_province_name ? 'selected' : '' ?>
                  >
                    <?= h($province['name_th']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="district_id">อำเภอ</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= admin_form_icon_svg('map') ?>
              <select id="district_id" name="district_id" disabled>
                <option value="">เลือกจังหวัดก่อน</option>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="sub_district_id">ตำบล</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= admin_form_icon_svg('map') ?>
              <select id="sub_district_id" name="sub_district_id" disabled>
                <option value="">เลือกอำเภอก่อน</option>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="zip_code">รหัสไปรษณีย์</label>
            <div class="staff-input-wrap readonly-field">
              <?= admin_form_icon_svg('id') ?>
              <input type="text" id="zip_code" name="zip_code" value="<?= h($selected_zip_code) ?>" readonly>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="staff-form-actions">
      <a class="staff-cancel-btn" href="<?= h(app_system_url('admin/customers.php')) ?>">ยกเลิก</a>
      <button class="staff-save-btn" type="submit">
        <?= admin_form_icon_svg('save') ?> บันทึกข้อมูล
      </button>
    </div>
  </form>
</div>
<script src="<?= h(app_asset_url('admin/assets/js/user_form.js')) ?>?v=<?= h(asset_version('admin/assets/js/user_form.js')) ?>"></script>

<?php layout_footer(); ?>
