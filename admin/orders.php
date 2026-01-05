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
 * ===== Helpers =====
 */
function get($k){ return isset($_GET[$k]) ? trim($_GET[$k]) : ''; }
function post($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }

function isValidDateYmd($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function hasTable(mysqli $conn, string $table): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    return $res && mysqli_num_rows($res) > 0;
}
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}
function pickColumn(mysqli $conn, string $table, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (hasColumn($conn, $table, $c)) return $c;
    }
    return null;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n){ return 'Rp ' . number_format((int)$n, 0, ',', '.'); }

/**
 * ===== Tables =====
 */
if (!hasTable($conn, 'orders')) die("Tabel 'orders' tidak ditemukan.");
if (!hasTable($conn, 'order_items')) die("Tabel 'order_items' tidak ditemukan.");
if (!hasTable($conn, 'users')) die("Tabel 'users' tidak ditemukan.");
if (!hasTable($conn, 'products')) die("Tabel 'products' tidak ditemukan.");

/**
 * ===== Detect columns (biar fleksibel skema) =====
 */
$ordersTable = 'orders';
$orderItemsTable = 'order_items';
$usersTable = 'users';
$productsTable = 'products';

$colOrderId     = pickColumn($conn, $ordersTable, ['id', 'order_id']) ?? 'id';
$colOrderUserId = pickColumn($conn, $ordersTable, ['user_id', 'customer_id']) ?? 'user_id';
$colOrderDate   = pickColumn($conn, $ordersTable, ['order_date', 'created_at', 'date']) ?? 'order_date';
$colOrderTotal  = pickColumn($conn, $ordersTable, ['total_amount', 'total', 'grand_total']) ?? 'total_amount';
$colOrderStatus = pickColumn($conn, $ordersTable, ['status', 'order_status']) ?? 'status';

$colOrderAddress = pickColumn($conn, $ordersTable, [
    'shipping_address','address','alamat','delivery_address','customer_address'
]);

$colLat = pickColumn($conn, $ordersTable, ['lat','latitude']);
$colLng = pickColumn($conn, $ordersTable, ['lng','longitude','lon']);

$colUserId   = pickColumn($conn, $usersTable, ['id','user_id']) ?? 'id';
$colUserName = pickColumn($conn, $usersTable, ['name','full_name','username']) ?? 'name';
$colUserPhone = pickColumn($conn, $usersTable, ['phone','telp','no_hp','mobile']);

$colItemOrderId = pickColumn($conn, $orderItemsTable, ['order_id']) ?? 'order_id';
$colItemProductId = pickColumn($conn, $orderItemsTable, ['product_id']) ?? 'product_id';
$colItemQty = pickColumn($conn, $orderItemsTable, ['qty','quantity','jumlah']) ?? 'qty';
$colItemPrice = pickColumn($conn, $orderItemsTable, ['price','unit_price','harga']); // optional

$colProdId = pickColumn($conn, $productsTable, ['id','product_id']) ?? 'id';
$colProdName = pickColumn($conn, $productsTable, ['name','product_name','title']) ?? 'name';
$colProdImage = pickColumn($conn, $productsTable, ['image','photo','thumbnail']); // optional
$colProdPrice = pickColumn($conn, $productsTable, ['price','harga']); // optional

/**
 * ===== Filter & Search =====
 */
$today = new DateTime('today');
$defaultStart = (new DateTime('today'))->modify('-13 days'); // 14 hari

$start_date = (get('start_date') && isValidDateYmd(get('start_date'))) ? get('start_date') : $defaultStart->format('Y-m-d');
$end_date   = (get('end_date') && isValidDateYmd(get('end_date'))) ? get('end_date') : $today->format('Y-m-d');

if ($start_date > $end_date) { $tmp=$start_date; $start_date=$end_date; $end_date=$tmp; }

