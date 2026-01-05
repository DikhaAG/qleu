<?php
include_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Ambil produk contoh dari DB
$products = [];
$res = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC LIMIT 6");
while($row = mysqli_fetch_assoc($res)) $products[] = $row;

// Nama user (dummy kalau belum login)
$username = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Customer';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cafe Marketplace</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <div class="shell">
    <div class="app">
      <div class="app-inner">

        <!-- SIDEBAR -->
        <aside class="sidebar">
          <div class="brand">
            <div class="logo"></div>
            <div class="title">
              <b>Mitra Cafe</b>
              <span>Nyaman di perut</span>
            </div>
          </div>

          <div class="side-section">Marketplace</div>
          <nav class="side-nav">
            <a class="side-link active" href="/pages/index.php">Home</a>
            <a class="side-link" href="/pages/cart.php">Keranjang</a>
            <a class="side-link" href="#">Favorit</a>
          </nav>

          <div class="side-section" style="margin-top:18px;">My Account</div>
          <nav class="side-nav">
            <a class="side-link" href="#"><span>Pesanan</span><span class="badge">2</span></a>
            <a class="side-link" href="#"><span>Notifikasi</span><span class="badge">9</span></a>
            <a class="side-link" href="#">Settings</a>
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
                <b>Good Morning, <?= htmlspecialchars($username) ?></b>
                <span>Lengkapi profilmu. <a href="#">Edit now</a></span>
              </div>
            </div>

            <div class="top-actions">
              <div class="search">
                <span style="opacity:.55;">🔎</span>
                <input type="text" placeholder="Cari makanan / minuman..." />
              </div>
              <button class="icon-btn" title="Settings">⚙️</button>
            </div>
          </div>

          <!-- SECTION: PRODUCTS -->
          <div class="section-head">
            <h3>Menu Eksklusif Hari Ini</h3>
            <a href="#">View All</a>
          </div>

          <div class="grid-products">
            <?php foreach($products as $p): ?>
              <div class="card product-card">
                <div class="brandmark">Mitra Cafe</div>
                <div class="img">
                  <?php if(!empty($p['image'])): ?>
                    <img src="/assets/images/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                  <?php else: ?>
                    <div style="font-weight:900; opacity:.5;">No Image</div>
                  <?php endif; ?>
                </div>

                <div class="meta">
                  <div>
                    <p class="sub">Ready</p>
                    <p class="name"><?= htmlspecialchars($p['name']) ?></p>
                  </div>
                  <button class="heart" title="Favorite">♡</button>
                </div>

                <div class="price-row">
                  <div>
                    <div class="sub">Price</div>
                    <div class="price">Rp <?= number_format((int)$p['price'], 0, ',', '.') ?></div>
                  </div>
                  <a class="btn" href="/pages/cart.php?add=<?= (int)$p['id'] ?>">Buy Now</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- SECTION: STORES / PROMO CARDS -->
          <div class="section-head" style="margin-top:24px;">
            <h3>Best Store In This Month</h3>
          </div>

          <div class="grid-stores">
            <div class="card store-card">
              <div class="store-top">
                <div class="left">
                  <div class="store-logo">MC</div>
                  <div>
                    <p class="store-name">Mitra Cafe ✅</p>
                    <p class="store-handle">@mitracafe</p>
                  </div>
                </div>
                <div class="store-actions">
                  <a class="link" href="#">Visit Store</a>
                  <button class="btn outline">Following</button>
                </div>
              </div>
              <div class="kpis">
                <div class="kpi"><b>128</b><span>Total menu</span></div>
                <div class="kpi"><b>4.8</b><span>Rating</span></div>
                <div class="kpi"><b>24m</b><span>Avg delivery</span></div>
              </div>
            </div>

            <div class="card store-card">
              <div class="store-top">
                <div class="left">
                  <div class="store-logo">PR</div>
                  <div>
                    <p class="store-name">Promo Rame ✅</p>
                    <p class="store-handle">@promo</p>
                  </div>
                </div>
                <div class="store-actions">
                  <a class="link" href="#">Visit</a>
                  <button class="btn">+ Follow</button>
                </div>
              </div>
              <div class="kpis">
                <div class="kpi"><b>12</b><span>Promo aktif</span></div>
                <div class="kpi"><b>5.7k</b><span>Followers</span></div>
                <div class="kpi"><b>Food</b><span>Category</span></div>
              </div>
            </div>
          </div>

        </main>

      </div>
    </div>
  </div>
</body>
</html>
