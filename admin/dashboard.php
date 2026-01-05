
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
?>
<h2>Dashboard</h2>
<div class="stats">
  <?php 
    // Contoh: hitung jumlah produk, order, user dari DB
    $count_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products"))['cnt'];
    $count_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders"))['cnt'];
    $count_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users"))['cnt'];
  ?>
  <p>Total Produk: <strong><?php echo $count_products; ?></strong></p>
  <p>Total Order: <strong><?php echo $count_orders; ?></strong></p>
  <p>Total Pelanggan: <strong><?php echo $count_users; ?></strong></p>
</div>
<p>Selamat datang, admin! Gunakan menu di atas untuk mengelola toko.</p>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
