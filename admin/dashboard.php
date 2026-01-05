<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Guard: admin wajib login
 */
if (!isset($_SESSION['admin']['id'])) {
    header('Location: ./login.php');
    exit;
}

// Data admin
$adminName = $_SESSION['admin']['name'] ?? 'Admin';

/**
 * =============== FILTER RANGE TANGGAL (GET) ===============
 * Parameter:
 * - start_date=YYYY-MM-DD
 * - end_date=YYYY-MM-DD
 *
 * Default: 14 hari terakhir (termasuk hari ini)
 */
function isValidDateYmd($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$today = new DateTime('today');
$defaultStart = (new DateTime('today'))->modify('-13 days'); // total 14 hari termasuk hari ini

$start_date = isset($_GET['start_date']) && isValidDateYmd($_GET['start_date'])
    ? $_GET['start_date']
    : $defaultStart->format('Y-m-d');

$end_date = isset($_GET['end_date']) && isValidDateYmd($_GET['end_date'])
    ? $_GET['end_date']
    : $today->format('Y-m-d');

// pastikan start <= end
if ($start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

// range waktu untuk query
$startDT = $start_date . ' 00:00:00';
$endDTExclusive = (new DateTime($end_date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; // eksklusif

$startDT_esc = mysqli_real_escape_string($conn, $startDT);
$endDT_esc   = mysqli_real_escape_string($conn, $endDTExclusive);

/**
 * =============== KPI COUNTS (SEMUA TER-FILTER RANGE) ===============
 * orders: filter berdasarkan orders.order_date ✅
 * products: filter berdasarkan products.created_at (jika ada) ✅
 * users: filter berdasarkan users.created_at (jika ada) ✅
 *
 * Kalau kolom created_at tidak ada, otomatis fallback ke overall (biar tidak error).
 */
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}

$productsHasCreatedAt = hasColumn($conn, 'products', 'created_at');
$usersHasCreatedAt    = hasColumn($conn, 'users', 'created_at');

// Total Produk (range kalau ada created_at)
if ($productsHasCreatedAt) {
    $q = mysqli_query($conn, "
      SELECT COUNT(*) AS cnt
      FROM products
      WHERE created_at >= '$startDT_esc' AND created_at < '$endDT_esc'
    ");
    $count_products = (int)(mysqli_fetch_assoc($q)['cnt'] ?? 0);
} else {
    $count_products = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products"))['cnt'] ?? 0);
}

// Total Order (range)
$qCountOrders = mysqli_query($conn, "
  SELECT COUNT(*) AS cnt
  FROM orders
  WHERE order_date >= '$startDT_esc' AND order_date < '$endDT_esc'
");
$count_orders_range = $qCountOrders ? (int)(mysqli_fetch_assoc($qCountOrders)['cnt'] ?? 0) : 0;

// Total Pelanggan (range kalau ada created_at)
if ($usersHasCreatedAt) {
    $q = mysqli_query($conn, "
      SELECT COUNT(*) AS cnt
      FROM users
      WHERE created_at >= '$startDT_esc' AND created_at < '$endDT_esc'
    ");
    $count_users = (int)(mysqli_fetch_assoc($q)['cnt'] ?? 0);
} else {
    $count_users = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users"))['cnt'] ?? 0);
}

/**
 * =============== CHART DATA: jumlah qty product per tanggal ===============
 * Query: SUM(order_items.qty) grouped by DATE(orders.order_date)
 */
$qtyByDate = []; // ['YYYY-MM-DD' => total_qty]
$qChart = mysqli_query($conn, "
  SELECT DATE(o.order_date) AS d, COALESCE(SUM(oi.qty), 0) AS total_qty
  FROM orders o
  LEFT JOIN order_items oi ON oi.order_id = o.id
  WHERE o.order_date >= '$startDT_esc' AND o.order_date < '$endDT_esc'
  GROUP BY DATE(o.order_date)
  ORDER BY d ASC
");

if ($qChart) {
    while ($r = mysqli_fetch_assoc($qChart)) {
        $qtyByDate[$r['d']] = (int)$r['total_qty'];
    }
}

// bikin deret tanggal lengkap (biar kalau tidak ada order di suatu hari tetap muncul 0)
$labels = [];
$values = [];

$cursor = new DateTime($start_date);
$endObj = new DateTime($end_date);

while ($cursor <= $endObj) {
    $d = $cursor->format('Y-m-d');
    $labels[] = $d;
    $values[] = $qtyByDate[$d] ?? 0;
    $cursor->modify('+1 day');
}

/**
 * =============== Latest Orders (dalam range) ===============
 */
$latestOrders = [];
$qOrders = mysqli_query($conn, "
  SELECT o.id, o.order_date, o.total_amount, o.status, u.name AS customer
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.order_date >= '$startDT_esc' AND o.order_date < '$endDT_esc'
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

  <!-- Chart.js (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    .kpi-grid{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: var(--gap);
      margin-top: 14px;
    }
    .kpi-card{ padding: 18px; }
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
      line-height: 1.6;
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
    .qa:hover{ border-color: rgba(47,107,255,.25); }
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

    .filter-bar{
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 8px;
      padding: 12px 14px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
    }
    .filter-bar .left{
      display:flex;
      align-items:center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .filter-bar label{
      font-size: 12px;
      font-weight: 900;
      color: var(--muted);
    }
    .filter-bar input[type="date"]{
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      outline:none;
      font-weight: 800;
      font-size: 12px;
    }
    .filter-bar .right{
      display:flex;
      gap: 10px;
      align-items:center;
      flex-wrap: wrap;
    }

    .chart-wrap{
      height: 280px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      padding: 12px;
    }
    .chart-note{
      margin-top: 10px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.6;
    }

    @media (max-width: 1100px){
      .kpi-grid{ grid-template-columns: repeat(2, 1fr); }
      .grid-2{ grid-template-columns: 1fr; }
      .chart-wrap{ height: 260px; }
    }
    @media (max-width: 860px){
      .kpi-grid{ grid-template-columns: 1fr; }
      .chart-wrap{ height: 240px; }
      .filter-bar{ align-items: stretch; flex-direction: column; }
      .filter-bar .right{ justify-content:flex-end; }
    }
  </style>
</head>
<body>

  <div class="shell">
    <div class="app">
      <div class="app-inner">

        <!-- SIDEBAR (Admin version) -->
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
            <a class="side-link active" href="./dashboard.php">Dashboard</a>
            <a class="side-link" href="./products.php">Produk</a>
            <a class="side-link" href="./orders.php">Transaksi</a>
            <a class="side-link" href="./users.php">Pelanggan</a>
            <a class="side-link" href="./profile.php">Profil</a>
          </nav>

          <div class="side-section" style="margin-top:18px;">Shortcut</div>
          <nav class="side-nav">
            <a class="side-link" href="../pages/index.php">Lihat Toko</a>
            <a class="side-link" href="./logout.php">Logout</a>
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
                <span>Ringkasan toko berdasarkan rentang tanggal.</span>
              </div>
            </div>

            <div class="top-actions">
              <button class="icon-btn" title="Settings">⚙️</button>
            </div>
          </div>

          <!-- FILTER RANGE -->
          <form class="filter-bar" method="GET" action="./dashboard.php">
            <div class="left">
              <label>Filter tanggal</label>
              <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
              <span style="opacity:.5; font-weight:900;">→</span>
              <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
            </div>
            <div class="right">
              <button class="btn" type="submit">Terapkan</button>
              <a class="btn outline" href="./dashboard.php" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            </div>
          </form>

          <!-- KPI Cards -->
          <div class="section-head">
            <h3>Overview</h3>
            <a href="./orders.php">Lihat transaksi</a>
          </div>

          <div class="kpi-grid">
            <div class="card kpi-card">
              <p class="label">Total Produk (Range)</p>
              <p class="value"><?= number_format($count_products, 0, ',', '.') ?></p>
              <p class="hint">
                <?= $productsHasCreatedAt
                    ? 'Produk dibuat pada rentang tanggal.'
                    : 'Tabel products tidak punya created_at, jadi ini total keseluruhan.'; ?>
              </p>
            </div>

            <div class="card kpi-card">
              <p class="label">Total Order (Range)</p>
              <p class="value"><?= number_format($count_orders_range, 0, ',', '.') ?></p>
              <p class="hint">Order dari <?= htmlspecialchars($start_date) ?> s/d <?= htmlspecialchars($end_date) ?>.</p>
            </div>

            <div class="card kpi-card">
              <p class="label">Total Pelanggan (Range)</p>
              <p class="value"><?= number_format($count_users, 0, ',', '.') ?></p>
              <p class="hint">
                <?= $usersHasCreatedAt
                    ? 'Pelanggan daftar pada rentang tanggal.'
                    : 'Tabel users tidak punya created_at, jadi ini total keseluruhan.'; ?>
              </p>
            </div>
          </div>

          <!-- Content 2 columns -->
          <div class="grid-2">

            <!-- Chart Orders -->
            <div class="card" style="padding:18px;">
              <div class="section-head" style="margin:0 0 10px;">
                <h3>Order per Hari (Total Qty Produk)</h3>
                <a href="./orders.php">Kelola</a>
              </div>

              <div class="chart-wrap">
                <canvas id="ordersChart"></canvas>
              </div>

              <div class="chart-note">
                Grafik menampilkan <b>jumlah total item</b> yang dipesan per hari (SUM <code>order_items.qty</code>) pada rentang tanggal.
              </div>

              <!-- Latest orders small list -->
              <div class="section-head" style="margin:16px 0 10px;">
                <h3 style="font-size:14px;">Order Terbaru (Range)</h3>
                <a href="./orders.php">Detail</a>
              </div>

              <?php if (count($latestOrders) === 0): ?>
                <div style="opacity:.65; padding:14px; text-align:center;">
                  Tidak ada order pada rentang tanggal ini.
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
                <a href="./products.php">Manage</a>
              </div>

              <div class="quick-actions">
                <a class="qa" href="./product_add.php">
                  <span class="dot"></span> Tambah Produk
                </a>
                <a class="qa" href="./products.php">
                  <span class="dot"></span> Edit Produk
                </a>
                <a class="qa" href="./orders.php">
                  <span class="dot"></span> Cek Transaksi
                </a>
                <a class="qa" href="../pages/index.php">
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

<script>
  const chartLabels = <?= json_encode($labels, JSON_UNESCAPED_SLASHES) ?>;
  const chartValues = <?= json_encode($values, JSON_UNESCAPED_SLASHES) ?>;

  const ctx = document.getElementById('ordersChart');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'Total Qty Produk / Hari',
        data: chartValues,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
        borderWidth: 2,
        fill: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true } },
      scales: {
        x: {
          ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 }
        }
      }
    }
  });
</script>

</body>
</html>
