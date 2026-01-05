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

/**
 * ===== Konfigurasi tabel/kolom admin (yang wajib) =====
 */
$adminTable  = 'admins';
$colId       = 'id';
$colName     = 'name';
$colUsername = 'username';

/**
 * Password column auto-detect (kandidat umum)
 */
$passwordCandidates = [
    'password',
    'password_hash',
    'pass',
    'passwd',
    'admin_password',
    'admin_pass',
    'hash',
];

/**
 * Helpers
 */
function post($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }
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
function detectPasswordColumn(mysqli $conn, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (hasColumn($conn, $table, $col)) return $col;
    }
    return null;
}
function isStrongPassword(string $pw): array {
    $errors = [];
    if (strlen($pw) < 8) $errors[] = 'Minimal 8 karakter.';
    if (!preg_match('/[a-z]/', $pw)) $errors[] = 'Harus ada huruf kecil.';
    if (!preg_match('/[A-Z]/', $pw)) $errors[] = 'Harus ada huruf besar.';
    if (!preg_match('/[0-9]/', $pw)) $errors[] = 'Harus ada angka.';
    return $errors;
}

/**
 * ===== Pastikan tabel & kolom wajib ada =====
 */
if (!hasTable($conn, $adminTable)) {
    die("Tabel '$adminTable' tidak ditemukan. Sesuaikan konfigurasi di admin/profile.php.");
}
foreach ([$colId,$colName,$colUsername] as $col) {
    if (!hasColumn($conn, $adminTable, $col)) {
        die("Kolom '$col' tidak ditemukan pada tabel '$adminTable'. Sesuaikan konfigurasi di admin/profile.php.");
    }
}

/**
 * Auto-detect password column
 */
$colPassword = detectPasswordColumn($conn, $adminTable, $passwordCandidates);
$passwordFeatureEnabled = ($colPassword !== null);

/**
 * Data admin login
 */
$adminId = (int)($_SESSION['admin']['id'] ?? 0);
if ($adminId <= 0) {
    header('Location: ./login.php');
    exit;
}

/**
 * Ambil data admin terbaru dari DB
 */
$adminRow = null;
$qAdmin = mysqli_query($conn, "SELECT `$colId`, `$colName`, `$colUsername` FROM `$adminTable` WHERE `$colId`=$adminId LIMIT 1");
if ($qAdmin) $adminRow = mysqli_fetch_assoc($qAdmin);

if (!$adminRow) {
    session_destroy();
    header('Location: ./login.php');
    exit;
}

$adminName = $adminRow[$colName] ?? 'Admin';
$adminUsername = $adminRow[$colUsername] ?? 'admin';

/**
 * Flash message
 */
$flash = ['type' => '', 'msg' => ''];

