
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header.php';
  if(session_status() === PHP_SESSION_NONE) session_start();
?>
<h2>Keranjang Belanja</h2>
<?php 
if (!empty($_SESSION['cart'])):
    // Ambil list ID produk dalam cart
    $ids = implode(',', array_keys($_SESSION['cart']));
    $query = "SELECT * FROM products WHERE id IN ($ids)";
    $result = mysqli_query($conn, $query);
?>
<form action="checkout.php" method="get">
  <table>
    <tr><th>Produk</th><th>Harga</th><th>Kuantitas</th><th>Subtotal</th><th>Aksi</th></tr>
    <?php 
    $total = 0;
    while($prod = mysqli_fetch_assoc($result)):
      $id = $prod['id'];
      $qty = $_SESSION['cart'][$id];
      $subtotal = $prod['price'] * $qty;
      $total += $subtotal;
    ?>
    <tr>
      <td><?php echo $prod['name']; ?></td>
      <td>Rp <?php echo number_format($prod['price']); ?></td>
      <td>
        <input type="number" name="qty[<?php echo $id; ?>]" value="<?php echo $qty; ?>" min="1" />
      </td>
      <td>Rp <?php echo number_format($subtotal); ?></td>
      <td><a href="cart.php?remove=<?php echo $id; ?>">Hapus</a></td>
    </tr>
    <?php endwhile; ?>
    <tr>
      <td colspan="3"><strong>Total:</strong></td>
      <td><strong>Rp <?php echo number_format($total); ?></strong></td>
      <td></td>
    </tr>
  </table>
  <button type="submit">Checkout</button>
</form>
<?php else: ?>
  <p>Keranjang Anda kosong.</p>
<?php endif; ?>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
