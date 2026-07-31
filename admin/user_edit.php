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
  redirect_to(app_system_url('admin/users.php?status=error'));
}

$user = get_user_by_id($conn, $user_id);

if (!$user) {
  redirect_to(app_system_url('admin/users.php?status=error'));
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
  $user_role = trim($_POST['user_role'] ?? '');

  if (
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $user_address === '' ||
    $user_role === ''
  ) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }

  if (!is_valid_full_name($user_name)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=name_space'));
  }

  if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=phone'));
  }

  // รับอีเมลทุกโดเมน แต่ต้องเป็นรูปแบบอีเมลที่ถูกต้องและมี @
  if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || strpos($user_email, '@') === false) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=email'));
  }

  if (!in_array($user_role, ['1', '2', '3'], true)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=role'));
  }

  if ($change_password && $user_password === '') {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }

  $role_int = (int) $user_role;

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
          'admin/user_edit.php?id=' . urlencode($user_id) .
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
          'admin/user_edit.php?id=' . urlencode($user_id) .
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
    redirect_to(app_system_url('admin/users.php?status=updated'));
  } catch (Throwable $e) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=error'));
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

layout_header('แก้ไขข้อมูลพนักงาน', 'users');
?>

<?= flash_message() ?>

<style>
  .password-change-trigger {
    border: 1px solid #f3b95f;
    background: #fff6df;
    color: #a85b00;
    border-radius: 999px;
    padding: 10px 16px;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
  }

  .password-change-trigger:hover {
    background: #ffedbd;
  }

  .password-change-trigger.is-active {
    border-color: #ff8a00;
    background: #ff8a00;
    color: #fff;
  }

  .password-help {
    margin-top: 8px;
    color: #8a6b3f;
    font-size: 13px;
  }

  .password-inline-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .password-input-wrap {
    flex: 1;
  }

  .password-change-trigger-small {
    flex: 0 0 auto;
    min-width: 112px;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    white-space: nowrap;
  }

  @media (max-width: 760px) {
    .password-inline-row {
      align-items: stretch;
      flex-direction: column;
    }

    .password-change-trigger-small {
      width: 100%;
    }
  }


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
      <?= admin_form_icon_svg('back') ?> กลับรายการพนักงาน
    </a>
  </div>

  <form
    class="staff-create-card"
    method="POST"
    action="<?= h(app_system_url('admin/user_edit.php')) ?>"
    autocomplete="off"
    id="userEditForm"
  >
    <input type="hidden" name="user_id" value="<?= h($user['user_id']) ?>">
    <input type="hidden" name="change_password" id="change_password" value="0">

    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>แก้ไขข้อมูลพนักงาน</h2>
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

      <div class="staff-field">
        <label for="user_role">สิทธิ์การใช้งาน *</label>
        <div class="staff-input-wrap select-wrap">
          <?= admin_form_icon_svg('role') ?>
          <select id="user_role" name="user_role" required>
            <?php $current_role = (string) $user['user_role']; ?>
            <option value="1" <?= $current_role === '1' ? 'selected' : '' ?>>หัวหน้าช่าง</option>
            <option value="2" <?= $current_role === '2' ? 'selected' : '' ?>>พนักงานขาย</option>
            <option value="3" <?= $current_role === '3' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
          </select>
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
      <a class="staff-cancel-btn" href="<?= h(app_system_url('admin/users.php')) ?>">ยกเลิก</a>
      <button class="staff-save-btn" type="submit">
        <?= admin_form_icon_svg('save') ?> บันทึกข้อมูล
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('userEditForm');
  const nameInput = document.getElementById('user_name');
  const phoneInput = document.getElementById('user_phone');
  const emailInput = document.getElementById('user_email');
  const phoneDuplicateError = document.getElementById('phoneDuplicateError');
  const emailDuplicateError = document.getElementById('emailDuplicateError');
  const duplicateCheckUrl = '<?= h(app_system_url('admin/user_edit.php')) ?>';
  const currentUserId = <?= json_encode((string) $user['user_id']) ?>;
  const passwordInput = document.getElementById('user_password');
  const passwordTrigger = document.getElementById('passwordChangeTrigger');
  const changePasswordInput = document.getElementById('change_password');

  const provinceSelect = document.getElementById('province_id');
  const districtSelect = document.getElementById('district_id');
  const subDistrictSelect = document.getElementById('sub_district_id');
  const zipCodeInput = document.getElementById('zip_code');
  const addressDetail = document.getElementById('address_detail');
  const fullAddressInput = document.getElementById('user_address');

  const districtUrl = '<?= h(app_system_url('ajax/get_districts.php')) ?>';
  const subDistrictUrl = '<?= h(app_system_url('ajax/get_subdistricts.php')) ?>';

  const selectedProvinceName = <?= json_encode($selected_province_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const selectedDistrictName = <?= json_encode($selected_district_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const selectedSubdistrictName = <?= json_encode($selected_subdistrict_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const selectedZipCode = <?= json_encode($selected_zip_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;



  function selectOptionByText(select, wantedText) {
    if (!select || !wantedText) return false;

    const wanted = wantedText.trim();
    const option = Array.from(select.options).find(item => item.textContent.trim() === wanted);

    if (!option) return false;

    select.value = option.value;
    return true;
  }

  function loadSavedAddressSelection() {
    if (!selectedProvinceName) return;

    if (!selectOptionByText(provinceSelect, selectedProvinceName)) return;

    districtSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
    districtSelect.disabled = true;

    fetch(`${districtUrl}?province_id=${encodeURIComponent(provinceSelect.value)}`)
      .then(response => response.json())
      .then(data => {
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ</option>';

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          districtSelect.appendChild(option);
        });

        districtSelect.disabled = false;

        if (!selectOptionByText(districtSelect, selectedDistrictName)) return;

        subDistrictSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
        subDistrictSelect.disabled = true;

        return fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(districtSelect.value)}`)
          .then(response => response.json())
          .then(subData => {
            subDistrictSelect.innerHTML = '<option value="">เลือกตำบล</option>';

            subData.forEach(item => {
              const option = document.createElement('option');
              option.value = item.id;
              option.textContent = item.name_th;
              option.dataset.zipCode = item.zip_code || '';
              subDistrictSelect.appendChild(option);
            });

            subDistrictSelect.disabled = false;
            selectOptionByText(subDistrictSelect, selectedSubdistrictName);

            const selected = subDistrictSelect.options[subDistrictSelect.selectedIndex];
            zipCodeInput.value = selected && selected.dataset.zipCode
              ? selected.dataset.zipCode
              : selectedZipCode;
          });
      })
      .catch(() => {
        districtSelect.innerHTML = '<option value="">เลือกจังหวัดก่อน</option>';
        districtSelect.disabled = true;
        subDistrictSelect.innerHTML = '<option value="">เลือกอำเภอก่อน</option>';
        subDistrictSelect.disabled = true;
        zipCodeInput.value = selectedZipCode;
      });
  }

  loadSavedAddressSelection();


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
        `${duplicateCheckUrl}?ajax=check_duplicate&field=${encodeURIComponent(field)}&value=${encodeURIComponent(trimmedValue)}&exclude_user_id=${encodeURIComponent(currentUserId)}`,
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

  passwordTrigger.addEventListener('click', function () {
    const isEditing = changePasswordInput.value === '1';

    if (isEditing) {
      changePasswordInput.value = '0';
      passwordInput.readOnly = true;
      passwordInput.value = <?= json_encode((string) ($user['user_password'] ?: '1234')) ?>;
      passwordTrigger.classList.remove('is-active');
      passwordTrigger.textContent = 'แก้รหัสผ่าน';
    } else {
      changePasswordInput.value = '1';
      passwordInput.readOnly = false;
      passwordInput.focus();
      passwordInput.select();
      passwordTrigger.classList.add('is-active');
      passwordTrigger.textContent = 'ยกเลิกการเปลี่ยนรหัสผ่าน';
    }
  });

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
    this.setCustomValidity(valid ? '' : 'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
  });

  nameInput.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  emailInput.addEventListener('blur', function () {
    const value = this.value.trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    this.setCustomValidity(valid ? '' : 'กรุณากรอกอีเมลให้ถูกต้อง และต้องมีเครื่องหมาย @');
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
    districtSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
    districtSelect.disabled = true;
    subDistrictSelect.innerHTML = '<option value="">เลือกอำเภอก่อน</option>';
    subDistrictSelect.disabled = true;
    zipCodeInput.value = '';

    if (!this.value) return;

    fetch(`${districtUrl}?province_id=${encodeURIComponent(this.value)}`)
      .then(response => response.json())
      .then(data => {
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ</option>';
        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          districtSelect.appendChild(option);
        });
        districtSelect.disabled = false;
      });
  });

  districtSelect.addEventListener('change', function () {
    subDistrictSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
    subDistrictSelect.disabled = true;
    zipCodeInput.value = '';

    if (!this.value) return;

    fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(this.value)}`)
      .then(response => response.json())
      .then(data => {
        subDistrictSelect.innerHTML = '<option value="">เลือกตำบล</option>';
        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          option.dataset.zipCode = item.zip_code || '';
          subDistrictSelect.appendChild(option);
        });
        subDistrictSelect.disabled = false;
      });
  });

  subDistrictSelect.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    zipCodeInput.value = selected ? (selected.dataset.zipCode || '') : '';
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    nameInput.value = nameInput.value.trim().replace(/\s+/g, ' ');

    if (!/^\S+(?:\s+\S+)+$/u.test(nameInput.value)) {
      nameInput.setCustomValidity('กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
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

    const detail = addressDetail.value.trim();
    const provinceText = provinceSelect.value
      ? provinceSelect.options[provinceSelect.selectedIndex].textContent.trim()
      : '';
    const districtText = districtSelect.value
      ? districtSelect.options[districtSelect.selectedIndex].textContent.trim()
      : '';
    const subText = subDistrictSelect.value
      ? subDistrictSelect.options[subDistrictSelect.selectedIndex].textContent.trim()
      : '';
    const zip = zipCodeInput.value.trim();

    if (detail && provinceText && districtText && subText && zip) {
      fullAddressInput.value = `${detail} ต.${subText} อ.${districtText} จ.${provinceText} ${zip}`;
    } else {
      fullAddressInput.value = detail;
    }

    form.submit();
  });
});
</script>

<?php layout_footer(); ?>