<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
$conn->set_charset('utf8mb4');
$district_id = isset($_GET['district_id']) ? (int) $_GET['district_id'] : 0;
if ($district_id <= 0) {
  echo json_encode([], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt = $conn->prepare("SELECT id, name_th, zip_code FROM sub_districts WHERE district_id = ? ORDER BY name_th ASC");
$stmt->bind_param('i', $district_id);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = ['id' => (string) $row['id'], 'name_th' => $row['name_th'], 'zip_code' => (string) $row['zip_code']];
}
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);