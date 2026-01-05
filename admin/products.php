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
 * Helpers
 */
function isValidDateYmd($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}
function post($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }
function get($k, $default=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $default; }

/**
 * Detect columns
 */
$productsHasCreatedAt = hasColumn($conn, 'products', 'created_at');
$productsHasActive    = hasColumn($conn, 'products', 'is_active');
$productsHasImage     = hasColumn($conn, 'products', 'image');

/**
 * Upload config
 */
$uploadDir = realpath(__DIR__ . '/../assets/images');
if ($uploadDir === false) {
    // Folder tidak ada -> akan error saat upload, tapi page tetap jalan
    $uploadDir = __DIR__ . '/../assets/images';
}
$maxUploadBytes = 2 * 1024 * 1024; // 2MB
$allowedExt = ['jpg','jpeg','png','webp'];
$allowedMime = ['image/jpeg','image/png','image/webp'];

function saveUploadedImage(string $fieldName, string $uploadDir, int $maxUploadBytes, array $allowedExt, array $allowedMime): array {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['ok' => true, 'filename' => null, 'error' => null];
    }

    $f = $_FILES[$fieldName];

    // No file uploaded
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null, 'error' => null];
    }

    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => 'Upload error code: ' . ($f['error'] ?? -1)];
    }

    if (($f['size'] ?? 0) <= 0 || ($f['size'] ?? 0) > $maxUploadBytes) {
        return ['ok' => false, 'filename' => null, 'error' => 'Ukuran file terlalu besar (maks 2MB) atau file kosong.'];
    }

    $tmp = $f['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'filename' => null, 'error' => 'File upload tidak valid.'];
    }

    // Check extension
    $origName = (string)($f['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Ekstensi tidak diizinkan. Gunakan: jpg, jpeg, png, webp.'];
    }

    // Check mime using finfo
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
        }
    }
    if ($mime === '' || !in_array($mime, $allowedMime, true)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Tipe file tidak diizinkan.'];
    }

    // Make safe unique filename
    $safeName = 'p_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!is_dir($uploadDir)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Folder upload tidak ditemukan: assets/images'];
    }

    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Gagal menyimpan file ke server.'];
    }

    return ['ok' => true, 'filename' => $safeName, 'error' => null];
}

/**
 * Handle CRUD + BULK (POST)
 */
