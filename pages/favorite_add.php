
<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode([
        'ok' => false,
        'code' => 'NOT_LOGGED_IN',
        'message' => 'Kamu harus login dulu.',
        'redirect' => '/pages/login.php'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Product tidak valid.']);
    exit;
}

// cek produk ada
$check = mysqli_query($conn, "SELECT id FROM products WHERE id = $product_id LIMIT 1");
if (!$check || mysqli_num_rows($check) === 0) {
    echo json_encode(['ok' => false, 'message' => 'Produk tidak ditemukan.']);
    exit;
}

// insert favorite (abaikan jika sudah ada)
$sql = "INSERT INTO favorites (user_id, product_id) VALUES ($user_id, $product_id)
        ON DUPLICATE KEY UPDATE created_at = created_at";

$ok = mysqli_query($conn, $sql);

if ($ok) {
    echo json_encode(['ok' => true, 'message' => 'Berhasil menambahkan ke Favorite.']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Gagal menambahkan Favorite.']);
}
