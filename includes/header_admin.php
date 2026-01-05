
<!-- includes/header_admin.php -->
<?php 
  if(session_status() === PHP_SESSION_NONE) session_start();
  // Cek bila admin belum login, redirect ke admin/login.php
  if(!isset($_SESSION['admin'])) {
    header("Location: ../admin/login.php");
    exit;
  }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Panel - Cafe Marketplace</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
<header>
  <h1>Admin Panel</h1>
  <nav>
    <a href="../admin/dashboard.php">Dashboard</a>
    <a href="../admin/products.php">Products</a>
    <a href="../admin/orders.php">Orders</a>
    <a href="../admin/users.php">Users</a>
    <a href="../admin/profile.php">Profile</a>
    <a href="../admin/logout.php">Logout</a>
  </nav>
</header>
<main>
