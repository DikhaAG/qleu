
<?php include_once __DIR__ . '/../includes/header.php'; ?>
<h2>User Registration</h2>
<form action="register.php" method="post">
  <label>Nama:</label><br/>
  <input type="text" name="name" required /><br/>
  <label>Email:</label><br/>
  <input type="email" name="email" required /><br/>
  <label>Password:</label><br/>
  <input type="password" name="password" required /><br/>
  <button type="submit">Register</button>
</form>
<p>Sudah punya akun? <a href="login.php">Login</a></p>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
