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

        if ($stmt->get_result()->num_rows === 0) {
            return $user_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสลูกค้าได้');
}

function normalize_full_name(string $name): string
{
    return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
}

function is_valid_full_name(string $name): bool
{
    return (bool) preg_match('/^\S+(?:\s+\S+)+$/u', $name);
}

function is_valid_password_policy(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

function register_redirect(string $error): void
{
    redirect_to(app_public_url('register.html?error=' . urlencode($error)));
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['ajax'] ?? '') === 'check_duplicate'
) {
    header('Content-Type: application/json; charset=utf-8');

    $field = trim($_GET['field'] ?? '');
    $value = trim($_GET['value'] ?? '');

    if ($value === '' || !in_array($field, ['user_phone', 'user_email'], true)) {
        echo json_encode(['duplicate' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($field === 'user_phone') {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!preg_match('/^[0-9]{10}$/', $value)) {
            echo json_encode(['duplicate' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT user_id
            FROM `user`
            WHERE user_phone = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $value);
    } else {
        $value = strtolower(trim($value));

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['duplicate' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT user_id
            FROM `user`
            WHERE LOWER(user_email) = ?
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(app_public_url('register.html'));
}

$user_id = make_customer_id($conn);
$user_name = normalize_full_name($_POST['user_name'] ?? '');
$user_phone = preg_replace('/\D+/', '', trim($_POST['user_phone'] ?? '')) ?? '';
$user_email = strtolower(trim($_POST['user_email'] ?? ''));
$address_detail = trim($_POST['address_detail'] ?? '');
$province_id = (int) ($_POST['province_id'] ?? 0);
$district_id = (int) ($_POST['district_id'] ?? 0);
$sub_district_id = (int) ($_POST['sub_district_id'] ?? 0);
$zip_code = trim($_POST['zip_code'] ?? '');
$user_password = trim($_POST['user_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Public registration is always customer role.
$user_role = 0;

if (
    $user_name === '' ||
    $user_phone === '' ||
    $user_email === '' ||
    $address_detail === '' ||
    $province_id <= 0 ||
    $district_id <= 0 ||
    $sub_district_id <= 0 ||
    $zip_code === '' ||
    $user_password === '' ||
    $confirm_password === ''
) {
    register_redirect('empty');
}

if (!is_valid_full_name($user_name)) {
    register_redirect('name');
}

if (!preg_match('/^[0-9]{10}$/', $user_phone)) {
    register_redirect('phone');
}

if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    register_redirect('email');
}

if (!is_valid_password_policy($user_password)) {
    register_redirect('weak_password');
}

if ($user_password !== $confirm_password) {
    register_redirect('password');
}

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

    $address = $addr_stmt->get_result()->fetch_assoc();

    if (!$address) {
        register_redirect('address');
    }

    $zip_code = (string) ($address['zip_code'] ?: $zip_code);
    $user_address =
        $address_detail .
        ' ต.' . $address['sub_district_name'] .
        ' อ.' . $address['district_name'] .
        ' จ.' . $address['province_name'] .
        ' ' . $zip_code;

    $check_phone = $conn->prepare("
        SELECT user_id
        FROM `user`
        WHERE user_phone = ?
        LIMIT 1
    ");
    $check_phone->bind_param('s', $user_phone);
    $check_phone->execute();

    if ($check_phone->get_result()->num_rows > 0) {
        register_redirect('duplicate_phone');
    }

    $check_email = $conn->prepare("
        SELECT user_id
        FROM `user`
        WHERE LOWER(user_email) = ?
        LIMIT 1
    ");
    $check_email->bind_param('s', $user_email);
    $check_email->execute();

    if ($check_email->get_result()->num_rows > 0) {
        register_redirect('duplicate_email');
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
    register_redirect('server');
}
