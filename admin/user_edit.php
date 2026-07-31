<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function column_exists(mysqli $conn, string $table, string $column): bool
{
  $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();

  return $stmt->get_result()->num_rows > 0;
}

function prepare_user_fullname_column(mysqli $conn): void
{
  if (!column_exists($conn, 'user', 'user_fullname')) {
    $conn->query("
            ALTER TABLE `user`
            ADD COLUMN `user_fullname` VARCHAR(100) NOT NULL DEFAULT '' AFTER `user_name`
        ");
  }
}

function get_user_by_id(mysqli $conn, string $user_id): ?array
{
  $stmt = $conn->prepare("
        SELECT *
        FROM `user`
        WHERE user_id = ?
        LIMIT 1
    ");

  $stmt->bind_param('s', $user_id);
  $stmt->execute();

  $result = $stmt->get_result();

  return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

prepare_user_fullname_column($conn);

$user_id = trim($_GET['id'] ?? $_POST['user_id'] ?? '');

if ($user_id === '') {
  redirect_to(app_system_url('admin/users.php?status=error'));
}

$user = get_user_by_id($conn, $user_id);

if (!$user) {
  redirect_to(app_system_url('admin/users.php?status=error'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  /*
    user_name = ชื่อผู้ใช้ที่แสดงในระบบ / ห้ามซ้ำ
    user_fullname = ชื่อ-นามสกุลจริง / Admin ใช้ดูข้อมูล
  */
  $user_name = trim($_POST['user_name'] ?? '');
  $user_fullname = trim($_POST['user_fullname'] ?? '');
  $user_password = trim($_POST['user_password'] ?? '');
  $user_phone = trim($_POST['user_phone'] ?? '');
  $user_email = trim($_POST['user_email'] ?? '');
  $user_address = trim($_POST['user_address'] ?? '');
  $user_role = trim($_POST['user_role'] ?? '');

  if (
    $user_name === '' ||
    $user_fullname === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $user_address === '' ||
    $user_role === ''
  ) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=error'));
  }

  if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=phone'));
  }

  if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $user_email)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=email'));
  }

  if (!in_array($user_role, ['0', '1', '2', '3'], true)) {
    redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=role'));
  }

  $user_role = (int) $user_role;

  try {
    /*
      ตรวจสอบข้อมูลซ้ำ ยกเว้นข้อมูลของตัวเอง
      user_name คือชื่อผู้ใช้สำหรับแสดงในระบบ จึงต้องห้ามซ้ำ
    */
    $check = $conn->prepare("
            SELECT user_id, user_name, user_phone, user_email
            FROM `user`
            WHERE user_id <> ?
              AND (
                    user_name = ?
                 OR user_phone = ?
                 OR user_email = ?
              )
            LIMIT 1
        ");

    $check->bind_param(
      'ssss',
      $user_id,
      $user_name,
      $user_phone,
      $user_email
    );

    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
      $duplicate = $check_result->fetch_assoc();

      if ($duplicate['user_name'] === $user_name) {
        redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=duplicate_name'));
      }

      if ($duplicate['user_phone'] === $user_phone) {
        redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=duplicate_phone'));
      }

      if ($duplicate['user_email'] === $user_email) {
        redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=duplicate_email'));
      }

      redirect_to(app_system_url('admin/user_edit.php?id=' . urlencode($user_id) . '&status=duplicate'));
    }

    if ($user_password !== '') {
      $stmt = $conn->prepare("
                UPDATE `user`
                SET user_name = ?,
                    user_fullname = ?,
                    user_password = ?,
                    user_phone = ?,
                    user_email = ?,
                    user_address = ?,
                    user_role = ?
                WHERE user_id = ?
            ");

      $stmt->bind_param(
        'ssssssis',
        $user_name,
        $user_fullname,
        $user_password,
        $user_phone,
        $user_email,
        $user_address,
        $user_role,
        $user_id
      );
    } else {
      $stmt = $conn->prepare("
                UPDATE `user`
                SET user_name = ?,
                    user_fullname = ?,
                    user_phone = ?,
                    user_email = ?,
                    user_address = ?,
                    user_role = ?
                WHERE user_id = ?
            ");

      $stmt->bind_param(
        'sssssis',
        $user_name,
        $user_fullname,
        $user_phone,
        $user_email,
        $user_address,
        $user_role,
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

$provinces = [];
try {
  $province_result = $conn->query("SELECT id, name_th FROM provinces ORDER BY name_th ASC");
  while ($province = $province_result->fetch_assoc()) {
    $provinces[] = $province;
  }
} catch (Throwable $e) {
  $provinces = [];
}

layout_header('แก้ไขข้อมูลพนักงาน', 'users');
?>
<?= flash_message() ?>
<div class="staff-form-page">
  <div class="staff-page-back-row"><a class="staff-back-link"
      href="<?= h(app_system_url('admin/users.php')) ?>"><?= admin_form_icon_svg('back') ?> กลับรายการพนักงาน</a></div>
  <form class="staff-create-card" method="POST" action="<?= h(app_system_url('admin/user_edit.php')) ?>"
    autocomplete="off">
    <input type="hidden" name="user_id" value="<?= h($user['user_id']) ?>">
    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>แก้ไขข้อมูลพนักงาน</h2>
      </div>
      <div class="staff-password-pill"><?= admin_form_icon_svg('lock') ?> เว้นรหัสผ่านว่างไว้ หากไม่ต้องการเปลี่ยน</div>
    </div>
    <div class="staff-form-grid">
      <div class="staff-field readonly-field"><label for="user_id_show">รหัสผู้ใช้ *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('id') ?><input type="text" id="user_id_show"
            value="<?= h($user['user_id']) ?>" readonly></div>
      </div>
      <div class="staff-field"><label for="user_role">สิทธิ์การใช้งาน *</label>
        <div class="staff-input-wrap select-wrap"><?= admin_form_icon_svg('role') ?><select id="user_role"
            name="user_role" required><?php $current_role = (string) $user['user_role']; ?>
            <option value="1" <?= $current_role === '1' ? 'selected' : '' ?>>หัวหน้าช่าง</option>
            <option value="2" <?= $current_role === '2' ? 'selected' : '' ?>>พนักงานขาย</option>
            <option value="3" <?= $current_role === '3' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
          </select></div>
      </div>
      <div class="staff-field"><label for="user_name">ชื่อผู้ใช้ *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('user') ?><input type="text" id="user_name"
            name="user_name" maxlength="50" value="<?= h($user['user_name']) ?>" required></div>
      </div>
      <div class="staff-field"><label for="user_fullname">ชื่อ-นามสกุลจริง *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('user') ?><input type="text" id="user_fullname"
            name="user_fullname" maxlength="100" value="<?= h($user['user_fullname'] ?? '') ?>" required></div>
      </div>
      <div class="staff-field"><label for="user_phone">เบอร์โทรศัพท์ *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('phone') ?><input type="text" id="user_phone"
            name="user_phone" maxlength="10" inputmode="numeric" value="<?= h($user['user_phone']) ?>" required></div>
      </div>
      <div class="staff-field"><label for="user_email">อีเมล Gmail *</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('mail') ?><input type="email" id="user_email"
            name="user_email" maxlength="50" value="<?= h($user['user_email']) ?>" required></div>
      </div>
      <div class="staff-field staff-field-full"><label for="user_password">รหัสผ่าน</label>
        <div class="staff-input-wrap"><?= admin_form_icon_svg('lock') ?><input type="password" id="user_password"
            name="user_password" maxlength="50" placeholder="กรอกรหัสผ่านใหม่เมื่อต้องการเปลี่ยน"></div>
      </div>
      <input type="hidden" id="user_address" name="user_address" value="<?= h($user['user_address']) ?>">
      <div class="staff-field staff-field-full staff-address-section">
        <div class="staff-address-title"><?= admin_form_icon_svg('map') ?><label>ข้อมูลที่อยู่ *</label></div>
        <div class="staff-address-grid">
          <div class="staff-field staff-address-detail"><label for="address_detail">บ้านเลขที่ / หมู่ / ถนน *</label>
            <div class="staff-input-wrap"><?= admin_form_icon_svg('map') ?><input type="text" id="address_detail"
                name="address_detail" maxlength="255" placeholder="เช่น 150 หมู่ 1 ถนนศรีจันทร์"
                value="<?= h($user['user_address']) ?>" required></div>
          </div>
          <div class="staff-field"><label for="province_id">จังหวัด *</label>
            <div class="staff-input-wrap address-select-wrap"><?= admin_form_icon_svg('map') ?><select id="province_id"
                name="province_id" class="js-address-select">
                <option value="" selected disabled>เลือกจังหวัด</option><?php foreach ($provinces as $province): ?>
                  <option value="<?= h($province['id']) ?>"><?= h($province['name_th']) ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="staff-field"><label for="district_id">อำเภอ *</label>
            <div class="staff-input-wrap address-select-wrap"><?= admin_form_icon_svg('map') ?><select id="district_id"
                name="district_id" class="js-address-select" disabled>
                <option value="" selected>เลือกจังหวัดก่อน</option>
              </select></div>
          </div>
          <div class="staff-field"><label for="sub_district_id">ตำบล *</label>
            <div class="staff-input-wrap address-select-wrap"><?= admin_form_icon_svg('map') ?><select
                id="sub_district_id" name="sub_district_id" class="js-address-select" disabled>
                <option value="" selected>เลือกอำเภอก่อน</option>
              </select></div>
          </div>
          <div class="staff-field"><label for="zip_code">รหัสไปรษณีย์ *</label>
            <div class="staff-input-wrap readonly-field"><?= admin_form_icon_svg('id') ?><input type="text"
                id="zip_code" name="zip_code" maxlength="10" placeholder="ระบบเติมให้อัตโนมัติ" readonly></div>
          </div>
        </div>
      </div>
    </div>
    <div class="staff-form-actions"><a class="staff-cancel-btn"
        href="<?= h(app_system_url('admin/users.php')) ?>">ยกเลิก</a><button class="staff-save-btn"
        type="submit"><?= admin_form_icon_svg('save') ?> บันทึกข้อมูล</button></div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('user_phone') || document.getElementById('tech_phone');
    const emailInput = document.getElementById('user_email') || document.getElementById('tech_email');
    const provinceSelect = document.getElementById('province_id');
    const districtSelect = document.getElementById('district_id');
    const subDistrictSelect = document.getElementById('sub_district_id');
    const zipCodeInput = document.getElementById('zip_code');
    const addressDetail = document.getElementById('address_detail');
    const fullAddressInput = document.getElementById('user_address');
    const addressForm = document.querySelector('form.staff-create-card');
    const districtUrl = '<?= h(app_system_url('ajax/get_districts.php')) ?>';
    const subDistrictUrl = '<?= h(app_system_url('ajax/get_subdistricts.php')) ?>';

    function closeAddressDropdowns(except) {
      document.querySelectorAll('.address-dropdown.is-open').forEach(function (dropdown) {
        if (dropdown !== except) dropdown.classList.remove('is-open');
      });
    }
    function refreshAddressDropdown(select) {
      if (select && typeof select._refreshAddressDropdown === 'function') select._refreshAddressDropdown();
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
      arrow.textContent = '⌄';
      const menu = document.createElement('div');
      menu.className = 'address-select-menu';
      button.appendChild(text); button.appendChild(arrow); dropdown.appendChild(button); dropdown.appendChild(menu); wrap.appendChild(dropdown);
      function render() {
        const selected = select.options[select.selectedIndex];
        const first = select.options[0];
        text.textContent = selected && selected.value ? selected.textContent : (first ? first.textContent : 'เลือกข้อมูล');
        button.disabled = select.disabled;
        dropdown.classList.toggle('is-disabled', select.disabled);
        menu.innerHTML = '';
        Array.from(select.options).forEach(function (option) {
          const item = document.createElement('button');
          item.type = 'button';
          item.className = 'address-select-option';
          item.textContent = option.textContent;
          item.disabled = option.disabled || option.value === '';
          if (option.value === '') item.classList.add('is-placeholder');
          if (option.selected && option.value !== '') item.classList.add('is-selected');
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
    document.addEventListener('click', function (event) { if (!event.target.closest('.address-dropdown')) closeAddressDropdowns(); });
    function resetSelect(select, text, disabled = true) { if (!select) return; select.innerHTML = `<option value="" selected>${text}</option>`; select.disabled = disabled; refreshAddressDropdown(select); }
    function setLoading(select, text) { if (!select) return; select.innerHTML = `<option value="" selected>${text}</option>`; select.disabled = true; refreshAddressDropdown(select); }
    function loadDistricts(provinceId) {
      setLoading(districtSelect, 'กำลังโหลดอำเภอ...');
      resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
      if (zipCodeInput) zipCodeInput.value = '';
      fetch(`${districtUrl}?province_id=${encodeURIComponent(provinceId)}`).then(r => r.json()).then(data => {
        resetSelect(districtSelect, 'เลือกอำเภอ', false);
        data.forEach(item => { const o = document.createElement('option'); o.value = item.id; o.textContent = item.name_th; districtSelect.appendChild(o); });
        refreshAddressDropdown(districtSelect);
      }).catch(() => resetSelect(districtSelect, 'โหลดอำเภอไม่สำเร็จ'));
    }
    function loadSubDistricts(districtId) {
      setLoading(subDistrictSelect, 'กำลังโหลดตำบล...');
      if (zipCodeInput) zipCodeInput.value = '';
      fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(districtId)}`).then(r => r.json()).then(data => {
        resetSelect(subDistrictSelect, 'เลือกตำบล', false);
        data.forEach(item => { const o = document.createElement('option'); o.value = item.id; o.textContent = item.name_th; o.dataset.zipCode = item.zip_code || ''; subDistrictSelect.appendChild(o); });
        refreshAddressDropdown(subDistrictSelect);
      }).catch(() => resetSelect(subDistrictSelect, 'โหลดตำบลไม่สำเร็จ'));
    }
    if (phoneInput) phoneInput.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 10); });
    if (emailInput) {
      emailInput.addEventListener('blur', function () { const value = this.value.trim(); this.setCustomValidity(value && !value.toLowerCase().endsWith('@gmail.com') ? 'กรุณากรอกอีเมลที่ลงท้ายด้วย @gmail.com' : ''); });
      emailInput.addEventListener('input', function () { this.setCustomValidity(''); });
    }
    if (provinceSelect) provinceSelect.addEventListener('change', function () { if (this.value) loadDistricts(this.value); else { resetSelect(districtSelect, 'เลือกจังหวัดก่อน'); resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน'); } });
    if (districtSelect) districtSelect.addEventListener('change', function () { if (this.value) loadSubDistricts(this.value); else resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน'); });
    if (subDistrictSelect && zipCodeInput) subDistrictSelect.addEventListener('change', function () { const selected = this.options[this.selectedIndex]; zipCodeInput.value = selected ? (selected.dataset.zipCode || '') : ''; });
    if (addressForm && fullAddressInput) {
      addressForm.addEventListener('submit', function () {
        const detail = addressDetail ? addressDetail.value.trim() : '';
        const provinceText = provinceSelect && provinceSelect.value ? provinceSelect.options[provinceSelect.selectedIndex].textContent.trim() : '';
        const districtText = districtSelect && districtSelect.value ? districtSelect.options[districtSelect.selectedIndex].textContent.trim() : '';
        const subText = subDistrictSelect && subDistrictSelect.value ? subDistrictSelect.options[subDistrictSelect.selectedIndex].textContent.trim() : '';
        const zip = zipCodeInput ? zipCodeInput.value.trim() : '';
        if (detail && provinceText && districtText && subText && zip) {
          fullAddressInput.value = `${detail} ต.${subText} อ.${districtText} จ.${provinceText} ${zip}`;
        } else if (detail && !fullAddressInput.value.trim()) {
          fullAddressInput.value = detail;
        }
      });
    }
  });
</script>

<?php layout_footer(); ?>