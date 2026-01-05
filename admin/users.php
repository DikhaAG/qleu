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

/**
 * Detect columns (biar fleksibel dengan struktur tabel user kamu)
 */
$usersHasCreatedAt = hasColumn($conn, 'users', 'created_at');
$usersHasEmail     = hasColumn($conn, 'users', 'email');
$usersHasPhone     = hasColumn($conn, 'users', 'no_hp') || hasColumn($conn, 'users', 'phone') || hasColumn($conn, 'users', 'telp_number');
$usersHasName      = hasColumn($conn, 'users', 'name');

/**
 * tentukan nama kolom phone yang dipakai (prioritas)
 */
$phoneCol = null;
if (hasColumn($conn, 'users', 'no_hp')) $phoneCol = 'no_hp';
else if (hasColumn($conn, 'users', 'phone')) $phoneCol = 'phone';
else if (hasColumn($conn, 'users', 'telp_number')) $phoneCol = 'telp_number';

/**
 * ===== Handle CRUD Actions (POST) =====
 * Kita hanya izinkan update ringan (name + phone) dan delete.
 */
$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'update') {
        $id = (int)post('id');
        if ($id <= 0) {
            $flash = ['type' => 'error', 'msg' => 'ID user tidak valid.'];
        } else {
            $sets = [];

            if ($usersHasName) {
                $name = post('name');
                if ($name === '') {
                    $flash = ['type' => 'error', 'msg' => 'Nama tidak boleh kosong.'];
                } else {
                    $nameEsc = mysqli_real_escape_string($conn, $name);
                    $sets[] = "name='$nameEsc'";
                }
            }

            if ($phoneCol) {
                $phone = post('phone');
                $phoneEsc = mysqli_real_escape_string($conn, $phone);
                $sets[] = "`$phoneCol`='$phoneEsc'";
            }

            if ($flash['msg'] === '' && count($sets) > 0) {
                $sql = "UPDATE users SET " . implode(',', $sets) . " WHERE id=$id LIMIT 1";
                $ok = mysqli_query($conn, $sql);
                $flash = $ok
                    ? ['type' => 'success', 'msg' => 'Data pelanggan berhasil diupdate.']
                    : ['type' => 'error', 'msg' => 'Gagal update pelanggan: ' . mysqli_error($conn)];
            } elseif ($flash['msg'] === '' && count($sets) === 0) {
                $flash = ['type' => 'error', 'msg' => 'Tidak ada kolom yang bisa diupdate pada tabel users.'];
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)post('id');
        if ($id <= 0) {
            $flash = ['type' => 'error', 'msg' => 'ID user tidak valid.'];
        } else {
            // Hindari menghapus admin (kalau admin juga disimpan di users). Kalau admin beda tabel, ini aman.
            $ok = mysqli_query($conn, "DELETE FROM users WHERE id=$id LIMIT 1");
            $flash = $ok
                ? ['type' => 'success', 'msg' => 'Pelanggan berhasil dihapus.']
                : ['type' => 'error', 'msg' => 'Gagal hapus pelanggan: ' . mysqli_error($conn)];
        }
    }
}

/**
 * ===== Filters (GET) =====
 */
$today = new DateTime('today');
$defaultStart = (new DateTime('today'))->modify('-29 days'); // 30 hari

$start_date = isset($_GET['start_date']) && isValidDateYmd($_GET['start_date'])
    ? $_GET['start_date']
    : $defaultStart->format('Y-m-d');

$end_date = isset($_GET['end_date']) && isValidDateYmd($_GET['end_date'])
    ? $_GET['end_date']
    : $today->format('Y-m-d');

