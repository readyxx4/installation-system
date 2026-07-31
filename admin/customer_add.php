<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function ensure_user_fullname_column(mysqli $conn): void
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = 'user'\n          AND COLUMN_NAME = 'user_fullname'\n        LIMIT 1\n    ");
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $conn->query("ALTER TABLE `user` ADD COLUMN user_fullname VARCHAR(100) NOT NULL DEFAULT '' AFTER user_name");
    }
}

function make_customer_id(mysqli $conn): string
{
    $prefix = 'USR-2569-';

    for ($i = 1; $i <= 9999; $i++) {
        $running_no = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $user_id = $prefix . $running_no;

        $stmt = $conn->prepare("SELECT user_id FROM `user` WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $user_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสลูกค้าใหม่ได้');
}

ensure_user_fullname_column($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $user_name = trim($_POST['user_name'] ?? '');
    $user_fullname = trim($_POST['user_fullname'] ?? '');
    $user_phone = trim($_POST['user_phone'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');

    $user_role = 0;
    $user_password = '1234';

    if (
        $user_id === '' ||
        $user_name === '' ||
        $user_fullname === '' ||
        $user_phone === '' ||
        $user_email === '' ||
        $user_address === ''
    ) {
        redirect_to(app_system_url('admin/customer_add.php?status=error'));
    }

    if (!preg_match('/^USR-2569-[0-9]{4}$/', $user_id)) {
        redirect_to(app_system_url('admin/customer_add.php?status=error'));
    }

    if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
        redirect_to(app_system_url('admin/customer_add.php?status=phone'));
    }

    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $user_email)) {
        redirect_to(app_system_url('admin/customer_add.php?status=email'));
    }

    try {
        $check = $conn->prepare("\n            SELECT user_id, user_name, user_phone, user_email\n            FROM `user`\n            WHERE user_id = ?\n               OR user_name = ?\n               OR user_phone = ?\n               OR user_email = ?\n            LIMIT 1\n        ");

        $check->bind_param('ssss', $user_id, $user_name, $user_phone, $user_email);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $duplicate = $check_result->fetch_assoc();

            if ($duplicate['user_id'] === $user_id) {
                redirect_to(app_system_url('admin/customer_add.php?status=duplicate_id'));
            }

            if ($duplicate['user_name'] === $user_name) {
                redirect_to(app_system_url('admin/customer_add.php?status=duplicate_name'));
            }

            if ($duplicate['user_phone'] === $user_phone) {
                redirect_to(app_system_url('admin/customer_add.php?status=duplicate_phone'));
            }

            if ($duplicate['user_email'] === $user_email) {
                redirect_to(app_system_url('admin/customer_add.php?status=duplicate_email'));
            }

            redirect_to(app_system_url('admin/customer_add.php?status=duplicate'));
        }

        $stmt = $conn->prepare("\n            INSERT INTO `user`\n            (user_id, user_name, user_fullname, user_password, user_phone, user_email, user_address, user_role)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n        ");

        $stmt->bind_param(
            'sssssssi',
            $user_id,
            $user_name,
            $user_fullname,
            $user_password,
            $user_phone,
            $user_email,
            $user_address,
            $user_role
        );

        $stmt->execute();

        redirect_to(app_system_url('admin/customers.php?status=created'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/customer_add.php?status=error'));
    }
}

$default_customer_id = make_customer_id($conn);

layout_header('เพิ่มข้อมูลลูกค้า', 'customers');
page_head('เพิ่มข้อมูลลูกค้า');
?>

<?= flash_message() ?>

<div class="panel user-add-panel">
  <div class="panel-title user-add-title">เพิ่มข้อมูลลูกค้าใหม่</div>

  <form class="admin-form user-add-form" method="POST" action="<?= h(app_system_url('admin/customer_add.php')) ?>">
    <div>
      <label for="user_id">รหัสลูกค้า</label>
      <input type="text" id="user_id" name="user_id" maxlength="13" value="<?= h($default_customer_id) ?>" readonly required>
    </div>

    <div>
      <label for="user_name">ชื่อผู้ใช้</label>
      <input type="text" id="user_name" name="user_name" maxlength="50" placeholder="กรอกชื่อผู้ใช้สำหรับแสดงในระบบ" required>
    </div>

    <div>
      <label for="user_fullname">ชื่อ-นามสกุลจริง</label>
      <input type="text" id="user_fullname" name="user_fullname" maxlength="100" placeholder="กรอกชื่อ-นามสกุลจริง" required>
    </div>

    <div>
      <label for="user_email">อีเมล</label>
      <input type="email" id="user_email" name="user_email" maxlength="50" placeholder="example@gmail.com" required>
    </div>

    <div>
      <label for="user_phone">เบอร์โทรศัพท์</label>
      <input type="text" id="user_phone" name="user_phone" maxlength="10" placeholder="เช่น 0812345678" required>
    </div>

    <div>
      <label>สิทธิ์การใช้งาน</label>
      <input type="text" value="ลูกค้า" readonly>
    </div>

    <div>
      <label>รหัสผ่านเริ่มต้น</label>
      <input type="text" value="1234" readonly>
    </div>

    <div class="full">
      <label for="user_address">ที่อยู่</label>
      <textarea id="user_address" name="user_address" placeholder="กรอกที่อยู่" required></textarea>
    </div>

    <div class="form-actions">
      <a class="btn-secondary" href="<?= h(app_system_url('admin/customers.php')) ?>">ยกเลิก</a>
      <button class="btn" type="submit">เพิ่มข้อมูล</button>
    </div>
  </form>
</div>

<?php
layout_footer();