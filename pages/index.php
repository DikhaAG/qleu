<?php
include_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Fallback GET search
$products = [];

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT * FROM products";
if ($keyword !== '') {
    $safe = mysqli_real_escape_string($conn, $keyword);
    $sql .= " WHERE name LIKE '%$safe%'";
}
$sql .= " ORDER BY id DESC LIMIT 6";

$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) $products[] = $row;

$username = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Customer';
$isLoggedIn = isset($_SESSION['user']['id']);
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
              <!-- Search: form GET + value keyword -->
              <form class="search" method="GET" action="/pages/index.php">
                <span style="opacity:.55;">🔎</span>
                <input
                  type="text"
                  id="searchInput"
                  name="q"
                  placeholder="Cari makanan / minuman..."
                  autocomplete="off"
                  value="<?= htmlspecialchars($keyword) ?>"
                />
              </form>

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
              <div class="card product-card" data-product-id="<?= (int)$p['id'] ?>">
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

                  <!-- ✅ REVISI: Favorite + Cart icon -->
                  <div class="actions">
                    <button class="action-btn fav" title="Favorite" data-action="favorite" data-id="<?= (int)$p['id'] ?>">♡</button>
                    <button class="action-btn cart" title="Tambah ke Keranjang" data-action="cart" data-id="<?= (int)$p['id'] ?>">🛒</button>
                  </div>
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

  <!-- ✅ Modal Popup (Login required / Success / Error) -->
  <div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <b id="modalTitle">Info</b>
        <button class="action-btn" id="modalClose" title="Close">✕</button>
      </div>
      <div class="modal-body" id="modalBody">
        ...
      </div>
      <div class="modal-actions" id="modalActions">
        <button class="btn gray" id="modalOk">OK</button>
      </div>
    </div>
  </div>

<script>
/* ===== Helpers ===== */
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

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
    modalActions.innerHTML = `<button class="btn gray" id="modalOkInner">OK</button>`;
    document.getElementById('modalOkInner').onclick = closeModal;
  }
  modalBackdrop.style.display = 'flex';
}

function closeModal() {
  modalBackdrop.style.display = 'none';
}

modalClose.onclick = closeModal;
modalBackdrop.addEventListener('click', (e) => {
  if (e.target === modalBackdrop) closeModal();
});

/* ===== Search (tetap) ===== */
const searchInput = document.getElementById('searchInput');
const productGrid = document.querySelector('.grid-products');
let debounceTimer;

searchInput.addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetch(`search_product.php?q=${encodeURIComponent(searchInput.value)}`)
      .then(res => res.json())
      .then(renderProductsFromJson)
      .catch(console.error);
  }, 300);
});

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function renderProductsFromJson(products) {
  productGrid.innerHTML = '';

  if (products.length === 0) {
    productGrid.innerHTML = `
      <div style="grid-column:1/-1; text-align:center; opacity:.6; padding:40px;">
        Produk tidak ditemukan
      </div>
    `;
    return;
  }

  products.forEach(p => {
    productGrid.innerHTML += `
      <div class="card product-card" data-product-id="${Number(p.id)}">
        <div class="brandmark">Mitra Cafe</div>
        <div class="img">
          ${
            p.image
              ? `<img src="/assets/images/${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}">`
              : `<div style="font-weight:900; opacity:.5;">No Image</div>`
          }
        </div>

        <div class="meta">
          <div>
            <p class="sub">Ready</p>
            <p class="name">${escapeHtml(p.name)}</p>
          </div>

          <div class="actions">
            <button class="action-btn fav" title="Favorite" data-action="favorite" data-id="${Number(p.id)}">♡</button>
            <button class="action-btn cart" title="Tambah ke Keranjang" data-action="cart" data-id="${Number(p.id)}">🛒</button>
          </div>
        </div>

        <div class="price-row">
          <div>
            <div class="sub">Price</div>
            <div class="price">Rp ${Number(p.price).toLocaleString('id-ID')}</div>
          </div>
          <a class="btn" href="/pages/cart.php?add=${Number(p.id)}">Buy Now</a>
        </div>
      </div>
    `;
  });
}

/* ===== Favorite + Cart (baru) ===== */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;

  const action = btn.dataset.action; // favorite | cart
  const productId = Number(btn.dataset.id);

  if (!IS_LOGGED_IN) {
    openModal({
      title: 'Login Diperlukan',
      body: 'Kamu harus login terlebih dahulu sebelum menambahkan produk.',
      actionsHTML: `
        <button class="btn gray" onclick="(${closeModal.toString()})()">Batal</button>
        <a class="btn" href="/pages/login.php" style="text-decoration:none; display:inline-flex; align-items:center;">Login</a>
      `
    });
    return;
  }

  try {
    let url = '';
    let successMsg = '';

    if (action === 'favorite') {
      url = 'favorite_add.php';
      successMsg = 'Berhasil menambahkan produk ke Favorite.';
    } else if (action === 'cart') {
      url = 'cart_add_db.php';
      successMsg = 'Berhasil menambahkan produk ke Keranjang.';
    } else {
      return;
    }

    const form = new FormData();
    form.append('product_id', productId);

    const res = await fetch(url, {
      method: 'POST',
      body: form
    });

    const data = await res.json();

    if (!data.ok) {
      if (data.code === 'NOT_LOGGED_IN' && data.redirect) {
        openModal({
          title: 'Login Diperlukan',
          body: data.message || 'Kamu harus login dulu.',
          actionsHTML: `
            <button class="btn gray" onclick="(${closeModal.toString()})()">Batal</button>
            <a class="btn" href="${data.redirect}" style="text-decoration:none; display:inline-flex; align-items:center;">Login</a>
          `
        });
        return;
      }

      openModal({
        title: 'Gagal',
        body: data.message || 'Terjadi kesalahan.',
      });
      return;
    }

    // Optional UI feedback: mark favorite as active
    if (action === 'favorite') {
      btn.classList.add('active');
      btn.textContent = '♥';
    }

    openModal({
      title: 'Sukses',
      body: data.message || successMsg,
    });

  } catch (err) {
    console.error(err);
    openModal({
      title: 'Error',
      body: 'Koneksi bermasalah atau server error.',
    });
  }
});
</script>

</body>
</html>
