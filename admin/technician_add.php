<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function make_tech_id(mysqli $conn): string
{
    $prefix = 'TEC-2569-';

    for ($i = 1; $i <= 9999; $i++) {
        $tech_id = $prefix . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT tech_id FROM technicians WHERE tech_id = ? LIMIT 1");
        $stmt->bind_param('s', $tech_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            return $tech_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสช่างใหม่ได้');
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
    'save' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>',
    'back' => '<svg class="action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>',
  ];

  return $icons[$name] ?? '';
}

function normalize_full_name(string $name): string
{
    return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'check_duplicate') {
    header('Content-Type: application/json; charset=utf-8');

    $field = trim($_GET['field'] ?? '');
    $value = trim($_GET['value'] ?? '');

    if ($value === '' || !in_array($field, ['tech_phone', 'tech_email'], true)) {
        echo json_encode(['duplicate' => false, 'tech_id' => ''], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($field === 'tech_phone') {
        $stmt = $conn->prepare("SELECT tech_id FROM technicians WHERE tech_phone = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT tech_id FROM technicians WHERE LOWER(tech_email) = LOWER(?) LIMIT 1");
    }

    $stmt->bind_param('s', $value);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'duplicate' => (bool) $duplicate,
        'tech_id' => $duplicate['tech_id'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tech_id = trim($_POST['tech_id'] ?? '');
    $tech_name = normalize_full_name($_POST['tech_name'] ?? '');
    $tech_phone = trim($_POST['tech_phone'] ?? '');
    $tech_email = trim($_POST['tech_email'] ?? '');
    $tech_status = trim($_POST['tech_status'] ?? '');
    $tech_password = '1234';

    if ($tech_id === '' || $tech_name === '' || $tech_phone === '' || $tech_email === '' || $tech_status === '') {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }

    if (!preg_match('/^\S+(?:\s+\S+)+$/u', $tech_name)) {
        redirect_to(app_system_url('admin/technician_add.php?status=name_space'));
    }

    if (!preg_match('/^[0-9]{10}$/', $tech_phone)) {
        redirect_to(app_system_url('admin/technician_add.php?status=phone'));
    }

    if (!filter_var($tech_email, FILTER_VALIDATE_EMAIL)) {
        redirect_to(app_system_url('admin/technician_add.php?status=email'));
    }

    if (!in_array($tech_status, ['0', '1'], true)) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }

    try {
        $check_phone = $conn->prepare("SELECT tech_id FROM technicians WHERE tech_phone = ? LIMIT 1");
        $check_phone->bind_param('s', $tech_phone);
        $check_phone->execute();
        if ($duplicate = $check_phone->get_result()->fetch_assoc()) {
            redirect_to(app_system_url('admin/technician_add.php?status=duplicate_phone&duplicate_user=' . urlencode($duplicate['tech_id'])));
        }

        $check_email = $conn->prepare("SELECT tech_id FROM technicians WHERE LOWER(tech_email) = LOWER(?) LIMIT 1");
        $check_email->bind_param('s', $tech_email);
        $check_email->execute();
        if ($duplicate = $check_email->get_result()->fetch_assoc()) {
            redirect_to(app_system_url('admin/technician_add.php?status=duplicate_email&duplicate_user=' . urlencode($duplicate['tech_id'])));
        }

        $stmt = $conn->prepare("
            INSERT INTO technicians
            (tech_id, tech_name, tech_password, tech_phone, tech_email, tech_status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $status_int = (int) $tech_status;
        $stmt->bind_param('sssssi', $tech_id, $tech_name, $tech_password, $tech_phone, $tech_email, $status_int);
        $stmt->execute();

        redirect_to(app_system_url('admin/technicians.php?status=created'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }
}

$default_tech_id = make_tech_id($conn);

layout_header('เพิ่มข้อมูลช่าง', 'technicians');
?>

<?= flash_message() ?>

<style>
.field-live-error{display:none;margin-top:7px;color:#dc2626;font-size:13px;font-weight:700}
.field-live-error.is-visible{display:block}
.staff-input-wrap.has-live-error{border-color:#ef4444!important;box-shadow:0 0 0 3px rgba(239,68,68,.1)}
</style>

<div class="staff-form-page">
  <div class="staff-page-back-row">
    <a class="staff-back-link" href="<?= h(app_system_url('admin/technicians.php')) ?>">
      <?= icon_svg('back') ?> กลับรายการช่าง
    </a>
  </div>

  <form
    class="staff-create-card"
    method="POST"
    action="<?= h(app_system_url('admin/technician_add.php')) ?>"
    autocomplete="off"
    id="techAddForm"
  >
    <div class="staff-create-head staff-create-head-clean">
      <div>
        <h2>ข้อมูลช่างใหม่</h2>
      </div>
    </div>

    <div class="staff-form-grid">
      <div class="staff-field readonly-field">
        <label for="tech_id">รหัสช่าง *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('id') ?>
          <input
            type="text"
            id="tech_id"
            name="tech_id"
            maxlength="13"
            value="<?= h($default_tech_id) ?>"
            readonly
            required
          >
        </div>
      </div>

      <div class="staff-field">
        <label for="tech_status">สถานะช่าง *</label>
        <div class="staff-input-wrap select-wrap">
          <?= icon_svg('role') ?>
          <select id="tech_status" name="tech_status" required>
            <option value="0">พร้อมรับงาน</option>
            <option value="1">ไม่พร้อมรับงาน</option>
          </select>
        </div>
      </div>

      <div class="staff-field staff-field-full">
        <label for="tech_name">ชื่อ-นามสกุล *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('user') ?>
          <input
            type="text"
            id="tech_name"
            name="tech_name"
            maxlength="100"
            placeholder="เช่น สมชาย ใจดี"
            required
          >
        </div>
      </div>

      <div class="staff-field">
        <label for="tech_phone">เบอร์โทรศัพท์ *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('phone') ?>
          <input
            type="text"
            id="tech_phone"
            name="tech_phone"
            maxlength="10"
            inputmode="numeric"
            placeholder="0812345678"
            required
          >
        </div>
        <div class="field-live-error" id="phoneDuplicateError" aria-live="polite"></div>
      </div>

      <div class="staff-field">
        <label for="tech_email">อีเมล *</label>
        <div class="staff-input-wrap">
          <?= icon_svg('mail') ?>
          <input
            type="email"
            id="tech_email"
            name="tech_email"
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
    </div>

    <div class="staff-form-actions">
      <a class="staff-cancel-btn" href="<?= h(app_system_url('admin/technicians.php')) ?>">
        ยกเลิก
      </a>

      <button class="staff-save-btn" type="submit">
        <?= icon_svg('save') ?> เพิ่มข้อมูลช่าง
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form=document.getElementById('techAddForm');
  const nameInput=document.getElementById('tech_name');
  const phoneInput=document.getElementById('tech_phone');
  const emailInput=document.getElementById('tech_email');
  const phoneError=document.getElementById('phoneDuplicateError');
  const emailError=document.getElementById('emailDuplicateError');
  const url='<?= h(app_system_url('admin/technician_add.php')) ?>';

  function setError(input,box,msg){
    const wrap=input.closest('.staff-input-wrap');
    box.textContent=msg;
    box.classList.toggle('is-visible',!!msg);
    wrap.classList.toggle('has-live-error',!!msg);
    input.dataset.duplicate=msg?'1':'0';
  }

  async function check(field,input,box){
    const value=input.value.trim();
    if(!value) return false;
    const r=await fetch(`${url}?ajax=check_duplicate&field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}`);
    const d=await r.json();
    if(d.duplicate){
      const label=field==='tech_phone'?'เบอร์โทรศัพท์':'อีเมล';
      setError(input,box,`${label}นี้มีผู้ใช้งานแล้ว (รหัสช่าง ${d.tech_id})`);
      return true;
    }
    setError(input,box,'');
    return false;
  }

  nameInput.addEventListener('blur',function(){
    this.value=this.value.trim().replace(/\s+/g,' ');
    this.setCustomValidity(/^\S+(?:\s+\S+)+$/u.test(this.value)?'':'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
  });
  nameInput.addEventListener('input',function(){this.setCustomValidity('')});

  phoneInput.addEventListener('input',function(){
    this.value=this.value.replace(/\D/g,'').slice(0,10);
    setError(this,phoneError,'');
    if(this.value.length===10) check('tech_phone',this,phoneError);
  });
  phoneInput.addEventListener('blur',()=>check('tech_phone',phoneInput,phoneError));

  emailInput.addEventListener('input',function(){setError(this,emailError,'')});
  emailInput.addEventListener('blur',()=>check('tech_email',emailInput,emailError));

  form.addEventListener('submit',async function(e){
    e.preventDefault();
    nameInput.value=nameInput.value.trim().replace(/\s+/g,' ');
    if(!/^\S+(?:\s+\S+)+$/u.test(nameInput.value)){
      nameInput.setCustomValidity('กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
      nameInput.reportValidity();
      return;
    }
    if(!form.reportValidity()) return;
    const p=await check('tech_phone',phoneInput,phoneError);
    const m=await check('tech_email',emailInput,emailError);
    if(p||m) return;
    form.submit();
  });
});
</script>

<?php layout_footer(); ?>