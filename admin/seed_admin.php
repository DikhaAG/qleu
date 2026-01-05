
<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$username = 'admin';
$password = 'admin123';
$name     = 'Administrator';

$u = mysqli_real_escape_string($conn, $username);

// cek sudah ada belum
$check = mysqli_query($conn, "SELECT id FROM admins WHERE username='$u' LIMIT 1");

if ($check && mysqli_num_rows($check) > 0) {
    echo "Admin sudah ada. (username: admin)\n";
    echo "Kalau mau reset password, hapus dulu row admin atau pakai Cara B.\n";
  echo "<pre>
Cara B: reset password admin via command line (lebih “opsi nerd”)
1) Generate hash bcrypt dari terminal (di folder project)

Kalau PHP CLI kamu aktif:

php -r 'echo password_hash('admin123', PASSWORD_BCRYPT), PHP_EOL;'


Copy output hash-nya.

2) Update di phpMyAdmin:
UPDATE admins
SET password_hash = 'PASTE_HASH_DISINI'
WHERE username = 'admin';


Lalu login lagi.
  </pre>";
    exit;
}

// buat hash yang valid DI SERVER KAMU
$hash = password_hash($password, PASSWORD_BCRYPT);

$h = mysqli_real_escape_string($conn, $hash);
$n = mysqli_real_escape_string($conn, $name);

$ok = mysqli_query($conn, "INSERT INTO admins (username, password_hash, name) VALUES ('$u', '$h', '$n')");

if ($ok) {
    echo "SUKSES! Admin dibuat.\n";
    echo "username: admin\n";
    echo "password: admin123\n";
    echo "\nHapus file admin/seed_admin.php setelah ini untuk keamanan.\n";
} else {
    echo "GAGAL insert admin: " . mysqli_error($conn);
}

