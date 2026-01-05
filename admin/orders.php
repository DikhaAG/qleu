
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
?>
<h2>Manajemen Order</h2>
<table border="1" cellspacing="0" cellpadding="5">
  <tr><th>Order ID</th><th>Pelanggan</th><th>Tanggal</th><th>Total (Rp)</th><th>Status</th><th>Aksi</th></tr>
  <?php 
    $result = mysqli_query($conn, 
      "SELECT o.id, u.name as customer, o.order_date, o.total_amount, o.status 
       FROM orders o JOIN users u ON o.user_id = u.id 
       ORDER BY o.order_date DESC");
    while($order = mysqli_fetch_assoc($result)):
  ?>
  <tr>
    <td><?php echo $order['id']; ?></td>
    <td><?php echo $order['customer']; ?></td>
    <td><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></td>
    <td><?php echo number_format($order['total_amount']); ?></td>
    <td>
      <form action="orders.php" method="post" style="display:inline;">
        <select name="status" onchange="this.form.submit()">
          <option value="Pending" <?php if($order['status']=='Pending') echo 'selected'; ?>>Pending</option>
          <option value="Processed" <?php if($order['status']=='Processed') echo 'selected'; ?>>Processed</option>
          <option value="Shipped" <?php if($order['status']=='Shipped') echo 'selected'; ?>>Shipped</option>
          <option value="Completed" <?php if($order['status']=='Completed') echo 'selected'; ?>>Completed</option>
        </select>
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>" />
      </form>
    </td>
    <td><a href="order_detail.php?id=<?php echo $order['id']; ?>">Detail</a></td>
  </tr>
  <?php endwhile; ?>
</table>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
