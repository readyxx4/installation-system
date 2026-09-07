<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../customer_profiles.php';

require_login('3');
ensure_customer_profiles_schema($conn);

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

  return $stmt->get_result()->num_rows > 0;
}

function make_user_id(mysqli $conn): string
{
  return make_customer_profile_id($conn);
}

function normalize_full_name(string $name): string
{
  return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
}

function is_valid_full_name(string $name): bool
{
  return (bool) preg_match('/^\S+(?:\s+\S+)+$/u', $name);
}

function icon_svg(string $name): string
{
  $icons = [
    'id' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="M8 10h8"></path><path d="M8 14h5"></path></svg>',
    'user' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    'mail' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M4 7l8 6 8-6"></path></svg>',
    'phone' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7"></path></svg>',
    'lock' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>',
    'map' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"></path><path d="M9 3v15"></path><path d="M15 6v15"></path></svg>',
    'save' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path></svg>',
    'back' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>',
  ];

  return $icons[$name] ?? '';
}


if (
  $_SERVER['REQUEST_METHOD'] === 'GET' &&
  ($_GET['ajax'] ?? '') === 'check_duplicate'
) {
  header('Content-Type: application/json; charset=utf-8');

  $field = trim($_GET['field'] ?? '');
  $value = trim($_GET['value'] ?? '');

  if ($value === '' || !in_array($field, ['user_phone', 'user_email'], true)) {
    echo json_encode([
      'duplicate' => false,
      'user_id' => '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($field === 'user_phone') {
    $stmt = $conn->prepare("
      SELECT customer_id AS user_id
      FROM customers
      WHERE customer_phone = ?
      LIMIT 1
    ");
    $stmt->bind_param('s', $value);
  } else {
    $stmt = $conn->prepare("
      SELECT customer_id AS user_id
      FROM customers
      WHERE LOWER(customer_email) = LOWER(?)
      LIMIT 1
    ");
    $stmt->bind_param('s', $value);
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
  $user_id = trim($_POST['user_id'] ?? '');
  $user_name = normalize_full_name($_POST['user_name'] ?? '');
  $user_phone = trim($_POST['user_phone'] ?? '');
  $user_email = trim($_POST['user_email'] ?? '');
  $address_detail = trim($_POST['address_detail'] ?? '');
  $province_id = (int) ($_POST['province_id'] ?? 0);
  $district_id = (int) ($_POST['district_id'] ?? 0);
  $sub_district_id = (int) ($_POST['sub_district_id'] ?? 0);
  $zip_code = trim($_POST['zip_code'] ?? '');

  if (
    $user_id === '' ||
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $address_detail === '' ||
    $province_id <= 0 ||
    $district_id <= 0 ||
    $sub_district_id <= 0 ||
    $zip_code === ''
  ) {
    redirect_to(app_system_url('admin/customer_add.php?status=error'));
  }

  if (!preg_match('/^CUS-2569-[0-9]{4}$/', $user_id)) {
    redirect_to(app_system_url('admin/customer_add.php?status=error'));
  }

  if (!is_valid_full_name($user_name)) {
    redirect_to(app_system_url('admin/customer_add.php?status=name_space'));
  }

  if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_system_url('admin/customer_add.php?status=phone'));
  }

  if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || strpos($user_email, '@') === false) {
    redirect_to(app_system_url('admin/customer_add.php?status=email'));
  }

  

  try {
    $transaction_started = false;

    $addr_stmt = $conn->prepare("
      SELECT
        p.name_th AS province_name,
        d.name_th AS district_name,
        sd.name_th AS sub_district_name,
        sd.zip_code
      FROM sub_districts sd
      INNER JOIN districts d ON sd.district_id = d.id
      INNER JOIN provinces p ON d.province_id = p.id
      WHERE sd.id = ?
        AND d.id = ?
        AND p.id = ?
      LIMIT 1
    ");
    $addr_stmt->bind_param('iii', $sub_district_id, $district_id, $province_id);
    $addr_stmt->execute();

    $addr_result = $addr_stmt->get_result();

    if ($addr_result->num_rows !== 1) {
      redirect_to(app_system_url('admin/customer_add.php?status=error'));
    }

    $addr = $addr_result->fetch_assoc();
    $zip_code = $addr['zip_code'] ?: $zip_code;

    $user_address =
      $address_detail .
      ' ต.' . $addr['sub_district_name'] .
      ' อ.' . $addr['district_name'] .
      ' จ.' . $addr['province_name'] .
      ' ' . $zip_code;

    $check_id = $conn->prepare("
      SELECT customer_id
      FROM customers
      WHERE customer_id = ?
      LIMIT 1
    ");
    $check_id->bind_param('s', $user_id);
    $check_id->execute();

    if ($check_id->get_result()->num_rows > 0) {
      redirect_to(app_system_url('admin/customer_add.php?status=duplicate_id'));
    }

    $check_phone = $conn->prepare("
      SELECT customer_id AS user_id
      FROM customers
      WHERE customer_phone = ?
      LIMIT 1
    ");
    $check_phone->bind_param('s', $user_phone);
    $check_phone->execute();
    $duplicate_phone = $check_phone->get_result()->fetch_assoc();

    if ($duplicate_phone) {
      redirect_to(
        app_system_url(
          'admin/customer_add.php?status=duplicate_phone' .
          '&duplicate_user=' . urlencode($duplicate_phone['user_id'])
        )
      );
    }

    $check_email = $conn->prepare("
      SELECT customer_id AS user_id
      FROM customers
      WHERE LOWER(customer_email) = LOWER(?)
      LIMIT 1
    ");
    $check_email->bind_param('s', $user_email);
    $check_email->execute();
    $duplicate_email = $check_email->get_result()->fetch_assoc();

    if ($duplicate_email) {
      redirect_to(
        app_system_url(
          'admin/customer_add.php?status=duplicate_email' .
          '&duplicate_user=' . urlencode($duplicate_email['user_id'])
        )
      );
    }

    $conn->begin_transaction();
    $transaction_started = true;

    $stmt = $conn->prepare("
      INSERT INTO customers
      (
        customer_id,
        customer_name,
        customer_phone,
        customer_email,
        customer_address,
        customer_status
      )
      VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param(
      'sssss',
      $user_id,
      $user_name,
      $user_phone,
      $user_email,
      $user_address
    );
    $stmt->execute();

    $conn->commit();

    redirect_to(app_system_url('admin/customers.php?status=created'));
  } catch (Throwable $e) {
    if (($transaction_started ?? false) === true) {
      $conn->rollback();
    }

    redirect_to(app_system_url('admin/customer_add.php?status=error'));
  }
}

$default_user_id = make_user_id($conn);

$provinces = [];

if (table_exists($conn, 'provinces')) {
  $province_result = $conn->query("
    SELECT id, name_th
    FROM provinces
    ORDER BY name_th ASC
  ");

  while ($province = $province_result->fetch_assoc()) {
    $provinces[] = $province;
  }
}

layout_header('เพิ่มข้อมูลลูกค้า', 'users');
?>

<?= flash_message() ?>
<link rel="stylesheet" href="<?= h(app_asset_url('admin/assets/css/staff_forms.css')) ?>?v=<?= h(asset_version('admin/assets/css/staff_forms.css')) ?>">

<div class="staff-form-page">
  <div class="staff-page-back-row">
    <a class="staff-back-link" href="<?= h(app_system_url('admin/customers.php')) ?>">
      <?= icon_svg('back') ?> กลับรายการพนักงาน
    </a>
  </div>

  <form
    class="staff-create-card"
    method="POST"
    action="<?= h(app_system_url('admin/customer_add.php')) ?>"
    autocomplete="off"
    id="userAddForm"
    data-duplicate-url="<?= h(app_system_url('admin/customer_add.php')) ?>"
    data-district-url="<?= h(app_system_url('ajax/get_districts.php')) ?>"
    data-sub-district-url="<?= h(app_system_url('ajax/get_subdistricts.php')) ?>"
  >
    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>ข้อมูลลูกค้าใหม่</h2>
      </div>
    </div>

    <div class="staff-form-grid">
      <div class="staff-field readonly-field">
        <label for="user_id">รหัสผู้ใช้ *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('id') ?>
          <input
            type="text"
            id="user_id"
            name="user_id"
            maxlength="13"
            value="<?= h($default_user_id) ?>"
            readonly
            required
          >
        </div>
      </div>


      <div class="staff-field staff-field-full">
        <label for="user_name">ชื่อ-นามสกุล *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('user') ?>
          <input
            type="text"
            id="user_name"
            name="user_name"
            maxlength="100"
            placeholder="เช่น สมชาย ใจดี"
            required
          >
        </div>
      </div>

      <div class="staff-field">
        <label for="user_phone">เบอร์โทรศัพท์ *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('phone') ?>
          <input
            type="text"
            id="user_phone"
            name="user_phone"
            maxlength="10"
            inputmode="numeric"
            placeholder="0812345678"
            required
          >
        </div>
        <div class="field-live-error" id="phoneDuplicateError" aria-live="polite"></div>
      </div>

      <div class="staff-field">
        <label for="user_email">อีเมล *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('mail') ?>
          <input
            type="email"
            id="user_email"
            name="user_email"
            maxlength="100"
            placeholder="example@gmail.com หรือ example@domain.com"
            required
          >
        </div>
        <div class="field-live-error" id="emailDuplicateError" aria-live="polite"></div>
      </div>

      <div class="staff-field staff-field-full staff-address-section">
        <div class="staff-address-title">
          <?= icon_svg('map') ?>
          <label>ข้อมูลที่อยู่ *</label>
        </div>

        <div class="staff-address-grid">
          <div class="staff-field staff-address-detail">
            <label for="address_detail">บ้านเลขที่ / หมู่ / ถนน *</label>
            <div class="staff-input-wrap">
              <?= icon_svg('map') ?>
              <input
                type="text"
                id="address_detail"
                name="address_detail"
                maxlength="255"
                placeholder="เช่น 150 หมู่ 1 ถนนศรีจันทร์"
                required
              >
            </div>
          </div>

          <div class="staff-field">
            <label for="province_id">จังหวัด *</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= icon_svg('map') ?>
              <select
                id="province_id"
                name="province_id"
                class="js-address-select"
                required
              >
                <option value="" selected disabled>เลือกจังหวัด</option>
                <?php foreach ($provinces as $province): ?>
                  <option value="<?= h($province['id']) ?>">
                    <?= h($province['name_th']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="district_id">อำเภอ *</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= icon_svg('map') ?>
              <select
                id="district_id"
                name="district_id"
                class="js-address-select"
                required
                disabled
              >
                <option value="" selected>เลือกจังหวัดก่อน</option>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="sub_district_id">ตำบล *</label>
            <div class="staff-input-wrap address-select-wrap">
              <?= icon_svg('map') ?>
              <select
                id="sub_district_id"
                name="sub_district_id"
                class="js-address-select"
                required
                disabled
              >
                <option value="" selected>เลือกอำเภอก่อน</option>
              </select>
            </div>
          </div>

          <div class="staff-field">
            <label for="zip_code">รหัสไปรษณีย์ *</label>
            <div class="staff-input-wrap readonly-field">
              <?= icon_svg('id') ?>
              <input
                type="text"
                id="zip_code"
                name="zip_code"
                maxlength="10"
                placeholder="ระบบเติมให้อัตโนมัติ"
                readonly
                required
              >
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="staff-form-actions">
      <a class="staff-cancel-btn" href="<?= h(app_system_url('admin/customers.php')) ?>">
        ยกเลิก
      </a>

      <button class="staff-save-btn" type="submit">
        <?= icon_svg('save') ?> เพิ่มข้อมูลลูกค้า
      </button>
    </div>
  </form>
</div>
<script src="<?= h(app_asset_url('admin/assets/js/user_form.js')) ?>?v=<?= h(asset_version('admin/assets/js/user_form.js')) ?>"></script>

<?php layout_footer(); ?>
