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

  return $stmt->get_result()->num_rows > 0;
}

function make_user_id(mysqli $conn): string
{
  $prefix = 'USR-2569-';

  for ($i = 1; $i <= 9999; $i++) {
    $running_no = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
    $user_id = $prefix . $running_no;

    $stmt = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE user_id = ?
      LIMIT 1
    ");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
      return $user_id;
    }
  }

  throw new Exception('ไม่สามารถสร้างรหัสผู้ใช้ใหม่ได้');
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
    'role' => '<svg class="form-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7l8-4z"></path><path d="M9 12l2 2 4-5"></path></svg>',
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
      SELECT user_id
      FROM `user`
      WHERE user_phone = ?
      LIMIT 1
    ");
    $stmt->bind_param('s', $value);
  } else {
    $stmt = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE LOWER(user_email) = LOWER(?)
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
  $user_role = trim($_POST['user_role'] ?? '');
  $user_password = '1234';

  if (
    $user_id === '' ||
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $address_detail === '' ||
    $province_id <= 0 ||
    $district_id <= 0 ||
    $sub_district_id <= 0 ||
    $zip_code === '' ||
    $user_role === ''
  ) {
    redirect_to(app_system_url('admin/user_add.php?status=error'));
  }

  if (!preg_match('/^USR-2569-[0-9]{4}$/', $user_id)) {
    redirect_to(app_system_url('admin/user_add.php?status=error'));
  }

  if (!is_valid_full_name($user_name)) {
    redirect_to(app_system_url('admin/user_add.php?status=name_space'));
  }

  if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_system_url('admin/user_add.php?status=phone'));
  }

  if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || strpos($user_email, '@') === false) {
    redirect_to(app_system_url('admin/user_add.php?status=email'));
  }

  if (!in_array($user_role, ['1', '2', '3'], true)) {
    redirect_to(app_system_url('admin/user_add.php?status=role'));
  }

  $user_role = (int) $user_role;

  try {
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
      redirect_to(app_system_url('admin/user_add.php?status=error'));
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
      SELECT user_id
      FROM `user`
      WHERE user_id = ?
      LIMIT 1
    ");
    $check_id->bind_param('s', $user_id);
    $check_id->execute();

    if ($check_id->get_result()->num_rows > 0) {
      redirect_to(app_system_url('admin/user_add.php?status=duplicate_id'));
    }

    $check_phone = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE user_phone = ?
      LIMIT 1
    ");
    $check_phone->bind_param('s', $user_phone);
    $check_phone->execute();
    $duplicate_phone = $check_phone->get_result()->fetch_assoc();

    if ($duplicate_phone) {
      redirect_to(
        app_system_url(
          'admin/user_add.php?status=duplicate_phone' .
          '&duplicate_user=' . urlencode($duplicate_phone['user_id'])
        )
      );
    }

    $check_email = $conn->prepare("
      SELECT user_id
      FROM `user`
      WHERE LOWER(user_email) = LOWER(?)
      LIMIT 1
    ");
    $check_email->bind_param('s', $user_email);
    $check_email->execute();
    $duplicate_email = $check_email->get_result()->fetch_assoc();

    if ($duplicate_email) {
      redirect_to(
        app_system_url(
          'admin/user_add.php?status=duplicate_email' .
          '&duplicate_user=' . urlencode($duplicate_email['user_id'])
        )
      );
    }

    $stmt = $conn->prepare("
      INSERT INTO `user`
      (
        user_id,
        user_name,
        user_password,
        user_phone,
        user_email,
        user_address,
        user_role
      )
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
      'ssssssi',
      $user_id,
      $user_name,
      $user_password,
      $user_phone,
      $user_email,
      $user_address,
      $user_role
    );

    $stmt->execute();

    redirect_to(app_system_url('admin/users.php?status=created'));
  } catch (Throwable $e) {
    redirect_to(app_system_url('admin/user_add.php?status=error'));
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

layout_header('เพิ่มข้อมูลพนักงาน', 'users');
?>

<?= flash_message() ?>


<style>
  .field-live-error {
    display: none;
    margin-top: 7px;
    padding-left: 4px;
    color: #dc2626;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.4;
  }

  .field-live-error.is-visible {
    display: block;
  }

  .staff-input-wrap.has-live-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.10);
  }
