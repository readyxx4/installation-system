<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset('utf8mb4');

$result = $conn->query("
  SELECT id, name_th
  FROM provinces
  ORDER BY name_th ASC
");

$data = [];

while ($row = $result->fetch_assoc()) {
  $data[] = [
    'id' => (string) $row['id'],
    'name_th' => $row['name_th'],
  ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