$startDT = $start_date . ' 00:00:00';
$endDTExclusive = (new DateTime($end_date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$startDT_esc = mysqli_real_escape_string($conn, $startDT);
$endDT_esc   = mysqli_real_escape_string($conn, $endDTExclusive);

$q = get('q'); // keyword search: id / customer
$statusFilter = get('status'); // optional
$viewId = (int)get('view'); // detail id

/**
 * ===== Flash =====
 */
$flash = ['type'=>'', 'msg'=>''];

/**
 * ===== Update status (POST) =====
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'update_status') {
        $orderId = (int)post('order_id');
        $newStatus = post('status');

        $allowed = ['Pending','Paid','Processing','On Delivery','Delivered','Cancelled'];
        if ($orderId <= 0) {
            $flash = ['type'=>'error','msg'=>'Order ID tidak valid.'];
        } elseif (!in_array($newStatus, $allowed, true)) {
            $flash = ['type'=>'error','msg'=>'Status tidak valid.'];
        } else {
            $stEsc = mysqli_real_escape_string($conn, $newStatus);
            $ok = mysqli_query($conn, "UPDATE `$ordersTable` SET `$colOrderStatus`='$stEsc' WHERE `$colOrderId`=$orderId LIMIT 1");
            if ($ok) {
                $flash = ['type'=>'success','msg'=>'Status order berhasil diupdate.'];
                // redirect biar refresh detail dan prevent resubmit
                $qs = "start_date=".urlencode($start_date)."&end_date=".urlencode($end_date);
                if ($q !== '') $qs .= "&q=".urlencode($q);
                if ($statusFilter !== '') $qs .= "&status=".urlencode($statusFilter);
                $qs .= "&view=".$orderId;
                header("Location: ./orders.php?$qs");
                exit;
            } else {
                $flash = ['type'=>'error','msg'=>'Gagal update status: ' . mysqli_error($conn)];
            }
        }
    }
}

/**
 * ===== Build WHERE =====
 */
$where = [];
$where[] = "o.`$colOrderDate` >= '$startDT_esc' AND o.`$colOrderDate` < '$endDT_esc'";

if ($statusFilter !== '') {
    $st = mysqli_real_escape_string($conn, $statusFilter);
    $where[] = "o.`$colOrderStatus` = '$st'";
}

if ($q !== '') {
    $safe = mysqli_real_escape_string($conn, $q);
    // cari by order id atau customer name
    $where[] = "(CAST(o.`$colOrderId` AS CHAR) LIKE '%$safe%' OR u.`$colUserName` LIKE '%$safe%')";
}

$whereSql = "WHERE " . implode(" AND ", $where);

/**
 * ===== Pagination (simple) =====
 */
$perPage = 10;
$page = max(1, (int)get('page'));
$offset = ($page - 1) * $perPage;

$qCount = mysqli_query($conn, "
    SELECT COUNT(*) AS cnt
    FROM `$ordersTable` o
    LEFT JOIN `$usersTable` u ON u.`$colUserId` = o.`$colOrderUserId`
    $whereSql
");
$totalRows = (int)(mysqli_fetch_assoc($qCount)['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/**
 * ===== List Orders =====
 */
$orders = [];
$qOrders = mysqli_query($conn, "
    SELECT
      o.`$colOrderId` AS id,
      o.`$colOrderDate` AS order_date,
      o.`$colOrderTotal` AS total_amount,
      o.`$colOrderStatus` AS status,
      ".($colOrderAddress ? "o.`$colOrderAddress` AS address," : "NULL AS address,")."
      u.`$colUserName` AS customer
      ".($colUserPhone ? ", u.`$colUserPhone` AS phone" : ", NULL AS phone")."
    FROM `$ordersTable` o
    LEFT JOIN `$usersTable` u ON u.`$colUserId` = o.`$colOrderUserId`
    $whereSql
    ORDER BY o.`$colOrderDate` DESC
    LIMIT $perPage OFFSET $offset
");
if ($qOrders) {
    while ($r = mysqli_fetch_assoc($qOrders)) $orders[] = $r;
}

/**
 * ===== Detail Order =====
 */
$orderDetail = null;
$orderItems = [];

if ($viewId > 0) {
    $qDetail = mysqli_query($conn, "
        SELECT
          o.`$colOrderId` AS id,
          o.`$colOrderDate` AS order_date,
          o.`$colOrderTotal` AS total_amount,
          o.`$colOrderStatus` AS status,
          o.`$colOrderUserId` AS user_id
          ".($colOrderAddress ? ", o.`$colOrderAddress` AS address" : ", NULL AS address")."
          ".($colLat ? ", o.`$colLat` AS lat" : ", NULL AS lat")."
          ".($colLng ? ", o.`$colLng` AS lng" : ", NULL AS lng")."
          , u.`$colUserName` AS customer
          ".($colUserPhone ? ", u.`$colUserPhone` AS phone" : ", NULL AS phone")."
        FROM `$ordersTable` o
        LEFT JOIN `$usersTable` u ON u.`$colUserId` = o.`$colOrderUserId`
        WHERE o.`$colOrderId`=$viewId
        LIMIT 1
    ");
    if ($qDetail) $orderDetail = mysqli_fetch_assoc($qDetail);

    // items
    $qItems = mysqli_query($conn, "
        SELECT
          oi.`$colItemQty` AS qty
          ".($colItemPrice ? ", oi.`$colItemPrice` AS item_price" : ", NULL AS item_price")."
          , p.`$colProdName` AS product_name
          ".($colProdImage ? ", p.`$colProdImage` AS image" : ", NULL AS image")."
          ".($colProdPrice ? ", p.`$colProdPrice` AS product_price" : ", NULL AS product_price")."
        FROM `$orderItemsTable` oi
        LEFT JOIN `$productsTable` p ON p.`$colProdId` = oi.`$colItemProductId`
        WHERE oi.`$colItemOrderId`=$viewId
    ");
    if ($qItems) {
        while ($it = mysqli_fetch_assoc($qItems)) $orderItems[] = $it;
    }
}

/**
 * ===== Build query string helper for pagination links =====
 */
function buildQS($params){
    $pairs = [];
    foreach ($params as $k=>$v) {
        if ($v === '' || $v === null) continue;
        $pairs[] = urlencode($k).'='.urlencode((string)$v);
    }
    return implode('&', $pairs);
}
$baseParams = [
  'start_date' => $start_date,
  'end_date' => $end_date,
  'q' => $q,
  'status' => $statusFilter,
  'view' => $viewId > 0 ? $viewId : '',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Orders - Mitra Cafe</title>
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
    .grid-2{
      display:grid;
      grid-template-columns: 1fr;
      gap: var(--gap);
      margin-top: 14px;
    }

    /* filter bar (mirip dashboard) */
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
      flex-wrap: wrap;
    }
    .filter-left{
      display:flex;
      align-items:center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .filter-right{
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
    .filter-bar input[type="date"], .filter-bar input[type="text"], .filter-bar select{
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      outline:none;
      font-weight: 800;
      font-size: 12px;
    }
    .filter-bar input[type="text"]{ width: min(280px, 70vw); }

    /* table */
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
      white-space: nowrap;
    }
    .status .s-dot{
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #111827;
      opacity: .55;
    }

    .pill-link{
      display:inline-flex;
      align-items:center;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid var(--line);
      text-decoration:none;
      font-weight: 900;
      font-size: 12px;
      color: #111827;
      background: rgba(255,255,255,.92);
    }
    .pill-link:hover{ border-color: rgba(47,107,255,.25); }

    .split{
      display:grid;
      grid-template-columns: 1.15fr .85fr;
      gap: var(--gap);
      margin-top: 14px;
    }

    .detail-meta{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 10px;
    }
    .meta-box{
      padding: 12px 12px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
    }
    .meta-box .k{ font-size: 12px; font-weight: 900; color: var(--muted); }
    .meta-box .v{ margin-top: 6px; font-weight: 950; font-size: 13px; }

    .items{
      margin-top: 12px;
      border: 1px solid var(--line);
      border-radius: 18px;
      overflow: hidden;
      background: rgba(255,255,255,.95);
    }
    .item-row{
      display:grid;
      grid-template-columns: 52px 1fr auto;
      gap: 10px;
      padding: 12px 12px;
      border-bottom: 1px solid var(--line);
      align-items:center;
    }
    .item-row:last-child{ border-bottom: 0; }
    .thumb{
      width: 52px; height: 52px;
      border-radius: 16px;
      border: 1px solid var(--line);
      display:flex;
      align-items:center;
      justify-content:center;
      overflow:hidden;
      background: rgba(47,107,255,.04);
      font-weight: 950;
      color: rgba(17,24,39,.55);
    }
    .thumb img{ width: 100%; height: 100%; object-fit: cover; display:block; }
    .item-name{ font-weight: 950; }
    .item-sub{ font-size: 12px; color: var(--muted); font-weight: 850; margin-top: 4px; }
    .item-right{ text-align:right; }
    .item-right b{ font-weight: 950; }

    .pager{
      display:flex;
      justify-content: space-between;
      gap: 10px;
      margin-top: 12px;
      flex-wrap: wrap;
      align-items:center;
    }
    .pager .muted{ color: var(--muted); font-weight: 900; font-size: 12px; }

    @media (max-width: 1100px){
      .split{ grid-template-columns: 1fr; }
    }
    @media (max-width: 760px){
      .detail-meta{ grid-template-columns: 1fr; }
      .item-row{ grid-template-columns: 52px 1fr; }
      .item-right{ grid-column: 1 / -1; text-align:left; }
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
              <b>Orders / Transaksi</b>
              <span>Kelola order, lihat detail, dan update status.</span>
            </div>
          </div>
          <div class="top-actions">
            <button class="icon-btn" title="Settings">⚙️</button>
          </div>
        </div>

        <!-- FLASH -->
        <?php if ($flash['msg'] !== ''): ?>
          <div class="card" style="padding:14px; margin-top:10px; border-color: <?= $flash['type']==='success'?'rgba(16,185,129,.35)':'rgba(239,68,68,.28)' ?>;">
            <b style="display:block; margin-bottom:4px;">
              <?= $flash['type']==='success' ? 'Berhasil' : 'Gagal' ?>
            </b>
            <div style="opacity:.85;"><?= h($flash['msg']) ?></div>
          </div>
        <?php endif; ?>

        <!-- FILTER -->
        <form class="filter-bar" method="GET" action="./orders.php">
          <div class="filter-left">
            <label>Filter</label>
            <input type="date" name="start_date" value="<?= h($start_date) ?>" required>
            <span style="opacity:.5; font-weight:900;">→</span>
            <input type="date" name="end_date" value="<?= h($end_date) ?>" required>

            <select name="status">
              <option value="">Semua status</option>
              <?php
                $opts = ['Pending','Paid','Processing','On Delivery','Delivered','Cancelled'];
                foreach ($opts as $opt):
                  $sel = ($statusFilter === $opt) ? 'selected' : '';
              ?>
                <option value="<?= h($opt) ?>" <?= $sel ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>

            <input type="text" name="q" placeholder="Cari #ID / customer..." value="<?= h($q) ?>">
          </div>

          <div class="filter-right">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn outline" href="./orders.php" style="text-decoration:none;">Reset</a>
          </div>
        </form>

        <!-- CONTENT -->
        <div class="split">

          <!-- LIST -->
          <div class="card" style="padding:18px;">
            <div class="section-head" style="margin:0 0 10px;">
              <h3>Daftar Order</h3>
              <a href="./dashboard.php">Dashboard</a>
            </div>

            <?php if (count($orders) === 0): ?>
              <div style="opacity:.65; padding:18px; text-align:center;">
                Tidak ada order pada filter ini.
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
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($orders as $o): ?>
                    <?php
                      $id = (int)$o['id'];
                      $qs = buildQS($baseParams);
                      $qsNoView = buildQS(array_merge($baseParams, ['view' => $id, 'page' => $page]));
                      $isActive = ($viewId === $id);
                    ?>
                    <tr style="<?= $isActive ? 'background: rgba(47,107,255,.04);' : '' ?>">
                      <td><b>#<?= $id ?></b></td>
                      <td>
                        <?= h($o['customer'] ?? 'Unknown') ?>
                        <?php if (!empty($o['phone'])): ?>
                          <div style="font-size:12px; color:var(--muted); font-weight:850; margin-top:4px;">
                            <?= h($o['phone']) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?= h(date('d-m-Y H:i', strtotime($o['order_date'] ?? 'now'))) ?></td>
                      <td><?= rupiah($o['total_amount'] ?? 0) ?></td>
                      <td>
                        <span class="status"><span class="s-dot"></span><?= h($o['status'] ?? 'Pending') ?></span>
                      </td>
                      <td>
                        <a class="pill-link" href="./orders.php?<?= $qsNoView ?>">👁️ Detail</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="pager">
                <div class="muted">
                  Menampilkan <?= count($orders) ?> dari <?= number_format($totalRows,0,',','.') ?> order
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                  <?php
                    $prev = max(1, $page-1);
                    $next = min($totalPages, $page+1);

                    $qsPrev = buildQS(array_merge($baseParams, ['page'=>$prev]));
                    $qsNext = buildQS(array_merge($baseParams, ['page'=>$next]));
                  ?>
                  <a class="btn outline" style="text-decoration:none; <?= $page<=1?'pointer-events:none; opacity:.55;':'' ?>" href="./orders.php?<?= $qsPrev ?>">← Prev</a>
                  <div class="muted">Page <b><?= $page ?></b> / <?= $totalPages ?></div>
                  <a class="btn outline" style="text-decoration:none; <?= $page>=$totalPages?'pointer-events:none; opacity:.55;':'' ?>" href="./orders.php?<?= $qsNext ?>">Next →</a>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- DETAIL -->
          <div class="card" style="padding:18px;">
            <div class="section-head" style="margin:0 0 10px;">
              <h3>Detail Order</h3>
              <a href="./orders.php?<?= buildQS(array_merge($baseParams, ['view'=>''])) ?>">Tutup</a>
            </div>

            <?php if (!$orderDetail): ?>
              <div style="opacity:.65; padding:18px; text-align:center;">
                Pilih order dari daftar untuk melihat detail.
              </div>
            <?php else: ?>
              <?php
                $mapsUrl = '';
                if (!empty($orderDetail['lat']) && !empty($orderDetail['lng'])) {
                    $mapsUrl = 'https://www.google.com/maps?q=' . urlencode($orderDetail['lat'].','.$orderDetail['lng']);
                } elseif (!empty($orderDetail['address'])) {
                    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($orderDetail['address']);
                }
              ?>

              <div class="detail-meta">
                <div class="meta-box">
                  <div class="k">Order</div>
                  <div class="v">#<?= (int)$orderDetail['id'] ?></div>
                </div>
                <div class="meta-box">
                  <div class="k">Tanggal</div>
                  <div class="v"><?= h(date('d-m-Y H:i', strtotime($orderDetail['order_date'] ?? 'now'))) ?></div>
                </div>
                <div class="meta-box">
                  <div class="k">Pelanggan</div>
                  <div class="v">
                    <?= h($orderDetail['customer'] ?? 'Unknown') ?>
                    <?php if (!empty($orderDetail['phone'])): ?>
                      <div style="margin-top:6px; font-size:12px; color:var(--muted); font-weight:850;"><?= h($orderDetail['phone']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="meta-box">
                  <div class="k">Total</div>
                  <div class="v"><?= rupiah($orderDetail['total_amount'] ?? 0) ?></div>
                </div>
              </div>

              <div class="meta-box" style="margin-top:10px;">
                <div class="k">Alamat Pengiriman</div>
                <div class="v" style="font-weight:900;">
                  <?= $orderDetail['address'] ? h($orderDetail['address']) : '<span style="opacity:.6;">(Alamat tidak tersedia)</span>' ?>
                </div>
                <?php if ($mapsUrl !== ''): ?>
                  <div style="margin-top:10px;">
                    <a class="pill-link" target="_blank" href="<?= h($mapsUrl) ?>">📍 Buka di Google Maps</a>
                  </div>
                <?php endif; ?>
              </div>

              <div class="meta-box" style="margin-top:10px;">
                <div class="k">Status</div>
                <div class="v" style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                  <span class="status"><span class="s-dot"></span><?= h($orderDetail['status'] ?? 'Pending') ?></span>

                  <form method="POST" action="./orders.php?<?= buildQS($baseParams) ?>" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= (int)$orderDetail['id'] ?>">

                    <select name="status" required>
                      <?php
                        $opts = ['Pending','Paid','Processing','On Delivery','Delivered','Cancelled'];
                        $cur = $orderDetail['status'] ?? 'Pending';
                        foreach ($opts as $opt):
                          $sel = ($cur === $opt) ? 'selected' : '';
                      ?>
                        <option value="<?= h($opt) ?>" <?= $sel ?>><?= h($opt) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Update Status</button>
                  </form>
                </div>
              </div>

              <div class="section-head" style="margin:16px 0 10px;">
                <h3 style="font-size:14px;">Item</h3>
                <a href="./orders.php?<?= buildQS(array_merge($baseParams, ['view'=>''])) ?>">Tutup</a>
              </div>

              <?php if (count($orderItems) === 0): ?>
                <div style="opacity:.65; padding:14px; text-align:center;">
                  Item order tidak ditemukan.
                </div>
              <?php else: ?>
                <div class="items">
                  <?php
                    $calcSubtotal = 0;
                    foreach ($orderItems as $it):
                      $qty = (int)($it['qty'] ?? 0);

                      // harga item: prioritas order_items.price, fallback products.price, fallback 0
                      $unit = 0;
                      if ($it['item_price'] !== null && $it['item_price'] !== '') $unit = (int)$it['item_price'];
                      elseif ($it['product_price'] !== null && $it['product_price'] !== '') $unit = (int)$it['product_price'];

                      $line = $qty * $unit;
                      $calcSubtotal += $line;

                      $img = $it['image'] ?? '';
                      $name = $it['product_name'] ?? 'Unknown product';
                  ?>
                    <div class="item-row">
                      <div class="thumb">
                        <?php if ($img): ?>
                          <img src="../assets/images/<?= h($img) ?>" alt="<?= h($name) ?>">
                        <?php else: ?>
                          IMG
                        <?php endif; ?>
                      </div>

                      <div>
                        <div class="item-name"><?= h($name) ?></div>
                        <div class="item-sub">
                          Qty: <b><?= $qty ?></b>
                          <?php if ($unit > 0): ?>
                            • Harga: <b><?= rupiah($unit) ?></b>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="item-right">
                        <?php if ($unit > 0): ?>
                          <div class="item-sub">Subtotal</div>
                          <b><?= rupiah($line) ?></b>
                        <?php else: ?>
                          <div class="item-sub">Subtotal</div>
                          <b style="opacity:.7;">(harga tidak tersedia)</b>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="meta-box" style="margin-top:10px;">
                  <div class="k">Ringkasan</div>
                  <div class="v" style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; font-weight:950;">
                    <span>Subtotal item (estimasi)</span>
                    <span><?= rupiah($calcSubtotal) ?></span>
                  </div>
                  <div class="v" style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:8px;">
                    <span>Total order (DB)</span>
                    <span style="font-weight:950;"><?= rupiah($orderDetail['total_amount'] ?? 0) ?></span>
                  </div>
                  <div style="margin-top:10px; font-size:12px; color:var(--muted); font-weight:850; line-height:1.6;">
                    Catatan: subtotal item dihitung dari <code>order_items.price</code> (jika ada), fallback <code>products.price</code>. Total order tetap mengikuti <code>orders.total_amount</code>.
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

        </div>

      </main>

    </div>
  </div>
</div>

</body>
</html>
