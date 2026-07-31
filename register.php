<?php
require_once __DIR__ . '/db.php';

function make_customer_id(mysqli $conn): string
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

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $user_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสลูกค้าได้');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(app_public_url('register.html'));
}

$user_id = make_customer_id($conn);
$user_name = trim($_POST['user_name'] ?? '');
$user_phone = trim($_POST['user_phone'] ?? '');
$user_email = trim($_POST['user_email'] ?? '');
$user_address = trim($_POST['user_address'] ?? '');
$user_password = trim($_POST['user_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// ลูกค้า
$user_role = 0;

if (
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $user_address === '' ||
    $user_password === '' ||
    $confirm_password === ''
) {
    redirect_to(app_public_url('register.html?error=empty'));
}

if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    redirect_to(app_public_url('register.html?error=phone'));
}

if (!filter_var($user_email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $user_email)) {
    redirect_to(app_public_url('register.html?error=email'));
}

if ($user_password !== $confirm_password) {
    redirect_to(app_public_url('register.html?error=password'));
}

try {
    $check = $conn->prepare("
        SELECT user_id, user_name, user_phone, user_email
        FROM `user`
        WHERE user_name = ?
           OR user_phone = ?
           OR user_email = ?
        LIMIT 1
    ");

    $check->bind_param(
        'sss',
        $user_name,
        $user_phone,
        $user_email
    );

    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
        $duplicate = $check_result->fetch_assoc();

        if ($duplicate['user_name'] === $user_name) {
            redirect_to(app_public_url('register.html?error=duplicate_name'));
        }

        if ($duplicate['user_phone'] === $user_phone) {
            redirect_to(app_public_url('register.html?error=duplicate_phone'));
        }

        if ($duplicate['user_email'] === $user_email) {
            redirect_to(app_public_url('register.html?error=duplicate_email'));
        }

        redirect_to(app_public_url('register.html?error=duplicate'));
    }

    $stmt = $conn->prepare("
        INSERT INTO `user`
        (user_id, user_name, user_password, user_phone, user_email, user_address, user_role)
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

    redirect_to(app_public_url('login.html?success=register'));
} catch (Throwable $e) {
    redirect_to(app_public_url('register.html?error=server'));
}