
<?php // Halaman login admin (tanpa header_admin, karena belum login)
  session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <h2>Login Admin</h2>
  <form action="login.php" method="post">
    <label>Username:</label><br/>
    <input type="text" name="username" required /><br/>
    <label>Password:</label><br/>
    <input type="password" name="password" required /><br/>
    <button type="submit">Login</button>
  </form>
  <!-- (Proses login: cek username/password admin di database, lalu set $_SESSION['admin'] dan redirect ke dashboard) -->
</body>
</html>
