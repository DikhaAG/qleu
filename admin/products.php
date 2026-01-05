
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
?>
<h2>Manajemen Produk</h2>
<p><a href="product_add.php">+ Tambah Produk</a></p>
<table border="1" cellspacing="0" cellpadding="5">
  <tr><th>ID</th><th>Nama</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
  <?php 
    $result = mysqli_query($conn, "SELECT * FROM products");
    while($prod = mysqli_fetch_assoc($result)):
  ?>
  <tr>
    <td><?php echo $prod['id']; ?></td>
    <td><?php echo $prod['name']; ?></td>
    <td>Rp <?php echo number_format($prod['price']); ?></td>
    <td><?php echo $prod['stock']; ?></td>
    <td>
      <a href="product_edit.php?id=<?php echo $prod['id']; ?>">Edit</a> | 
      <a href="products.php?delete=<?php echo $prod['id']; ?>" onclick="return confirm('Hapus produk ini?');">Hapus</a>
    </td>
  </tr>
  <?php endwhile; ?>
</table>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
