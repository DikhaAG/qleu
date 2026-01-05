
<!-- includes/header.php -->
<?php 
  // Memulai session (jika diperlukan untuk cek login)
  if(session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cafe Marketplace</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
<header>
  <h1>My Cafe</h1>
  <nav>
    <a href="/pages/index.php">Home</a>
    <a href="/pages/cart.php">Cart</a>
    <?php if(isset($_SESSION['user'])): ?>
      <a href="/pages/profile.php">Profile</a>
      <a href="/pages/logout.php">Logout</a>
    <?php else: ?>
      <a href="/pages/login.php">Login</a>
      <a href="/pages/register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main>
