
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header_admin.php'; 
  // Misal ambil data admin saat ini dari DB (dengan id tersimpan di session)
?>
<h2>Profil Admin</h2>
<form action="profile.php" method="post">
  <label>Nama Admin:</label><br/>
  <input type="text" name="name" value="<?php echo $currentAdminName; ?>" /><br/>
  <label>Password Baru (kosongkan jika tidak ganti):</label><br/>
  <input type="password" name="new_password" /><br/>
  <button type="submit">Update Profile</button>
</form>
<?php include_once __DIR__ . '/../includes/footer_admin.php'; ?>
