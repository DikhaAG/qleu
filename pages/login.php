
<?php include_once __DIR__ . '/../includes/header.php'; ?>
<h2>User Login</h2>
<form action="login.php" method="post">
  <label>Email:</label><br/>
  <input type="email" name="email" required /><br/>
  <label>Password:</label><br/>
  <input type="password" name="password" required /><br/>
  <button type="submit">Login</button>
</form>
<p>Belum punya akun? <a href="register.php">Register</a></p>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
