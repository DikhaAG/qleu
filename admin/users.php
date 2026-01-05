
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
?>
<h2>Daftar Pengguna Terdaftar</h2>
<table border="1" cellspacing="0" cellpadding="5">
  <tr><th>User ID</th><th>Nama</th><th>Email</th><th>Tgl Daftar</th></tr>
  <?php 
    $result = mysqli_query($conn, "SELECT id, name, email, created_at FROM users");
    while($user = mysqli_fetch_assoc($result)):
  ?>
  <tr>
    <td><?php echo $user['id']; ?></td>
    <td><?php echo $user['name']; ?></td>
    <td><?php echo $user['email']; ?></td>
    <td><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></td>
  </tr>
  <?php endwhile; ?>
</table>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
