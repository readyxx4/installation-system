<?php
require_once __DIR__ . '/helpers.php';

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "installation_system";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    redirect_to(app_public_url('login.html?error=server'));
}