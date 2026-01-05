<?php
require_once __DIR__ . '/../config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT * FROM products";

if ($q !== '') {
    $safe = mysqli_real_escape_string($conn, $q);
    $sql .= " WHERE name LIKE '%$safe%'";
}

$sql .= " ORDER BY id DESC LIMIT 12";

$result = mysqli_query($conn, $sql);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
