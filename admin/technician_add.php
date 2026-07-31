<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function ensure_tech_fullname_column(mysqli $conn): void
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = 'technicians'\n          AND COLUMN_NAME = 'tech_fullname'\n        LIMIT 1\n    ");
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $conn->query("ALTER TABLE technicians ADD COLUMN tech_fullname VARCHAR(100) NOT NULL DEFAULT '' AFTER tech_name");
    }
}

function make_tech_id(mysqli $conn): string
{
    $prefix = 'TEC-2569-';

    for ($i = 1; $i <= 9999; $i++) {
        $running_no = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $tech_id = $prefix . $running_no;

        $stmt = $conn->prepare("SELECT tech_id FROM technicians WHERE tech_id = ? LIMIT 1");
        $stmt->bind_param('s', $tech_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $tech_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสช่างใหม่ได้');
}

ensure_tech_fullname_column($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
      tech_name = ชื่อผู้ใช้ที่แสดงในระบบ / ห้ามซ้ำ
      tech_fullname = ชื่อ-นามสกุลจริง / Admin ใช้ดูข้อมูล
    */
    $tech_id = trim($_POST['tech_id'] ?? '');
    $tech_name = trim($_POST['tech_name'] ?? '');
    $tech_fullname = trim($_POST['tech_fullname'] ?? '');
    $tech_phone = trim($_POST['tech_phone'] ?? '');
    $tech_email = trim($_POST['tech_email'] ?? '');
    $tech_status = trim($_POST['tech_status'] ?? '');

    $tech_password = '1234';

    if (
        $tech_id === '' ||
        $tech_name === '' ||
        $tech_fullname === '' ||
        $tech_phone === '' ||
        $tech_email === '' ||
        $tech_status === ''
    ) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }

    if (!preg_match('/^TEC-2569-[0-9]{4}$/', $tech_id)) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }

    if (!preg_match('/^[0-9]{10}$/', $tech_phone)) {
        redirect_to(app_system_url('admin/technician_add.php?status=phone'));
    }

    if (!filter_var($tech_email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $tech_email)) {
        redirect_to(app_system_url('admin/technician_add.php?status=email'));
    }

    if (!in_array($tech_status, ['0', '1'], true)) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }

    $tech_status = (int) $tech_status;

    try {
        $check = $conn->prepare("\n            SELECT tech_id, tech_name, tech_phone, tech_email\n            FROM technicians\n            WHERE tech_id = ?\n               OR tech_name = ?\n               OR tech_phone = ?\n               OR tech_email = ?\n            LIMIT 1\n        ");

        $check->bind_param('ssss', $tech_id, $tech_name, $tech_phone, $tech_email);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $duplicate = $check_result->fetch_assoc();

            if ($duplicate['tech_id'] === $tech_id) {
                redirect_to(app_system_url('admin/technician_add.php?status=duplicate_id'));
            }

            if ($duplicate['tech_name'] === $tech_name) {
                redirect_to(app_system_url('admin/technician_add.php?status=duplicate_name'));
            }

            if ($duplicate['tech_phone'] === $tech_phone) {
                redirect_to(app_system_url('admin/technician_add.php?status=duplicate_phone'));
            }

            if ($duplicate['tech_email'] === $tech_email) {
                redirect_to(app_system_url('admin/technician_add.php?status=duplicate_email'));
            }

            redirect_to(app_system_url('admin/technician_add.php?status=duplicate'));
        }

        $stmt = $conn->prepare("\n            INSERT INTO technicians\n            (tech_id, tech_name, tech_fullname, tech_password, tech_phone, tech_email, tech_status)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n        ");

        $stmt->bind_param(
            'ssssssi',
            $tech_id,
            $tech_name,
            $tech_fullname,
            $tech_password,
            $tech_phone,
            $tech_email,
            $tech_status
        );

        $stmt->execute();

        redirect_to(app_system_url('admin/technicians.php?status=created'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/technician_add.php?status=error'));
    }
}

$default_tech_id = make_tech_id($conn);


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

layout_header('เพิ่มข้อมูลช่าง', 'technicians');
?>
<?= flash_message() ?>
<div class="staff-form-page">
  <div class="staff-page-back-row"><a class="staff-back-link" href="<?= h(app_system_url('admin/technicians.php')) ?>"><?= admin_form_icon_svg('back') ?> กลับรายการช่าง</a></div>
  <form class="staff-create-card" method="POST" action="<?= h(app_system_url('admin/technician_add.php')) ?>" autocomplete="off">
    <div class="staff-create-head staff-create-head-clean"><div><h2>ข้อมูลช่างใหม่</h2></div><div class="staff-password-pill"><?= admin_form_icon_svg('lock') ?> รหัสผ่านเริ่มต้น: <strong>1234</strong></div></div>
    <div class="staff-form-grid">
      <div class="staff-field readonly-field"><label for="tech_id">รหัสช่าง *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('id') ?><input type="text" id="tech_id" name="tech_id" maxlength="13" value="<?= h($default_tech_id) ?>" readonly required></div></div>
      <div class="staff-field"><label for="tech_status">สถานะช่าง *</label><div class="staff-input-wrap select-wrap"><?= admin_form_icon_svg('role') ?><select id="tech_status" name="tech_status" required><option value="0">0: พร้อมรับงาน</option><option value="1">1: ไม่พร้อมรับงาน</option></select></div></div>
      <div class="staff-field"><label for="tech_name">ชื่อผู้ใช้ *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('user') ?><input type="text" id="tech_name" name="tech_name" maxlength="50" placeholder="เช่น tech01" required></div></div>
      <div class="staff-field"><label for="tech_fullname">ชื่อ-นามสกุลจริง *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('user') ?><input type="text" id="tech_fullname" name="tech_fullname" maxlength="100" placeholder="เช่น สมชาย ใจดี" required></div></div>
      <div class="staff-field"><label for="tech_phone">เบอร์โทรศัพท์ *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('phone') ?><input type="text" id="tech_phone" name="tech_phone" maxlength="10" inputmode="numeric" placeholder="0812345678" required></div></div>
      <div class="staff-field"><label for="tech_email">อีเมล Gmail *</label><div class="staff-input-wrap"><?= admin_form_icon_svg('mail') ?><input type="email" id="tech_email" name="tech_email" maxlength="50" placeholder="example@gmail.com" required></div></div>
    </div>
    <div class="staff-form-actions"><a class="staff-cancel-btn" href="<?= h(app_system_url('admin/technicians.php')) ?>">ยกเลิก</a><button class="staff-save-btn" type="submit"><?= admin_form_icon_svg('save') ?> เพิ่มข้อมูลช่าง</button></div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const phoneInput = document.getElementById('tech_phone');
  const emailInput = document.getElementById('tech_email');
  if (phoneInput) phoneInput.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 10); });
  if (emailInput) {
    emailInput.addEventListener('blur', function () { const value = this.value.trim(); this.setCustomValidity(value && !value.toLowerCase().endsWith('@gmail.com') ? 'กรุณากรอกอีเมลที่ลงท้ายด้วย @gmail.com' : ''); });
    emailInput.addEventListener('input', function () { this.setCustomValidity(''); });
  }
});
</script>
<?php layout_footer(); ?>