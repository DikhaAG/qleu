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

$adminName = $_SESSION['admin']['name'] ?? 'Admin';

/**
 * ===== Filter tanggal (GET) =====
 */
function isValidDateYmd($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$today = new DateTime('today');
$defaultStart = (new DateTime('today'))->modify('-13 days');

$start_date = isset($_GET['start_date']) && isValidDateYmd($_GET['start_date'])
    ? $_GET['start_date']
    : $defaultStart->format('Y-m-d');

$end_date = isset($_GET['end_date']) && isValidDateYmd($_GET['end_date'])
    ? $_GET['end_date']
    : $today->format('Y-m-d');

if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$startDT = $start_date . ' 00:00:00';
$endDTExclusive = (new DateTime($end_date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$startDT_esc = mysqli_real_escape_string($conn, $startDT);
$endDT_esc   = mysqli_real_escape_string($conn, $endDTExclusive);

/**
 * ===== Ambil data orders =====
 */
$orders = [];
$q = mysqli_query($conn, "
  SELECT o.id, o.order_date, o.total_amount, o.status, u.name AS customer
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.order_date >= '$startDT_esc' AND o.order_date < '$endDT_esc'
  ORDER BY o.order_date DESC
");

if ($q) {
    while ($r = mysqli_fetch_assoc($q)) $orders[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Orders - Mitra Cafe</title>
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
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

    .table{
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      margin-top: 14px;
    }
    .table th, .table td{
      padding: 12px 12px;
      font-size: 13px;
      border-bottom: 1px solid var(--line);
      text-align: left;
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
    }
    .status .dot{
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #111827;
      opacity: .55;
    }

    @media (max-width: 860px){
      .filter-bar{ flex-direction: column; align-items: stretch; }
      .filter-bar .right{ justify-content:flex-end; }
    }
  </style>
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
            <b>Admin Panel</b>
            <span>Mitra Cafe</span>
          </div>
        </div>

        <div class="side-section">Menu</div>
        <nav class="side-nav">
          <a class="side-link" href="./dashboard.php">Dashboard</a>
          <a class="side-link" href="./products.php">Produk</a>
          <a class="side-link active" href="./orders.php">Transaksi</a>
          <a class="side-link" href="./users.php">Pelanggan</a>
          <a class="side-link" href="./profile.php">Profil</a>
        </nav>

        <div class="side-section" style="margin-top:18px;">Shortcut</div>
        <nav class="side-nav">
          <a class="side-link" href="../pages/index.php">Lihat Toko</a>
          <a class="side-link" href="./logout.php">Logout</a>
        </nav>
      </aside>

      <!-- MAIN -->
      <main class="main">

        <!-- TOPBAR -->
        <div class="topbar">
          <div class="greeting">
            <div class="avatar"></div>
            <div class="text">
              <b>Orders</b>
              <span>Kelola transaksi berdasarkan tanggal.</span>
            </div>
          </div>
        </div>

        <!-- FILTER -->
        <form class="filter-bar" method="GET" action="./orders.php">
          <div class="left">
            <label>Filter tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
            <span style="opacity:.5; font-weight:900;">→</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
          </div>
          <div class="right">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn outline" href="./orders.php">Reset</a>
          </div>
        </form>

        <!-- TABLE -->
        <div class="card" style="padding:18px;">
          <div class="section-head" style="margin:0 0 10px;">
            <h3>Daftar Order</h3>
          </div>

          <?php if (count($orders) === 0): ?>
            <div style="opacity:.6; padding:20px; text-align:center;">
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
                <?php foreach($orders as $o): ?>
                  <tr>
                    <td><b>#<?= (int)$o['id'] ?></b></td>
                    <td><?= htmlspecialchars($o['customer'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($o['order_date']))) ?></td>
                    <td>Rp <?= number_format((int)$o['total_amount'], 0, ',', '.') ?></td>
                    <td>
                      <span class="status">
                        <span class="dot"></span>
                        <?= htmlspecialchars($o['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </main>

    </div>
  </div>
</div>

</body>
</html>
