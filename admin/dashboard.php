<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Guard: admin wajib login
 */
if (!isset($_SESSION['admin']['id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Data admin
$adminName = $_SESSION['admin']['name'] ?? 'Admin';

// KPI counts
$count_products = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products"))['cnt'] ?? 0);
$count_orders   = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders"))['cnt'] ?? 0);
$count_users    = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users"))['cnt'] ?? 0);

// Optional: order terbaru (kalau tabel orders/user sudah ada)
$latestOrders = [];
$qOrders = mysqli_query($conn, "
  SELECT o.id, o.order_date, o.total_amount, o.status, u.name AS customer
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  ORDER BY o.order_date DESC
  LIMIT 6
");

if ($qOrders) {
  while ($r = mysqli_fetch_assoc($qOrders)) $latestOrders[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - Mitra Cafe</title>
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
    /* Tambahan kecil khusus dashboard admin (masih satu rasa UI) */
    .kpi-grid{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: var(--gap);
      margin-top: 14px;
    }
    .kpi-card{
      padding: 18px;
    }
    .kpi-card .label{
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      margin: 0 0 8px;
    }
    .kpi-card .value{
      font-size: 26px;
      font-weight: 950;
      margin: 0;
      letter-spacing: .2px;
    }
    .kpi-card .hint{
      margin: 8px 0 0;
      font-size: 12px;
      color: var(--muted);
    }

    .grid-2{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap: var(--gap);
      margin-top: 18px;
    }

    .quick-actions{
      display:flex;
      flex-wrap:wrap;
      gap: 10px;
      margin-top: 12px;
    }
    .qa{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
      text-decoration:none;
      color: #111827;
      font-weight: 850;
      font-size: 13px;
    }
    .qa:hover{
      border-color: rgba(47,107,255,.25);
    }
    .qa .dot{
      width: 10px; height: 10px;
      border-radius: 50%;
      background: var(--primary);
      opacity: .8;
    }

    .table{
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
    }
    .table th, .table td{
      padding: 12px 12px;
      font-size: 13px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: top;
    }
    .table th{
      color: var(--muted);
      font-size: 12px;
      letter-spacing: .2px;
      font-weight: 900;
      background: rgba(47,107,255,.04);
    }
    .table tr:last-child td{ border-bottom: 0; }

    .status{
      display:inline-flex;
      align-items:center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 900;
      font-size: 12px;
      border: 1px solid var(--line);
      background: rgba(17,24,39,.04);
      color: #111827;
    }
    .status .s-dot{
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #111827;
      opacity: .55;
    }

    /* Responsive */
    @media (max-width: 1100px){
      .kpi-grid{ grid-template-columns: repeat(2, 1fr); }
      .grid-2{ grid-template-columns: 1fr; }
    }
    @media (max-width: 860px){
      .kpi-grid{ grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <div class="shell">
    <div class="app">
      <div class="app-inner">

        <!-- SIDEBAR (Admin version, sama gaya) -->
        <aside class="sidebar">
          <div class="brand">
            <div class="logo"></div>
            <div class="title">
              <b>Admin Panel</b>
              <span>Mitra Cafe</span>
            </div>
          </div>

          <div class="side-section">Menu</div>
          <nav class="side-nav">
            <a class="side-link active" href="/admin/dashboard.php">Dashboard</a>
            <a class="side-link" href="/admin/products.php">Produk</a>
            <a class="side-link" href="/admin/orders.php">Transaksi</a>
            <a class="side-link" href="/admin/users.php">Pelanggan</a>
            <a class="side-link" href="/admin/profile.php">Profil</a>
          </nav>

          <div class="side-section" style="margin-top:18px;">Shortcut</div>
          <nav class="side-nav">
            <a class="side-link" href="/pages/index.php">Lihat Toko</a>
            <a class="side-link" href="/admin/logout.php">Logout</a>
          </nav>

          <div class="side-bottom">
            <div class="toggle">
              <span>Light Mode</span>
              <div class="pill" title="Tema (dummy)"></div>
            </div>
          </div>
        </aside>

        <!-- MAIN -->
        <main class="main">

          <!-- TOPBAR -->
          <div class="topbar">
            <div class="greeting">
              <div class="avatar"></div>
              <div class="text">
                <b>Welcome back, <?= htmlspecialchars($adminName) ?></b>
                <span>Ringkasan toko hari ini dan aksi cepat.</span>
              </div>
            </div>

            <div class="top-actions">
              <button class="icon-btn" title="Settings">⚙️</button>
            </div>
          </div>

          <!-- KPI Cards -->
          <div class="section-head">
            <h3>Overview</h3>
            <a href="/admin/orders.php">Lihat transaksi</a>
          </div>

          <div class="kpi-grid">
            <div class="card kpi-card">
              <p class="label">Total Produk</p>
              <p class="value"><?= number_format($count_products, 0, ',', '.') ?></p>
              <p class="hint">Jumlah menu aktif di katalog.</p>
            </div>

            <div class="card kpi-card">
              <p class="label">Total Order</p>
              <p class="value"><?= number_format($count_orders, 0, ',', '.') ?></p>
              <p class="hint">Semua transaksi yang tercatat.</p>
            </div>

            <div class="card kpi-card">
              <p class="label">Total Pelanggan</p>
              <p class="value"><?= number_format($count_users, 0, ',', '.') ?></p>
              <p class="hint">Pengguna terdaftar.</p>
            </div>
          </div>

          <!-- Content 2 columns -->
          <div class="grid-2">

            <!-- Latest Orders -->
            <div class="card" style="padding:18px;">
              <div class="section-head" style="margin:0 0 10px;">
                <h3>Order Terbaru</h3>
                <a href="/admin/orders.php">Kelola</a>
              </div>

              <?php if (count($latestOrders) === 0): ?>
                <div style="opacity:.65; padding:18px; text-align:center;">
                  Belum ada order atau tabel orders belum terisi.
                </div>
              <?php else: ?>
                <table class="table">
                  <thead>
                    <tr>
                      <th>#ID</th>
                      <th>Pelanggan</th>
                      <th>Tanggal</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($latestOrders as $o): ?>
                      <tr>
                        <td><b>#<?= (int)$o['id'] ?></b></td>
                        <td><?= htmlspecialchars($o['customer'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($o['order_date'] ?? 'now'))) ?></td>
                        <td>Rp <?= number_format((int)($o['total_amount'] ?? 0), 0, ',', '.') ?></td>
                        <td>
                          <span class="status">
                            <span class="s-dot"></span>
                            <?= htmlspecialchars($o['status'] ?? 'Pending') ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card" style="padding:18px;">
              <div class="section-head" style="margin:0 0 10px;">
                <h3>Quick Actions</h3>
                <a href="/admin/products.php">Manage</a>
              </div>

              <div class="quick-actions">
                <a class="qa" href="/admin/product_add.php">
                  <span class="dot"></span> Tambah Produk
                </a>
                <a class="qa" href="/admin/products.php">
                  <span class="dot"></span> Edit Produk
                </a>
                <a class="qa" href="/admin/orders.php">
                  <span class="dot"></span> Cek Transaksi
                </a>
                <a class="qa" href="/pages/index.php">
                  <span class="dot"></span> Lihat Toko
                </a>
              </div>

              <div style="margin-top:14px; color:var(--muted); font-size:13px; line-height:1.6;">
                Selamat datang, admin! Gunakan menu di sidebar untuk mengelola produk, order, dan pelanggan.
              </div>
            </div>

          </div>

        </main>

      </div>
    </div>
  </div>

</body>
</html>
