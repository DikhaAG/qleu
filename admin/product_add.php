
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
?>
<h2>Tambah Produk Baru</h2>
<form action="product_add.php" method="post" enctype="multipart/form-data">
  <label>Nama Produk:</label><br/>
  <input type="text" name="name" required /><br/>
  <label>Deskripsi:</label><br/>
  <textarea name="description" required></textarea><br/>
  <label>Harga:</label><br/>
  <input type="number" name="price" required /><br/>
  <label>Stok:</label><br/>
  <input type="number" name="stock" required /><br/>
  <label>Gambar Produk:</label><br/>
  <input type="file" name="image" accept="image/*" /><br/><br/>
  <button type="submit">Simpan</button>
</form>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