if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$startDT = $start_date . ' 00:00:00';
$endDTExclusive = (new DateTime($end_date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$startDT_esc = mysqli_real_escape_string($conn, $startDT);
$endDT_esc   = mysqli_real_escape_string($conn, $endDTExclusive);
$qEsc        = mysqli_real_escape_string($conn, $q);

/**
 * ===== Fetch users with filters =====
 */
$where = [];

if ($q !== '') {
    $likeParts = [];
    if ($usersHasName)  $likeParts[] = "name LIKE '%$qEsc%'";
    if ($usersHasEmail) $likeParts[] = "email LIKE '%$qEsc%'";
    if ($phoneCol)      $likeParts[] = "`$phoneCol` LIKE '%$qEsc%'";
    if (count($likeParts) > 0) $where[] = "(" . implode(" OR ", $likeParts) . ")";
}

if ($usersHasCreatedAt) {
    $where[] = "created_at >= '$startDT_esc' AND created_at < '$endDT_esc'";
}

$sql = "SELECT * FROM users";
if (count($where) > 0) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC";

$users = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Users - Mitra Cafe</title>
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
    .filter-bar input[type="date"],
    .filter-bar input[type="text"]{
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      outline:none;
      font-weight: 800;
      font-size: 12px;
    }
    .filter-bar input[type="text"]{ min-width: 260px; }
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

    .actions{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .action-btn{
      padding: 8px 10px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
      font-weight: 900;
      font-size: 12px;
      cursor:pointer;
      white-space: nowrap;
    }
    .action-btn:hover{ border-color: rgba(47,107,255,.25); }

    /* Modal */
    .modal-backdrop{
      position: fixed;
      inset: 0;
      background: rgba(17,24,39,.48);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 14px;
      z-index: 999;
    }
    .modal{
      width: min(560px, 100%);
      border-radius: 22px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.98);
      box-shadow: 0 30px 80px rgba(0,0,0,.18);
      overflow: hidden;
    }
    .modal-head{
      display:flex;
      align-items:center;
      justify-content: space-between;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: rgba(47,107,255,.04);
    }
    .modal-head b{ font-size: 14px; }
    .modal-body{ padding: 16px; }
    .modal-actions{
      display:flex;
      justify-content:flex-end;
      gap: 10px;
      padding: 14px 16px;
      border-top: 1px solid var(--line);
      background: rgba(17,24,39,.02);
    }
    .field{
      display:flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 12px;
    }
    .field label{
      font-size: 12px;
      font-weight: 900;
      color: var(--muted);
    }
    .field input{
      padding: 12px 12px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.95);
      outline:none;
      font-weight: 800;
      font-size: 13px;
    }
    .help{
      font-size: 12px;
      color: var(--muted);
      line-height: 1.6;
      margin-top: 6px;
    }

    @media (max-width: 860px){
      .filter-bar{ flex-direction: column; align-items: stretch; }
      .filter-bar .right{ justify-content:flex-end; }
      .filter-bar input[type="text"]{ min-width: 0; width: 100%; }
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
          <a class="side-link" href="./orders.php">Transaksi</a>
          <a class="side-link active" href="./users.php">Pelanggan</a>
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
              <b>Customers</b>
              <span>Kelola pelanggan, filter, edit ringan, dan hapus.</span>
            </div>
          </div>
        </div>

        <!-- FLASH -->
        <?php if ($flash['msg'] !== ''): ?>
          <div class="card" style="padding:14px; margin-top:10px; border-color: <?= $flash['type']==='success'?'rgba(16,185,129,.35)':'rgba(239,68,68,.28)' ?>;">
            <b style="display:block; margin-bottom:4px;">
              <?= $flash['type']==='success' ? 'Berhasil' : 'Gagal' ?>
            </b>
            <div style="opacity:.8;"><?= htmlspecialchars($flash['msg']) ?></div>
          </div>
        <?php endif; ?>

        <!-- FILTER -->
        <form class="filter-bar" method="GET" action="./users.php">
          <div class="left">
            <label>Filter</label>

            <?php if ($usersHasCreatedAt): ?>
              <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
              <span style="opacity:.5; font-weight:900;">→</span>
              <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
            <?php else: ?>
              <span style="opacity:.65; font-size:12px; font-weight:900;">
                (Tanggal tidak tersedia: users.created_at tidak ditemukan)
              </span>
            <?php endif; ?>

            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama / email / nomor...">
          </div>

          <div class="right">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn outline" href="./users.php" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
          </div>
        </form>

        <!-- TABLE -->
        <div class="card" style="padding:18px; margin-top: 12px;">
          <div class="section-head" style="margin:0 0 10px;">
            <h3>Daftar Pelanggan</h3>
            <a href="./users.php" style="text-decoration:none;">
              Total: <?= number_format(count($users), 0, ',', '.') ?>
            </a>
          </div>

          <?php if (count($users) === 0): ?>
            <div style="opacity:.65; padding:18px; text-align:center;">
              Tidak ada pelanggan sesuai filter.
            </div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>#ID</th>
                  <?php if ($usersHasName): ?><th>Nama</th><?php endif; ?>
                  <?php if ($usersHasEmail): ?><th>Email</th><?php endif; ?>
                  <?php if ($phoneCol): ?><th>Nomor</th><?php endif; ?>
                  <?php if ($usersHasCreatedAt): ?><th>Daftar</th><?php endif; ?>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($users as $u): ?>
                  <tr>
                    <td><b>#<?= (int)$u['id'] ?></b></td>

                    <?php if ($usersHasName): ?>
                      <td style="font-weight:950;"><?= htmlspecialchars($u['name'] ?? '-') ?></td>
                    <?php endif; ?>

                    <?php if ($usersHasEmail): ?>
                      <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <?php endif; ?>

                    <?php if ($phoneCol): ?>
                      <td><?= htmlspecialchars($u[$phoneCol] ?? '-') ?></td>
                    <?php endif; ?>

                    <?php if ($usersHasCreatedAt): ?>
                      <td style="white-space:nowrap;">
                        <?= htmlspecialchars(date('d-m-Y H:i', strtotime($u['created_at'] ?? 'now'))) ?>
                      </td>
                    <?php endif; ?>

                    <td>
                      <div class="actions">
                        <button
                          class="action-btn"
                          type="button"
                          data-edit="1"
                          data-id="<?= (int)$u['id'] ?>"
                          data-name="<?= htmlspecialchars(($u['name'] ?? ''), ENT_QUOTES) ?>"
                          data-phone="<?= htmlspecialchars(($phoneCol ? ($u[$phoneCol] ?? '') : ''), ENT_QUOTES) ?>"
                        >Edit</button>

                        <button
                          class="action-btn"
                          type="button"
                          data-delete="1"
                          data-id="<?= (int)$u['id'] ?>"
                          data-label="<?= htmlspecialchars(($u['name'] ?? ($usersHasEmail ? ($u['email'] ?? 'User') : 'User')), ENT_QUOTES) ?>"
                        >Hapus</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div class="help">
              Catatan: halaman ini tidak mengubah password. Kalau kamu butuh “reset password pelanggan”, kita buat halaman khusus yang aman.
            </div>
          <?php endif; ?>
        </div>

      </main>

    </div>
  </div>
</div>

<!-- MODAL: EDIT -->
<div class="modal-backdrop" id="modalEdit">
  <div class="modal">
    <div class="modal-head">
      <b>Edit Pelanggan</b>
      <button class="action-btn" type="button" data-close="modalEdit">✕</button>
    </div>

    <form method="POST" action="./users.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId" value="">

        <?php if ($usersHasName): ?>
          <div class="field">
            <label>Nama</label>
            <input type="text" name="name" id="editName" required>
          </div>
        <?php endif; ?>

        <?php if ($phoneCol): ?>
          <div class="field">
            <label>Nomor HP</label>
            <input type="text" name="phone" id="editPhone" placeholder="08xxxxxxxxxx">
          </div>
        <?php endif; ?>

        <div class="help">
          Update yang aman: hanya data profil ringan. Email/password tidak disentuh.
        </div>
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
      <b>Hapus Pelanggan</b>
      <button class="action-btn" type="button" data-close="modalDelete">✕</button>
    </div>

    <form method="POST" action="./users.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId" value="">

        <div style="font-weight:950; margin-bottom:6px;">Yakin mau menghapus pelanggan ini?</div>
        <div style="opacity:.8;" id="deleteLabel">-</div>

        <div class="help" style="margin-top:10px;">
          Aksi ini tidak bisa dibatalkan.
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalDelete">Batal</button>
        <button class="btn" type="submit">Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openModal(id){ document.getElementById(id).style.display = 'flex'; }
  function closeModal(id){ document.getElementById(id).style.display = 'none'; }

  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close')));
  });

  document.querySelectorAll('.modal-backdrop').forEach(bg => {
    bg.addEventListener('click', (e) => { if (e.target === bg) bg.style.display = 'none'; });
  });

  document.querySelectorAll('[data-edit="1"]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value = btn.dataset.id;

      const nameEl = document.getElementById('editName');
      if (nameEl) nameEl.value = btn.dataset.name || '';

      const phoneEl = document.getElementById('editPhone');
      if (phoneEl) phoneEl.value = btn.dataset.phone || '';

      openModal('modalEdit');
    });
  });

  document.querySelectorAll('[data-delete="1"]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('deleteId').value = btn.dataset.id;
      document.getElementById('deleteLabel').textContent = btn.dataset.label || 'User';
      openModal('modalDelete');
    });
  });
</script>

</body>
</html>