$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // ===== BULK ACTIONS =====
    if ($action === 'bulk') {
        $bulk_action = post('bulk_action'); // delete|deactivate|activate
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($x)=>$x>0));

        if (count($ids) === 0) {
            $flash = ['type' => 'error', 'msg' => 'Pilih minimal 1 produk untuk bulk action.'];
        } else {
            $idList = implode(',', $ids);

            if ($bulk_action === 'delete') {
                // Optional: hapus file image juga (kalau ada)
                if ($productsHasImage) {
                    $qImg = mysqli_query($conn, "SELECT image FROM products WHERE id IN ($idList)");
                    if ($qImg) {
                        while ($r = mysqli_fetch_assoc($qImg)) {
                            $img = $r['image'] ?? '';
                            if ($img) {
                                $path = realpath(__DIR__ . '/../assets/images/' . $img);
                                // pastikan masih di dalam folder assets/images
                                $base = realpath(__DIR__ . '/../assets/images');
                                if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
                                    @unlink($path);
                                }
                            }
                        }
                    }
                }
                $ok = mysqli_query($conn, "DELETE FROM products WHERE id IN ($idList)");
                $flash = $ok
                    ? ['type' => 'success', 'msg' => 'Bulk delete berhasil untuk ' . count($ids) . ' produk.']
                    : ['type' => 'error', 'msg' => 'Bulk delete gagal: ' . mysqli_error($conn)];
            } elseif (($bulk_action === 'deactivate' || $bulk_action === 'activate') && $productsHasActive) {
                $val = ($bulk_action === 'activate') ? 1 : 0;
                $ok = mysqli_query($conn, "UPDATE products SET is_active=$val WHERE id IN ($idList)");
                $flash = $ok
                    ? ['type' => 'success', 'msg' => 'Bulk update status berhasil untuk ' . count($ids) . ' produk.']
                    : ['type' => 'error', 'msg' => 'Bulk update gagal: ' . mysqli_error($conn)];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Bulk action tidak valid atau kolom is_active tidak tersedia.'];
            }
        }
    }

    // ===== SINGLE CREATE / UPDATE / DELETE =====
    if ($flash['msg'] === '') {
        $name  = post('name');
        $price = post('price');
        $active = post('is_active');

        if ($action === 'create') {
            if ($name === '' || $price === '') {
                $flash = ['type' => 'error', 'msg' => 'Nama dan harga wajib diisi.'];
            } else {
                $imageFilename = null;
                if ($productsHasImage) {
                    $up = saveUploadedImage('image', $uploadDir, $maxUploadBytes, $allowedExt, $allowedMime);
                    if (!$up['ok']) {
                        $flash = ['type' => 'error', 'msg' => 'Upload gagal: ' . $up['error']];
                    } else {
                        $imageFilename = $up['filename'];
                    }
                }

                if ($flash['msg'] === '') {
                    $nameEsc = mysqli_real_escape_string($conn, $name);
                    $priceInt = (int)$price;

                    $cols = ['name', 'price'];
                    $vals = ["'$nameEsc'", (string)$priceInt];

                    if ($productsHasActive) {
                        $cols[] = 'is_active';
                        $vals[] = ((int)$active) ? '1' : '0';
                    }
                    if ($productsHasImage) {
                        $cols[] = 'image';
                        $imgEsc = mysqli_real_escape_string($conn, (string)$imageFilename);
                        $vals[] = $imageFilename ? "'$imgEsc'" : "NULL";
                    }
                    if ($productsHasCreatedAt) {
                        $cols[] = 'created_at';
                        $vals[] = "NOW()";
                    }

                    $sql = "INSERT INTO products (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                    $ok = mysqli_query($conn, $sql);

                    $flash = $ok
                        ? ['type' => 'success', 'msg' => 'Produk berhasil ditambahkan.']
                        : ['type' => 'error', 'msg' => 'Gagal menambahkan produk: ' . mysqli_error($conn)];
                }
            }
        }

        if ($action === 'update') {
            $id = (int)post('id');
            if ($id <= 0) {
                $flash = ['type' => 'error', 'msg' => 'ID produk tidak valid.'];
            } elseif ($name === '' || $price === '') {
                $flash = ['type' => 'error', 'msg' => 'Nama dan harga wajib diisi.'];
            } else {
                // upload baru opsional
                $newImageFilename = null;
                $replaceImage = false;

                if ($productsHasImage) {
                    $up = saveUploadedImage('image', $uploadDir, $maxUploadBytes, $allowedExt, $allowedMime);
                    if (!$up['ok']) {
                        $flash = ['type' => 'error', 'msg' => 'Upload gagal: ' . $up['error']];
                    } elseif ($up['filename']) {
                        $newImageFilename = $up['filename'];
                        $replaceImage = true;
                    }
                }

                if ($flash['msg'] === '') {
                    $nameEsc = mysqli_real_escape_string($conn, $name);
                    $priceInt = (int)$price;

                    $sets = [];
                    $sets[] = "name='$nameEsc'";
                    $sets[] = "price=" . $priceInt;

                    if ($productsHasActive) {
                        $sets[] = "is_active=" . (((int)$active) ? 1 : 0);
                    }

                    if ($productsHasImage && $replaceImage) {
                        // Hapus image lama dari disk (kalau ada)
                        $qOld = mysqli_query($conn, "SELECT image FROM products WHERE id=$id LIMIT 1");
                        if ($qOld) {
                            $old = mysqli_fetch_assoc($qOld);
                            $oldImg = $old['image'] ?? '';
                            if ($oldImg) {
                                $path = realpath(__DIR__ . '/../assets/images/' . $oldImg);
                                $base = realpath(__DIR__ . '/../assets/images');
                                if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
                                    @unlink($path);
                                }
                            }
                        }

                        $imgEsc = mysqli_real_escape_string($conn, $newImageFilename);
                        $sets[] = "image='$imgEsc'";
                    }

                    $sql = "UPDATE products SET " . implode(',', $sets) . " WHERE id=" . $id . " LIMIT 1";
                    $ok = mysqli_query($conn, $sql);

                    $flash = $ok
                        ? ['type' => 'success', 'msg' => 'Produk berhasil diupdate.']
                        : ['type' => 'error', 'msg' => 'Gagal update produk: ' . mysqli_error($conn)];
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)post('id');
            if ($id <= 0) {
                $flash = ['type' => 'error', 'msg' => 'ID produk tidak valid.'];
            } else {
                // hapus image juga
                if ($productsHasImage) {
                    $qOld = mysqli_query($conn, "SELECT image FROM products WHERE id=$id LIMIT 1");
                    if ($qOld) {
                        $old = mysqli_fetch_assoc($qOld);
                        $oldImg = $old['image'] ?? '';
                        if ($oldImg) {
                            $path = realpath(__DIR__ . '/../assets/images/' . $oldImg);
                            $base = realpath(__DIR__ . '/../assets/images');
                            if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
                                @unlink($path);
                            }
                        }
                    }
                }

                $ok = mysqli_query($conn, "DELETE FROM products WHERE id=$id LIMIT 1");
                $flash = $ok
                    ? ['type' => 'success', 'msg' => 'Produk berhasil dihapus.']
                    : ['type' => 'error', 'msg' => 'Gagal hapus produk: ' . mysqli_error($conn)];
            }
        }
    }
}