</style>


<div class="staff-form-page">
  <div class="staff-page-back-row">
    <a class="staff-back-link" href="<?= h(app_system_url('admin/users.php')) ?>">
      <?= icon_svg('back') ?> กลับรายการพนักงาน
    </a>
  </div>

  <form
    class="staff-create-card"
    method="POST"
    action="<?= h(app_system_url('admin/user_add.php')) ?>"
    autocomplete="off"
    id="userAddForm"
  >
    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>ข้อมูลพนักงานใหม่</h2>
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

      <div class="staff-field">
        <label for="user_role">สิทธิ์การใช้งาน *</label>
        <div class="staff-input-wrap select-wrap">
          <?= icon_svg('role') ?>
          <select id="user_role" name="user_role" required>
            <option value="" selected disabled>เลือกสิทธิ์การใช้งาน</option>
            <option value="1">หัวหน้าช่าง</option>
            <option value="2">พนักงานขาย</option>
            <option value="3">ผู้ดูแลระบบ</option>
          </select>
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

      <div class="staff-field staff-field-full">
        <label for="default_password">รหัสผ่านเริ่มต้น</label>
        <div class="staff-input-wrap readonly-field">
          <?= icon_svg('lock') ?>
          <input
            type="text"
            id="default_password"
            value="1234"
            readonly
          >
        </div>
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
      <a class="staff-cancel-btn" href="<?= h(app_system_url('admin/users.php')) ?>">
        ยกเลิก
      </a>

      <button class="staff-save-btn" type="submit">
        <?= icon_svg('save') ?> เพิ่มข้อมูลพนักงาน
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('userAddForm');
  const nameInput = document.getElementById('user_name');
  const phoneInput = document.getElementById('user_phone');
  const emailInput = document.getElementById('user_email');
  const phoneDuplicateError = document.getElementById('phoneDuplicateError');
  const emailDuplicateError = document.getElementById('emailDuplicateError');
  const duplicateCheckUrl = '<?= h(app_system_url('admin/user_add.php')) ?>';
  const provinceSelect = document.getElementById('province_id');
  const districtSelect = document.getElementById('district_id');
  const subDistrictSelect = document.getElementById('sub_district_id');
  const zipCodeInput = document.getElementById('zip_code');

  const districtUrl = '<?= h(app_system_url('ajax/get_districts.php')) ?>';
  const subDistrictUrl = '<?= h(app_system_url('ajax/get_subdistricts.php')) ?>';

  function closeAddressDropdowns(except) {
    document.querySelectorAll('.address-dropdown.is-open').forEach(function (dropdown) {
      if (dropdown !== except) {
        dropdown.classList.remove('is-open');
      }
    });
  }

  function refreshAddressDropdown(select) {
    if (select && typeof select._refreshAddressDropdown === 'function') {
      select._refreshAddressDropdown();
    }
  }

  function initAddressDropdown(select) {
    if (!select || select.dataset.addressReady === '1') return;

    const wrap = select.closest('.address-select-wrap');
    if (!wrap) return;

    select.dataset.addressReady = '1';
    select.classList.add('address-native-select');

    const dropdown = document.createElement('div');
    dropdown.className = 'address-dropdown';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'address-select-button';

    const text = document.createElement('span');
    text.className = 'address-select-text';

    const arrow = document.createElement('span');
    arrow.className = 'address-select-arrow';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '⌄';

    const menu = document.createElement('div');
    menu.className = 'address-select-menu';

    button.appendChild(text);
    button.appendChild(arrow);
    dropdown.appendChild(button);
    dropdown.appendChild(menu);
    wrap.appendChild(dropdown);

    function render() {
      const selected = select.options[select.selectedIndex];
      const first = select.options[0];

      text.textContent =
        selected && selected.value
          ? selected.textContent
          : (first ? first.textContent : 'เลือกข้อมูล');

      button.disabled = select.disabled;
      dropdown.classList.toggle('is-disabled', select.disabled);
      menu.innerHTML = '';

      Array.from(select.options).forEach(function (option) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'address-select-option';
        item.textContent = option.textContent;
        item.disabled = option.disabled || option.value === '';

        if (option.value === '') {
          item.classList.add('is-placeholder');
        }

        if (option.selected && option.value !== '') {
          item.classList.add('is-selected');
        }

        item.addEventListener('click', function () {
          if (item.disabled) return;

          select.value = option.value;
          closeAddressDropdowns();
          dropdown.classList.remove('is-open');
          select.dispatchEvent(new Event('change', { bubbles: true }));
          render();
        });

        menu.appendChild(item);
      });
    }

    button.addEventListener('click', function () {
      if (select.disabled) return;

      const willOpen = !dropdown.classList.contains('is-open');
      closeAddressDropdowns(dropdown);
      dropdown.classList.toggle('is-open', willOpen);
    });

    select.addEventListener('change', render);
    select._refreshAddressDropdown = render;
    render();
  }

  document.querySelectorAll('.js-address-select').forEach(initAddressDropdown);

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.address-dropdown')) {
      closeAddressDropdowns();
    }
  });

  function resetSelect(select, text, disabled = true) {
    if (!select) return;

    select.innerHTML = `<option value="" selected>${text}</option>`;
    select.disabled = disabled;
    refreshAddressDropdown(select);
  }

  function setLoading(select, text) {
    if (!select) return;

    select.innerHTML = `<option value="" selected>${text}</option>`;
    select.disabled = true;
    refreshAddressDropdown(select);
  }

  function loadDistricts(provinceId) {
    setLoading(districtSelect, 'กำลังโหลดอำเภอ...');
    resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
    zipCodeInput.value = '';

    fetch(`${districtUrl}?province_id=${encodeURIComponent(provinceId)}`)
      .then(response => response.json())
      .then(data => {
        resetSelect(districtSelect, 'เลือกอำเภอ', false);

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          districtSelect.appendChild(option);
        });

        refreshAddressDropdown(districtSelect);
      })
      .catch(() => resetSelect(districtSelect, 'โหลดอำเภอไม่สำเร็จ'));
  }

  function loadSubDistricts(districtId) {
    setLoading(subDistrictSelect, 'กำลังโหลดตำบล...');
    zipCodeInput.value = '';

    fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(districtId)}`)
      .then(response => response.json())
      .then(data => {
        resetSelect(subDistrictSelect, 'เลือกตำบล', false);

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          option.dataset.zipCode = item.zip_code || '';
          subDistrictSelect.appendChild(option);
        });

        refreshAddressDropdown(subDistrictSelect);
      })
      .catch(() => resetSelect(subDistrictSelect, 'โหลดตำบลไม่สำเร็จ'));
  }


  let phoneCheckController = null;
  let emailCheckController = null;

  function setDuplicateMessage(input, messageBox, message) {
    const wrap = input.closest('.staff-input-wrap');

    if (message) {
      messageBox.textContent = message;
      messageBox.classList.add('is-visible');
      wrap.classList.add('has-live-error');
      input.dataset.duplicate = '1';
    } else {
      messageBox.textContent = '';
      messageBox.classList.remove('is-visible');
      wrap.classList.remove('has-live-error');
      input.dataset.duplicate = '0';
    }
  }

  async function checkDuplicate(field, value, input, messageBox) {
    const trimmedValue = value.trim();

    if (!trimmedValue) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (field === 'user_phone' && !/^[0-9]{10}$/.test(trimmedValue)) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (
      field === 'user_email' &&
      !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedValue)
    ) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (field === 'user_phone') {
      if (phoneCheckController) phoneCheckController.abort();
      phoneCheckController = new AbortController();
    } else {
      if (emailCheckController) emailCheckController.abort();
      emailCheckController = new AbortController();
    }

    const controller =
      field === 'user_phone'
        ? phoneCheckController
        : emailCheckController;

    try {
      const response = await fetch(
        `${duplicateCheckUrl}?ajax=check_duplicate&field=${encodeURIComponent(field)}&value=${encodeURIComponent(trimmedValue)}`,
        {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: controller.signal
        }
      );

      const result = await response.json();

      if (result.duplicate) {
        const label = field === 'user_phone' ? 'เบอร์โทรศัพท์' : 'อีเมล';

        setDuplicateMessage(
          input,
          messageBox,
          `${label}นี้มีผู้ใช้งานแล้ว (รหัสผู้ใช้ ${result.user_id})`
        );

        return true;
      }

      setDuplicateMessage(input, messageBox, '');
      return false;
    } catch (error) {
      if (error.name !== 'AbortError') {
        setDuplicateMessage(input, messageBox, '');
      }

      return false;
    }
  }

  phoneInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
    setDuplicateMessage(this, phoneDuplicateError, '');

    if (this.value.length === 10) {
      checkDuplicate('user_phone', this.value, this, phoneDuplicateError);
    }
  });

  phoneInput.addEventListener('blur', function () {
    checkDuplicate('user_phone', this.value, this, phoneDuplicateError);
  });

  nameInput.addEventListener('blur', function () {
    this.value = this.value.trim().replace(/\s+/g, ' ');

    const valid = /^\S+(?:\s+\S+)+$/u.test(this.value);

    this.setCustomValidity(
      valid
        ? ''
        : 'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล'
    );
  });

  nameInput.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  emailInput.addEventListener('blur', function () {
    const value = this.value.trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    this.setCustomValidity(
      valid
        ? ''
        : 'กรุณากรอกอีเมลให้ถูกต้อง และต้องมีเครื่องหมาย @'
    );
  });

  emailInput.addEventListener('input', function () {
    this.setCustomValidity('');
    setDuplicateMessage(this, emailDuplicateError, '');
  });

  emailInput.addEventListener('blur', function () {
    const value = this.value.trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    if (valid) {
      checkDuplicate('user_email', value, this, emailDuplicateError);
    }
  });

  provinceSelect.addEventListener('change', function () {
    if (this.value) {
      loadDistricts(this.value);
    } else {
      resetSelect(districtSelect, 'เลือกจังหวัดก่อน');
      resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
    }
  });

  districtSelect.addEventListener('change', function () {
    if (this.value) {
      loadSubDistricts(this.value);
    } else {
      resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
    }
  });

  subDistrictSelect.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    zipCodeInput.value = selected ? (selected.dataset.zipCode || '') : '';
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    nameInput.value = nameInput.value.trim().replace(/\s+/g, ' ');

    if (!/^\S+(?:\s+\S+)+$/u.test(nameInput.value)) {
      nameInput.setCustomValidity(
        'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล'
      );
      nameInput.reportValidity();
      return;
    }

    if (!form.reportValidity()) {
      return;
    }

    const phoneDuplicate = await checkDuplicate(
      'user_phone',
      phoneInput.value,
      phoneInput,
      phoneDuplicateError
    );

    const emailDuplicate = await checkDuplicate(
      'user_email',
      emailInput.value,
      emailInput,
      emailDuplicateError
    );

    if (phoneDuplicate || emailDuplicate) {
      const firstDuplicateInput = phoneDuplicate ? phoneInput : emailInput;
      firstDuplicateInput.focus();
      return;
    }

    form.submit();
  });
});
</script>

<?php layout_footer(); ?>