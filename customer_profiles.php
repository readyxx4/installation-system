<?php

function customer_profiles_table_exists(mysqli $conn): bool
{
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
        LIMIT 1
    ");
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function customer_profiles_column_exists(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function ensure_customer_profiles_schema(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS customers (
            customer_id CHAR(13) NOT NULL,
            customer_password VARCHAR(255) NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone CHAR(10) NOT NULL,
            customer_email VARCHAR(100) NOT NULL,
            customer_address TEXT NOT NULL,
            customer_status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (customer_id),
            KEY idx_customers_status (customer_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        ALTER TABLE customers
        CONVERT TO CHARACTER SET utf8mb4
        COLLATE utf8mb4_general_ci
    ");

    if (!customer_profiles_column_exists($conn, 'customer_password')) {
        $conn->query("
            ALTER TABLE customers
            ADD COLUMN customer_password VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_id
        ");
        $conn->query("
            ALTER TABLE customers
            ALTER customer_password DROP DEFAULT
        ");
    }

    migrate_legacy_customer_profile_ids($conn);
}

function make_customer_profile_id(mysqli $conn): string
{
    $prefix = 'CUS-2569-';

    for ($i = 1; $i <= 9999; $i++) {
        $running_no = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $customer_id = $prefix . $running_no;

        $stmt = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE customer_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $customer_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            return $customer_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสลูกค้าได้');
}

function migrate_legacy_customer_profile_ids(mysqli $conn): void
{
    $legacy_profiles = $conn->query("
        SELECT customer_id
        FROM customers
        WHERE customer_id NOT LIKE 'CUS-2569-%'
        ORDER BY customer_id ASC
    ");

    while ($profile = $legacy_profiles->fetch_assoc()) {
        $old_customer_id = (string) $profile['customer_id'];
        $new_customer_id = make_customer_profile_id($conn);

        $stmt = $conn->prepare("
            UPDATE customers
            SET customer_id = ?
            WHERE customer_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $new_customer_id, $old_customer_id);
        $stmt->execute();
    }
}

function create_customer_profile(
    mysqli $conn,
    string $customer_password,
    string $customer_name,
    string $customer_phone,
    string $customer_email,
    string $customer_address,
    int $customer_status = 1
): string {
    $customer_id = make_customer_profile_id($conn);

    $stmt = $conn->prepare("
        INSERT INTO customers
            (customer_id, customer_password, customer_name, customer_phone, customer_email, customer_address, customer_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'ssssssi',
        $customer_id,
        $customer_password,
        $customer_name,
        $customer_phone,
        $customer_email,
        $customer_address,
        $customer_status
    );
    $stmt->execute();

    return $customer_id;
}

function upsert_customer_profile(
    mysqli $conn,
    string $customer_password,
    string $customer_name,
    string $customer_phone,
    string $customer_email,
    string $customer_address,
    int $customer_status = 1
): void {
    $customer_id = make_customer_profile_id($conn);

    $stmt = $conn->prepare("
        INSERT INTO customers
            (customer_id, customer_password, customer_name, customer_phone, customer_email, customer_address, customer_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            customer_password = VALUES(customer_password),
            customer_name = VALUES(customer_name),
            customer_phone = VALUES(customer_phone),
            customer_email = VALUES(customer_email),
            customer_address = VALUES(customer_address),
            customer_status = VALUES(customer_status)
    ");

    $stmt->bind_param(
        'ssssssi',
        $customer_id,
        $customer_password,
        $customer_name,
        $customer_phone,
        $customer_email,
        $customer_address,
        $customer_status
    );
    $stmt->execute();
}

function customer_account_status_name($status): string
{
    return ((string) $status === '0') ? 'ระงับบัญชี' : 'ใช้งานปกติ';
}

function customer_account_status_badge($status): string
{
    return ((string) $status === '0') ? 'red' : 'green';
}