/**
 * ===== Handle POST actions =====
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'update_profile') {
        $name = post('name');
        $username = post('username');

        if ($name === '' || $username === '') {
            $flash = ['type' => 'error', 'msg' => 'Nama dan username wajib diisi.'];
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
            $flash = ['type' => 'error', 'msg' => 'Username hanya boleh berisi huruf/angka/._- dan panjang 3-32 karakter.'];
        } else {
            $nameEsc = mysqli_real_escape_string($conn, $name);
            $userEsc = mysqli_real_escape_string($conn, $username);

            $qCheck = mysqli_query($conn, "SELECT `$colId` FROM `$adminTable` WHERE `$colUsername`='$userEsc' AND `$colId`<>$adminId LIMIT 1");
            if ($qCheck && mysqli_num_rows($qCheck) > 0) {
                $flash = ['type' => 'error', 'msg' => 'Username sudah digunakan admin lain.'];
            } else {
                $ok = mysqli_query($conn, "UPDATE `$adminTable` SET `$colName`='$nameEsc', `$colUsername`='$userEsc' WHERE `$colId`=$adminId LIMIT 1");
                if ($ok) {
                    $_SESSION['admin']['name'] = $name;
                    $_SESSION['admin']['username'] = $username;

                    $adminName = $name;
                    $adminUsername = $username;

                    $flash = ['type' => 'success', 'msg' => 'Profil berhasil diupdate.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Gagal update profil: ' . mysqli_error($conn)];
                }
            }
        }
    }

    if ($action === 'change_password') {
        if (!$passwordFeatureEnabled) {
            $flash = ['type' => 'error', 'msg' => 'Fitur ubah password nonaktif karena kolom password tidak ditemukan di tabel admins.'];
        } else {
            $old = post('old_password');
            $new = post('new_password');
            $confirm = post('confirm_password');

            if ($old === '' || $new === '' || $confirm === '') {
                $flash = ['type' => 'error', 'msg' => 'Semua field password wajib diisi.'];
            } elseif ($new !== $confirm) {
                $flash = ['type' => 'error', 'msg' => 'Password baru dan konfirmasi tidak sama.'];
            } elseif ($old === $new) {
                $flash = ['type' => 'error', 'msg' => 'Password baru tidak boleh sama dengan password lama.'];
            } else {
                $strengthErrors = isStrongPassword($new);
                if (count($strengthErrors) > 0) {
                    $flash = ['type' => 'error', 'msg' => 'Password baru belum kuat: ' . implode(' ', $strengthErrors)];
                } else {
                    $qPw = mysqli_query($conn, "SELECT `$colPassword` AS pw FROM `$adminTable` WHERE `$colId`=$adminId LIMIT 1");
                    $rowPw = $qPw ? mysqli_fetch_assoc($qPw) : null;
                    $hash = $rowPw['pw'] ?? '';

                    $okVerify = false;
                    if ($hash && password_verify($old, $hash)) {
                        $okVerify = true;
                    } elseif ($hash === $old) {
                        // fallback legacy (tidak ideal)
                        $okVerify = true;
                    }

                    if (!$okVerify) {
                        $flash = ['type' => 'error', 'msg' => 'Password lama salah.'];
                    } else {
                        $newHash = password_hash($new, PASSWORD_DEFAULT);
                        $newHashEsc = mysqli_real_escape_string($conn, $newHash);
                        $ok = mysqli_query($conn, "UPDATE `$adminTable` SET `$colPassword`='$newHashEsc' WHERE `$colId`=$adminId LIMIT 1");
                        $flash = $ok
                            ? ['type' => 'success', 'msg' => 'Password berhasil diubah.']
                            : ['type' => 'error', 'msg' => 'Gagal ubah password: ' . mysqli_error($conn)];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Profile - Mitra Cafe</title>
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
    .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap: var(--gap); margin-top: 14px; }
    .info-row{
      display:flex; align-items:center; justify-content: space-between; gap: 12px;
      padding: 12px 14px; border-radius: 18px; border: 1px solid var(--line);
      background: rgba(255,255,255,.92); margin-top: 12px;
    }
    .info-row b{ font-size: 13px; }
    .info-row .muted{ color: var(--muted); font-weight: 900; font-size: 12px; }

    .action-list{ display:flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .action-btn{
      padding: 10px 12px; border-radius: 16px; border: 1px solid var(--line);
      background: rgba(255,255,255,.92); font-weight: 900; font-size: 12px; cursor:pointer;
    }
    .action-btn:hover{ border-color: rgba(47,107,255,.25); }

    .warn{
      padding: 12px 14px;
      border-radius: 18px;
      border: 1px solid rgba(245,158,11,.35);
      background: rgba(245,158,11,.08);
      font-weight: 850;
      font-size: 12px;
      color: #92400e;
      margin-top: 12px;
      line-height: 1.6;
    }

    .modal-backdrop{
      position: fixed; inset: 0; background: rgba(17,24,39,.48);
      display: none; align-items: center; justify-content: center; padding: 14px; z-index: 999;
    }
    .modal{
      width: min(620px, 100%); border-radius: 22px; border: 1px solid var(--line);
      background: rgba(255,255,255,.98); box-shadow: 0 30px 80px rgba(0,0,0,.18); overflow: hidden;
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
    .field{ display:flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
    .field label{ font-size: 12px; font-weight: 900; color: var(--muted); }
    .field input{
      padding: 12px 12px; border-radius: 16px; border: 1px solid var(--line);
      background: rgba(255,255,255,.95); outline:none; font-weight: 800; font-size: 13px;
    }
    .help{ font-size: 12px; color: var(--muted); line-height: 1.6; margin-top: 8px; }

    @media (max-width: 980px){
      .grid-2{ grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="shell">
  <div class="app">
    <div class="app-inner">

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
          <a class="side-link" href="./users.php">Pelanggan</a>
          <a class="side-link active" href="./profile.php">Profil</a>
        </nav>

        <div class="side-section" style="margin-top:18px;">Shortcut</div>
        <nav class="side-nav">
          <a class="side-link" href="../pages/index.php">Lihat Toko</a>
          <a class="side-link" href="./logout.php">Logout</a>
        </nav>
      </aside>

      <main class="main">

        <div class="topbar">
          <div class="greeting">
            <div class="avatar"></div>
            <div class="text">
              <b>Admin Profile</b>
              <span>Update profil dan keamanan akun admin.</span>
            </div>
          </div>

          <div class="top-actions">
            <button class="icon-btn" title="Settings">⚙️</button>
          </div>
        </div>

        <?php if ($flash['msg'] !== ''): ?>
          <div class="card" style="padding:14px; margin-top:10px; border-color: <?= $flash['type']==='success'?'rgba(16,185,129,.35)':'rgba(239,68,68,.28)' ?>;">
            <b style="display:block; margin-bottom:4px;">
              <?= $flash['type']==='success' ? 'Berhasil' : 'Gagal' ?>
            </b>
            <div style="opacity:.85;"><?= htmlspecialchars($flash['msg']) ?></div>
          </div>
        <?php endif; ?>

        <div class="grid-2">

          <div class="card" style="padding:18px;">
            <div class="section-head" style="margin:0 0 10px;">
              <h3>Profil</h3>
              <a href="./dashboard.php">Dashboard</a>
            </div>

            <div class="info-row">
              <div>
                <div class="muted">Nama</div>
                <b><?= htmlspecialchars($adminName) ?></b>
              </div>
              <button class="action-btn" type="button" id="btnEditProfile">Edit</button>
            </div>

            <div class="info-row">
              <div>
                <div class="muted">Username</div>
                <b><?= htmlspecialchars($adminUsername) ?></b>
              </div>
              <button class="action-btn" type="button" id="btnEditProfile2">Edit</button>
            </div>

            <div class="help">
              Username diizinkan: <b>a-z</b> <b>A-Z</b> <b>0-9</b> <b>._-</b> (3–32 karakter).
            </div>
          </div>

          <div class="card" style="padding:18px;">
            <div class="section-head" style="margin:0 0 10px;">
              <h3>Keamanan</h3>
              <a href="./logout.php">Logout</a>
            </div>

            <div class="info-row">
              <div>
                <div class="muted">Password</div>
                <b><?= $passwordFeatureEnabled ? '••••••••' : 'N/A' ?></b>
              </div>
              <button class="action-btn" type="button" id="btnChangePassword" <?= $passwordFeatureEnabled ? '' : 'disabled style="opacity:.5; cursor:not-allowed;"' ?>>
                Ubah
              </button>
            </div>

            <?php if (!$passwordFeatureEnabled): ?>
              <div class="warn">
                Kolom password tidak ditemukan di tabel <b><?= htmlspecialchars($adminTable) ?></b>.<br>
                Aku sudah coba cari: <code><?= htmlspecialchars(implode(', ', $passwordCandidates)) ?></code><br>
                Solusi: samakan nama kolom password di DB atau tambahkan kolom hash password.
              </div>
            <?php else: ?>
              <div class="help">
                Password baru: minimal <b>8</b> karakter, ada <b>huruf besar</b>, <b>huruf kecil</b>, dan <b>angka</b>.
              </div>
            <?php endif; ?>

            <div class="action-list">
              <a class="btn outline" href="./dashboard.php" style="text-decoration:none;">Kembali</a>
              <a class="btn" href="../pages/index.php" style="text-decoration:none;">Lihat Toko</a>
            </div>
          </div>

        </div>

      </main>

    </div>
  </div>
</div>

<div class="modal-backdrop" id="modalProfile">
  <div class="modal">
    <div class="modal-head">
      <b>Update Profil</b>
      <button class="action-btn" type="button" data-close="modalProfile">✕</button>
    </div>

    <form method="POST" action="./profile.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="update_profile">

        <div class="field">
          <label>Nama</label>
          <input type="text" name="name" value="<?= htmlspecialchars($adminName) ?>" required>
        </div>

        <div class="field">
          <label>Username</label>
          <input type="text" name="username" value="<?= htmlspecialchars($adminUsername) ?>" required>
        </div>

        <div class="help">
          Setelah update, session admin ikut disegarkan.
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalProfile">Batal</button>
        <button class="btn" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="modalPassword">
  <div class="modal">
    <div class="modal-head">
      <b>Ubah Password</b>
      <button class="action-btn" type="button" data-close="modalPassword">✕</button>
    </div>

    <form method="POST" action="./profile.php" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" name="action" value="change_password">

        <div class="field">
          <label>Password Lama</label>
          <input type="password" name="old_password" required>
        </div>

        <div class="field">
          <label>Password Baru</label>
          <input type="password" name="new_password" minlength="8" required>
        </div>

        <div class="field">
          <label>Konfirmasi Password Baru</label>
          <input type="password" name="confirm_password" minlength="8" required>
        </div>

        <div class="help">
          Password yang kuat itu kayak kopi enak: pahit dikit tapi bikin aman.
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn outline" type="button" data-close="modalPassword">Batal</button>
        <button class="btn" type="submit">Ubah</button>
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

  document.getElementById('btnEditProfile')?.addEventListener('click', () => openModal('modalProfile'));
  document.getElementById('btnEditProfile2')?.addEventListener('click', () => openModal('modalProfile'));

  const btnPw = document.getElementById('btnChangePassword');
  if (btnPw && !btnPw.disabled) {
    btnPw.addEventListener('click', () => openModal('modalPassword'));
  }
</script>

</body>
</html>