/**
 * Filters (GET)
 */
$today = new DateTime('today');
$defaultStart = (new DateTime('today'))->modify('-29 days'); // 30 hari

$start_date = get('start_date', $defaultStart->format('Y-m-d'));
$end_date   = get('end_date', $today->format('Y-m-d'));

if (!isValidDateYmd($start_date)) $start_date = $defaultStart->format('Y-m-d');
if (!isValidDateYmd($end_date))   $end_date = $today->format('Y-m-d');
if ($start_date > $end_date) [$start_date, $end_date] = [$end_date, $start_date];

$q = get('q', '');
$status = get('status', 'all'); // all | active | inactive

$startDT = $start_date . ' 00:00:00';
$endDTExclusive = (new DateTime($end_date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$startDT_esc = mysqli_real_escape_string($conn, $startDT);
$endDT_esc   = mysqli_real_escape_string($conn, $endDTExclusive);
$qEsc = mysqli_real_escape_string($conn, $q);

/**
 * Pagination + Sorting (GET)
 */
$page = (int)get('page', '1');
if ($page < 1) $page = 1;

$per_page = (int)get('per_page', '10');
if (!in_array($per_page, [10, 20, 50], true)) $per_page = 10;
$offset = ($page - 1) * $per_page;

$sort = get('sort', 'id');
$dir  = strtolower(get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

// whitelist sort columns
$allowedSort = ['id','name','price'];
if ($productsHasCreatedAt) $allowedSort[] = 'created_at';
if ($productsHasActive) $allowedSort[] = 'is_active';
if (!in_array($sort, $allowedSort, true)) $sort = 'id';

/**
 * Build WHERE
 */
$where = [];
if ($q !== '') $where[] = "name LIKE '%$qEsc%'";
if ($productsHasCreatedAt) $where[] = "created_at >= '$startDT_esc' AND created_at < '$endDT_esc'";
if ($productsHasActive) {
    if ($status === 'active') $where[] = "is_active=1";
    if ($status === 'inactive') $where[] = "is_active=0";
}

$whereSql = (count($where) > 0) ? (" WHERE " . implode(" AND ", $where)) : "";

/**
 * Total count for pagination
 */
$totalRows = 0;
$qCount = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products" . $whereSql);
if ($qCount) $totalRows = (int)(mysqli_fetch_assoc($qCount)['cnt'] ?? 0);

$totalPages = max(1, (int)ceil($totalRows / $per_page));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $per_page;

/**
 * Fetch products
 */
$sql = "SELECT * FROM products" . $whereSql . " ORDER BY `$sort` $dir LIMIT $per_page OFFSET $offset";
$products = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) $products[] = $row;
}

/**
 * Build query string helper for pagination/sort links
 */
function buildQuery(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Products - Mitra Cafe</title>
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
    .filter-bar{
      display:flex; align-items:center; justify-content: space-between;
      gap: 12px; margin-top: 8px; padding: 12px 14px;
      border-radius: 18px; border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
    }
    .filter-bar .left{ display:flex; align-items:center; gap: 10px; flex-wrap: wrap; }
    .filter-bar label{ font-size: 12px; font-weight: 900; color: var(--muted); }
    .filter-bar input[type="date"],
    .filter-bar input[type="text"],
    .filter-bar select{
      padding: 10px 12px; border-radius: 14px; border: 1px solid var(--line);
      background: rgba(255,255,255,.95); outline:none; font-weight: 800; font-size: 12px;
    }
    .filter-bar input[type="text"]{ min-width: 240px; }
    .filter-bar .right{ display:flex; gap: 10px; align-items:center; flex-wrap: wrap; }

    .toolbar{
      display:flex; align-items:center; justify-content: space-between;
      gap: 12px; margin-top: 12px;
    }
    .toolbar .left{ display:flex; gap: 10px; align-items:center; flex-wrap: wrap; }
    .toolbar .right{ display:flex; gap: 10px; align-items:center; flex-wrap: wrap; }

    .table{
      width: 100%; border-collapse: separate; border-spacing: 0;
      overflow: hidden; border-radius: 18px; border: 1px solid var(--line);
      background: rgba(255,255,255,.95); margin-top: 14px;
    }
    .table th, .table td{
      padding: 12px 12px; font-size: 13px;
      border-bottom: 1px solid var(--line); text-align: left; vertical-align: top;
    }
    .table th{
      color: var(--muted); font-size: 12px; letter-spacing: .2px;
      font-weight: 900; background: rgba(47,107,255,.04);
      user-select:none;
    }
    .table tr:last-child td{ border-bottom: 0; }

    .pill-badge{
      display:inline-flex; align-items:center; gap: 8px;
      padding: 6px 10px; border-radius: 999px;
      font-weight: 900; font-size: 12px;
      border: 1px solid var(--line);
      background: rgba(17,24,39,.04);
      color: #111827; white-space: nowrap;
    }
    .pill-badge .dot{ width: 8px; height: 8px; border-radius: 50%; background: #111827; opacity: .55; }

    .actions{ display:flex; gap: 8px; flex-wrap: wrap; }
    .action-btn{
      padding: 8px 10px; border-radius: 14px; border: 1px solid var(--line);
      background: rgba(255,255,255,.92); font-weight: 900; font-size: 12px;
      cursor:pointer; white-space: nowrap;
    }
    .action-btn:hover{ border-color: rgba(47,107,255,.25); }

    .thumb{
      width: 46px; height: 46px; border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(17,24,39,.03);
      display:flex; align-items:center; justify-content:center;
      overflow:hidden;
    }
    .thumb img{ width:100%; height:100%; object-fit:cover; display:block; }

    /* Modal */
    .modal-backdrop{
      position: fixed; inset: 0; background: rgba(17,24,39,.48);
      display: none; align-items: center; justify-content: center; padding: 14px;
      z-index: 999;
    }
    .modal{
      width: min(620px, 100%); border-radius: 22px; border: 1px solid var(--line);
      background: rgba(255,255,255,.98); box-shadow: 0 30px 80px rgba(0,0,0,.18);
      overflow: hidden;
    }
    .modal-head{
      display:flex; align-items:center; justify-content: space-between;
      padding: 14px 16px; border-bottom: 1px solid var(--line);
      background: rgba(47,107,255,.04);
    }
    .modal-head b{ font-size: 14px; }
    .modal-body{ padding: 16px; }
    .modal-actions{
      display:flex; justify-content:flex-end; gap: 10px;
      padding: 14px 16px; border-top: 1px solid var(--line);
      background: rgba(17,24,39,.02);
    }
    .field{
      display:flex; flex-direction: column; gap: 6px; margin-bottom: 12px;
    }
    .field label{ font-size: 12px; font-weight: 900; color: var(--muted); }
    .field input, .field select{
      padding: 12px 12px; border-radius: 16px; border: 1px solid var(--line);
      background: rgba(255,255,255,.95); outline:none; font-weight: 800; font-size: 13px;
    }
    .help{ font-size: 12px; color: var(--muted); line-height: 1.6; margin-top: 8px; }
    .muted{ color: var(--muted); }

    .pager{
      display:flex; align-items:center; justify-content: space-between;
      gap: 12px; margin-top: 12px; flex-wrap: wrap;
    }
    .pager .left, .pager .right{ display:flex; gap: 10px; align-items:center; flex-wrap: wrap; }
    .mini{
      padding: 8px 10px; border-radius: 14px; border: 1px solid var(--line);
      background: rgba(255,255,255,.92); font-weight: 900; font-size: 12px;
      text-decoration:none; color:#111827; display:inline-flex; align-items:center;
    }
    .mini:hover{ border-color: rgba(47,107,255,.25); }
    .mini.disabled{ opacity:.45; pointer-events:none; }

    @media (max-width: 860px){
      .filter-bar{ flex-direction: column; align-items: stretch; }
      .filter-bar .right{ justify-content:flex-end; }
      .filter-bar input[type="text"]{ min-width: 0; width: 100%; }
      .toolbar{ flex-direction: column; align-items: stretch; }
      .pager{ flex-direction: column; align-items: stretch; }
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
          <a class="side-link active" href="./products.php">Produk</a>
          <a class="side-link" href="./orders.php">Transaksi</a>
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
              <b>Products</b>
              <span>Kelola produk + upload gambar + bulk action + pagination.</span>
            </div>
          </div>

          <div class="top-actions">
            <button class="btn" type="button" id="btnOpenCreate">+ Tambah Produk</button>
          </div>
        </div>

        <!-- FLASH -->
        <?php if ($flash['msg'] !== ''): ?>
          <div class="card" style="padding:14px; margin-top:10px; border-color: <?= $flash['type']==='success'?'rgba(16,185,129,.35)':'rgba(239,68,68,.28)' ?>;">
            <b style="display:block; margin-bottom:4px;">
              <?= $flash['type']==='success' ? 'Berhasil' : 'Gagal' ?>
            </b>
            <div style="opacity:.85;"><?= htmlspecialchars($flash['msg']) ?></div>
          </div>
        <?php endif; ?>

        <!-- FILTER -->
        <form class="filter-bar" method="GET" action="./products.php">
          <div class="left">
            <label>Filter</label>

            <?php if ($productsHasCreatedAt): ?>
              <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
              <span style="opacity:.5; font-weight:900;">→</span>
              <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
            <?php else: ?>
              <span class="muted" style="font-size:12px; font-weight:900;">
                (Tanggal tidak tersedia: products.created_at tidak ditemukan)
              </span>
            <?php endif; ?>

            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama produk...">

            <select name="status">
              <option value="all" <?= $status==='all'?'selected':'' ?>>All</option>
              <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>

            <select name="per_page">
              <option value="10" <?= $per_page===10?'selected':'' ?>>10 / page</option>
              <option value="20" <?= $per_page===20?'selected':'' ?>>20 / page</option>
              <option value="50" <?= $per_page===50?'selected':'' ?>>50 / page</option>
            </select>
          </div>

          <div class="right">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn outline" href="./products.php" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
          </div>
        </form>

        <!-- TOOLBAR: BULK + SORT INFO -->
        <div class="toolbar">
          <div class="left">
            <div class="card" style="padding:10px 12px;">
              <b>Hasil:</b> <?= number_format($totalRows, 0, ',', '.') ?> produk
              <span class="muted">• Page <?= $page ?> / <?= $totalPages ?></span>
            </div>
          </div>
          <div class="right muted" style="font-weight:900; font-size:12px;">
            Sort: <b><?= htmlspecialchars($sort) ?></b> (<?= htmlspecialchars(strtoupper($dir)) ?>)
          </div>
        </div>

        <!-- TABLE + BULK FORM -->
        <div class="card" style="padding:18px; margin-top: 12px;">
          <div class="section-head" style="margin:0 0 10px;">
            <h3>Daftar Produk</h3>
            <a href="./products.php" style="text-decoration:none;">Kelola</a>
          </div>

          <?php if (count($products) === 0): ?>
            <div style="opacity:.65; padding:18px; text-align:center;">
              Tidak ada produk sesuai filter.
            </div>
          <?php else: ?>
            <form method="POST" action="./products.php?<?= htmlspecialchars(buildQuery([])) ?>" id="bulkForm">
              <input type="hidden" name="action" value="bulk">

              <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
                <select name="bulk_action" required>
                  <option value="" selected disabled>Bulk action...</option>
                  <option value="delete">Delete selected</option>
                  <?php if ($productsHasActive): ?>
                    <option value="deactivate">Deactivate selected</option>
                    <option value="activate">Activate selected</option>
                  <?php endif; ?>
                </select>
                <button class="btn" type="submit" onclick="return confirm('Jalankan bulk action untuk produk terpilih?')">Apply</button>
                <span class="muted" style="font-weight:900; font-size:12px;">Tip: centang checkbox produk lalu pilih action.</span>
              </div>

              <table class="table">
                <thead>
                  <tr>
                    <th style="width:38px;">
                      <input type="checkbox" id="checkAll" />
                    </th>

                    <th>
                      <a class="muted" style="text-decoration:none;"
                         href="./products.php?<?= htmlspecialchars(buildQuery(['sort'=>'id','dir'=>($sort==='id' && $dir==='asc')?'desc':'asc','page'=>1])) ?>">
                        #ID
                      </a>
                    </th>

                    <th>Gambar</th>

                    <th>
                      <a class="muted" style="text-decoration:none;"
                         href="./products.php?<?= htmlspecialchars(buildQuery(['sort'=>'name','dir'=>($sort==='name' && $dir==='asc')?'desc':'asc','page'=>1])) ?>">
                        Nama
                      </a>
                    </th>

                    <th>
                      <a class="muted" style="text-decoration:none;"
                         href="./products.php?<?= htmlspecialchars(buildQuery(['sort'=>'price','dir'=>($sort==='price' && $dir==='asc')?'desc':'asc','page'=>1])) ?>">
                        Harga
                      </a>
                    </th>

                    <?php if ($productsHasActive): ?>
                      <th>
                        <a class="muted" style="text-decoration:none;"
                           href="./products.php?<?= htmlspecialchars(buildQuery(['sort'=>'is_active','dir'=>($sort==='is_active' && $dir==='asc')?'desc':'asc','page'=>1])) ?>">
                          Status
                        </a>
                      </th>
                    <?php endif; ?>

                    <?php if ($productsHasCreatedAt): ?>
                      <th>
                        <a class="muted" style="text-decoration:none;"
                           href="./products.php?<?= htmlspecialchars(buildQuery(['sort'=>'created_at','dir'=>($sort==='created_at' && $dir==='asc')?'desc':'asc','page'=>1])) ?>">
                          Dibuat
                        </a>
                      </th>
                    <?php endif; ?>

                    <th>Aksi</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach($products as $p): ?>
                    <tr>
                      <td>
                        <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="rowCheck" />
                      </td>

                      <td><b>#<?= (int)$p['id'] ?></b></td>

                      <td>
                        <div class="thumb">
                          <?php if ($productsHasImage && !empty($p['image'])): ?>
                            <img src="../assets/images/<?= htmlspecialchars($p['image']) ?>" alt="img">
                          <?php else: ?>
                            <span style="opacity:.55; font-weight:950;">—</span>
                          <?php endif; ?>
                        </div>
                      </td>

                      <td>
                        <div style="font-weight:950;"><?= htmlspecialchars($p['name']) ?></div>
                        <?php if ($productsHasImage): ?>
                          <div class="muted" style="font-size:12px; margin-top:4px;">
                            <?= !empty($p['image']) ? ('File: ' . htmlspecialchars($p['image'])) : 'No image' ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td>Rp <?= number_format((int)$p['price'], 0, ',', '.') ?></td>

                      <?php if ($productsHasActive): ?>
                        <td>
                          <span class="pill-badge">
                            <span class="dot"></span>
                            <?= ((int)$p['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                          </span>
                        </td>
                      <?php endif; ?>

                      <?php if ($productsHasCreatedAt): ?>
                        <td style="white-space:nowrap;">
                          <?= htmlspecialchars(date('d-m-Y H:i', strtotime($p['created_at'] ?? 'now'))) ?>
                        </td>
                      <?php endif; ?>

                      <td>
                        <div class="actions">
                          <button
                            class="action-btn"
                            type="button"
                            data-edit="1"
                            data-id="<?= (int)$p['id'] ?>"
                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                            data-price="<?= (int)$p['price'] ?>"
                            data-active="<?= $productsHasActive ? (int)$p['is_active'] : 1 ?>"
                            data-image="<?= $productsHasImage ? htmlspecialchars(($p['image'] ?? ''), ENT_QUOTES) : '' ?>"
                          >Edit</button>

                          <button
                            class="action-btn"
                            type="button"
                            data-delete="1"
                            data-id="<?= (int)$p['id'] ?>"
                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                          >Hapus</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </form>

            <!-- PAGINATION -->
            <div class="pager">
              <div class="left">
                <a class="mini <?= $page<=1?'disabled':'' ?>" href="./products.php?<?= htmlspecialchars(buildQuery(['page'=>1])) ?>">« First</a>
                <a class="mini <?= $page<=1?'disabled':'' ?>" href="./products.php?<?= htmlspecialchars(buildQuery(['page'=>$page-1])) ?>">‹ Prev</a>
                <div class="card" style="padding:8px 10px;">
                  Page <b><?= $page ?></b> / <?= $totalPages ?>
                </div>
                <a class="mini <?= $page>=$totalPages?'disabled':'' ?>" href="./products.php?<?= htmlspecialchars(buildQuery(['page'=>$page+1])) ?>">Next ›</a>
                <a class="mini <?= $page>=$totalPages?'disabled':'' ?>" href="./products.php?<?= htmlspecialchars(buildQuery(['page'=>$totalPages])) ?>">Last »</a>
              </div>

              <div class="right muted" style="font-weight:900; font-size:12px;">
                Menampilkan <?= count($products) ?> dari <?= $totalRows ?> data
              </div>
            </div>

          <?php endif; ?>
        </div>

      </main>

    </div>
  </div>
</div>

<!-- MODAL: CREATE -->
<div class="modal-backdrop" id="modalCreate">
  <div class="modal">
    <div class="modal-head">
      <b>Tambah Produk</b>
      <button class="action-btn" type="button" data-close="modalCreate">✕</button>
    </div>

    <form method="POST" action="./products.php?<?= htmlspecialchars(buildQuery(['page'=>1])) ?>" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="action" value="create">

        <div class="field">
          <label>Nama Produk</label>
          <input type="text" name="name" placeholder="Contoh: Kopi Susu Gula Aren" required>
        </div>

        <div class="field">
          <label>Harga (angka)</label>
          <input type="number" name="price" placeholder="Contoh: 18000" min="0" required>
        </div>

        <?php if ($productsHasActive): ?>
          <div class="field">
            <label>Status</label>
            <select name="is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($productsHasImage): ?>
          <div class="field">
            <label>Gambar (jpg/png/webp, max 2MB)</label>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          </div>
          <div class="help">Gambar disimpan ke <code>../assets/images/</code> dengan nama unik.</div>
        <?php endif; ?>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalCreate">Batal</button>
        <button class="btn" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: EDIT -->
<div class="modal-backdrop" id="modalEdit">
  <div class="modal">
    <div class="modal-head">
      <b>Edit Produk</b>
      <button class="action-btn" type="button" data-close="modalEdit">✕</button>
    </div>

    <form method="POST" action="./products.php?<?= htmlspecialchars(buildQuery([])) ?>" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId" value="">

        <div class="field">
          <label>Nama Produk</label>
          <input type="text" name="name" id="editName" required>
        </div>

        <div class="field">
          <label>Harga (angka)</label>
          <input type="number" name="price" id="editPrice" min="0" required>
        </div>

        <?php if ($productsHasActive): ?>
          <div class="field">
            <label>Status</label>
            <select name="is_active" id="editActive">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($productsHasImage): ?>
          <div class="field">
            <label>Gambar Baru (opsional, max 2MB)</label>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          </div>
          <div class="help">
            File sekarang: <span id="editImageName" class="muted">-</span><br>
            Kalau upload gambar baru, gambar lama akan dihapus otomatis.
          </div>
        <?php endif; ?>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalEdit">Batal</button>
        <button class="btn" type="submit">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: DELETE -->
<div class="modal-backdrop" id="modalDelete">
  <div class="modal">
    <div class="modal-head">
      <b>Hapus Produk</b>
      <button class="action-btn" type="button" data-close="modalDelete">✕</button>
    </div>

    <form method="POST" action="./products.php?<?= htmlspecialchars(buildQuery([])) ?>">
      <div class="modal-body">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId" value="">

        <div style="font-weight:950; margin-bottom:6px;">Yakin mau menghapus produk ini?</div>
        <div style="opacity:.75;" id="deleteName">-</div>

        <div class="help" style="margin-top:10px;">Aksi ini tidak bisa dibatalkan.</div>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalDelete">Batal</button>
        <button class="btn" type="submit">Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
  // ===== modal helpers =====
  function openModal(id){ document.getElementById(id).style.display = 'flex'; }
  function closeModal(id){ document.getElementById(id).style.display = 'none'; }

  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close')));
  });
  document.querySelectorAll('.modal-backdrop').forEach(bg => {
    bg.addEventListener('click', (e) => { if (e.target === bg) bg.style.display = 'none'; });
  });

  // open create
  document.getElementById('btnOpenCreate')?.addEventListener('click', () => openModal('modalCreate'));

  // open edit/delete
  document.querySelectorAll('[data-edit="1"]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value = btn.dataset.id;
      document.getElementById('editName').value = btn.dataset.name;
      document.getElementById('editPrice').value = btn.dataset.price;

      const activeEl = document.getElementById('editActive');
      if (activeEl) activeEl.value = btn.dataset.active;

      const imgNameEl = document.getElementById('editImageName');
      if (imgNameEl) imgNameEl.textContent = btn.dataset.image || '-';

      openModal('modalEdit');
    });
  });

  document.querySelectorAll('[data-delete="1"]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('deleteId').value = btn.dataset.id;
      document.getElementById('deleteName').textContent = btn.dataset.name;
      openModal('modalDelete');
    });
  });

  // Bulk select all
  const checkAll = document.getElementById('checkAll');
  const rowChecks = () => Array.from(document.querySelectorAll('.rowCheck'));
  checkAll?.addEventListener('change', () => {
    rowChecks().forEach(ch => ch.checked = checkAll.checked);
  });
</script>

</body>
</html>
