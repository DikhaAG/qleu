
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header.php'; 

  // Ambil ID produk dari query string
  $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $result = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id");
  $product = mysqli_fetch_assoc($result);
?>
<?php if($product): ?>
  <div class="product-detail">
    <h2><?php echo $product['name']; ?></h2>
    <img src="/assets/images/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" />
    <p><?php echo $product['description']; ?></p>
    <p>Harga: Rp <?php echo number_format($product['price']); ?></p>
    <a href="cart.php?add=<?php echo $product['id']; ?>">+ Tambah ke Keranjang</a>
  </div>
<?php else: ?>
  <p>Produk tidak ditemukan.</p>
<?php endif; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
