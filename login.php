<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(app_public_url('login.html'));
}

$login_email = trim($_POST['user_id'] ?? '');
$password = trim($_POST['user_password'] ?? '');
$login_type = trim($_POST['login_type'] ?? '');

if ($login_email === '' || $password === '' || $login_type === '') {
    redirect_to(app_public_url('login.html?error=invalid'));
}

if (!filter_var($login_email, FILTER_VALIDATE_EMAIL)) {
    redirect_to(app_public_url('login.html?error=invalid'));
}

try {
    /*
      ช่างติดตั้ง
      ใช้ตาราง technicians
      login ด้วย tech_email + tech_password
    */
    if ($login_type === 'technician') {
        $stmt = $conn->prepare("
            SELECT tech_id, tech_name, tech_email, tech_status
            FROM technicians
            WHERE tech_email = ?
              AND tech_password = ?
            LIMIT 1
        ");

        $stmt->bind_param('ss', $login_email, $password);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            redirect_to(app_public_url('login.html?error=invalid'));
        }

        $tech = $result->fetch_assoc();

        $_SESSION['logged_in'] = true;
        $_SESSION['login_type'] = 'technician';
        $_SESSION['user_id'] = $tech['tech_id'];
        $_SESSION['user_name'] = $tech['tech_name'];
        $_SESSION['user_email'] = $tech['tech_email'];
        $_SESSION['user_role'] = 'technician';

        redirect_to(app_system_url('technician/index.php'));
    }

    /*
      ผู้ใช้ทั่วไป
      0 = ลูกค้า
      1 = หัวหน้าช่าง
      2 = พนักงานการเงิน
      3 = ผู้ดูแลระบบ
    */
    if (!in_array($login_type, ['0', '1', '2', '3'], true)) {
        redirect_to(app_public_url('login.html?error=invalid'));
    }

    $user_role = (int) $login_type;

    $stmt = $conn->prepare("
        SELECT user_id, user_name, user_email, user_role
        FROM `user`
        WHERE user_email = ?
          AND user_password = ?
          AND user_role = ?
        LIMIT 1
    ");

    $stmt->bind_param('ssi', $login_email, $password, $user_role);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        redirect_to(app_public_url('login.html?error=invalid'));
    }

    $user = $result->fetch_assoc();

    $_SESSION['logged_in'] = true;
    $_SESSION['login_type'] = 'user';
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['user_email'] = $user['user_email'];
    $_SESSION['user_role'] = (string) $user['user_role'];

    if ((int) $user['user_role'] === 0) {
        redirect_to(app_system_url('customer/index.php'));
    }

    if ((int) $user['user_role'] === 1) {
        redirect_to(app_system_url('manager/index.php'));
    }

    if ((int) $user['user_role'] === 2) {
        redirect_to(app_system_url('finance/index.php'));
    }

    if ((int) $user['user_role'] === 3) {
        redirect_to(app_system_url('admin/index.php'));
    }

    redirect_to(app_public_url('login.html?error=invalid'));
} catch (Throwable $e) {
    redirect_to(app_public_url('login.html?error=server'));
}