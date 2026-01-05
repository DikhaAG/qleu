<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * ✅ Revisi #1: Jika admin sudah login → redirect ke dashboard
 */
if (isset($_SESSION['admin']['id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$error = '';
$success = false;

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $u = mysqli_real_escape_string($conn, $username);
        $res = mysqli_query($conn, "SELECT id, username, password_hash, name FROM admins WHERE username = '$u' LIMIT 1");

        if ($res && mysqli_num_rows($res) === 1) {
            $admin = mysqli_fetch_assoc($res);

            if (password_verify($password, $admin['password_hash'])) {
                // ✅ Login sukses: set session admin
                $_SESSION['admin'] = [
                    'id' => (int)$admin['id'],
                    'username' => $admin['username'],
                    'name' => $admin['name'],
                ];
                $success = true;
            } else {
                $error = 'Username atau password salah.';
            }
        } else {
            $error = 'Username atau password salah. (tidak ditemukan)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    /* Sedikit penyesuaian khusus halaman login (tetap 1 gaya dengan index) */
    .login-wrap{
      min-height: calc(100vh - 68px);
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 10px;
    }
    .login-card{
      width: min(520px, 100%);
      padding: 20px;
    }
    .login-title{
      margin: 0 0 6px;
      font-size: 18px;
      font-weight: 900;
    }
    .login-sub{
      margin: 0 0 16px;
      color: var(--muted);
      font-size: 13px;
    }
    .form{
      display:flex;
      flex-direction:column;
      gap: 12px;
      margin-top: 10px;
    }
    .field label{
      display:block;
      font-size: 12px;
      color: var(--muted);
      font-weight: 800;
      margin-bottom: 6px;
    }
    .field input{
      width: 100%;
      padding: 12px 14px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.92);
      outline: none;
      font-size: 14px;
    }
    .field input:focus{
      border-color: rgba(47,107,255,.35);
      box-shadow: 0 0 0 4px rgba(47,107,255,.12);
    }
    .row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      margin-top: 6px;
    }
    .hint{
      font-size: 12px;
      color: var(--muted);
    }
    .hint code{
      background: rgba(47,107,255,.08);
      border: 1px solid rgba(47,107,255,.14);
      padding: 2px 6px;
      border-radius: 10px;
      font-weight: 800;
      color: var(--primary);
    }
  </style>
</head>
<body>
  <!-- UI/UX sama: shell → app → app-inner -->
  <div class="shell">
    <div class="app">
      <div class="app-inner">

        <!-- Sidebar versi admin login (ringkas tapi feel sama) -->
        <aside class="sidebar">
          <div class="brand">
            <div class="logo"></div>
            <div class="title">
              <b>Admin Panel</b>
              <span>Mitra Cafe</span>
            </div>
          </div>

          <div class="side-section">Akses</div>
          <nav class="side-nav">
            <a class="side-link active" href="../admin/login.php">Login Admin</a>
            <a class="side-link" href="../pages/index.php">Kembali ke Toko</a>
          </nav>

          <div class="side-bottom">
            <div class="toggle">
              <span>Light Mode</span>
              <div class="pill" title="Tema (dummy)"></div>
            </div>
          </div>
        </aside>

        <!-- Main -->
        <main class="main">
          <div class="topbar">
            <div class="greeting">
              <div class="avatar"></div>
              <div class="text">
                <b>Login Admin</b>
                <span>Masuk untuk mengelola produk dan transaksi.</span>
              </div>
            </div>
            <div class="top-actions">
              <button class="icon-btn" title="Settings">⚙️</button>
            </div>
          </div>

          <div class="login-wrap">
            <div class="card login-card">
              <p class="login-title">Masuk ke Dashboard</p>
              <p class="login-sub">Gunakan akun admin yang terdaftar.</p>

              <form class="form" action="login.php" method="post" novalidate>
                <div class="field">
                  <label>Username</label>
                  <input type="text" name="username" required placeholder="contoh: admin" />
                </div>

                <div class="field">
                  <label>Password</label>
                  <input type="password" name="password" required placeholder="••••••••" />
                </div>

                <div class="row">
                  <span class="hint">Demo: <code>admin</code> / <code>admin123</code></span>
                </div>

                <div class="row" style="justify-content:flex-end; margin-top: 10px;">
                  <button class="btn" type="submit">Login</button>
                </div>
              </form>
            </div>
          </div>

        </main>

      </div>
    </div>
  </div>

  <!-- Modal popup (style sudah ada di CSS yang kita tambah sebelumnya) -->
  <div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <b id="modalTitle">Info</b>
        <button class="action-btn" id="modalClose" title="Close">✕</button>
      </div>
      <div class="modal-body" id="modalBody">...</div>
      <div class="modal-actions" id="modalActions">
        <button class="btn gray" id="modalOk">OK</button>
      </div>
    </div>
  </div>

<script>
  const modalBackdrop = document.getElementById('modalBackdrop');
  const modalTitle = document.getElementById('modalTitle');
  const modalBody = document.getElementById('modalBody');
  const modalActions = document.getElementById('modalActions');
  const modalClose = document.getElementById('modalClose');
  const modalOk = document.getElementById('modalOk');

  function openModal({title='Info', body='', actionsHTML=null}) {
    modalTitle.textContent = title;
    modalBody.innerHTML = body;
    if (actionsHTML) {
      modalActions.innerHTML = actionsHTML;
    } else {
      modalActions.innerHTML = `<button class="btn gray" id="okBtn">OK</button>`;
      document.getElementById('okBtn').onclick = closeModal;
    }
    modalBackdrop.style.display = 'flex';
  }

  function closeModal(){
    modalBackdrop.style.display = 'none';
  }

  modalClose.onclick = closeModal;
  modalBackdrop.addEventListener('click', (e) => {
    if (e.target === modalBackdrop) closeModal();
  });

  <?php if ($error !== ''): ?>
    // ❌ Login gagal → popup
    openModal({
      title: 'Login Gagal',
      body: <?= json_encode($error) ?>,
    });
  <?php elseif ($success): ?>
    // ✅ Login sukses → popup lalu redirect
    openModal({
      title: 'Login Berhasil',
      body: 'Selamat datang! Mengalihkan ke dashboard...',
      actionsHTML: `<a class="btn" href="../admin/dashboard.php" style="text-decoration:none;">Masuk Dashboard</a>`
    });
    setTimeout(() => {
      window.location.href = '../admin/dashboard.php';
    }, 900);
  <?php endif; ?>
</script>
</body>
</html>
