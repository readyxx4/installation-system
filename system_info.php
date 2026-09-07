<?php
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];

try {
    require_once __DIR__ . '/db.php';

    if (isset($conn) && $conn instanceof mysqli) {
        $result = $conn->query("SELECT system_name, system_logo, system_desc FROM system LIMIT 1");

        if ($result && $result->num_rows > 0) {
            $system = $result->fetch_assoc();
            $response = [
                'success' => true,
                'system_name' => (string)($system['system_name'] ?? ''),
                'system_logo' => (string)($system['system_logo'] ?? ''),
                'system_desc' => (string)($system['system_desc'] ?? '')
            ];
        }
    }
} catch (Throwable $e) {
    $response = ['success' => false];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
