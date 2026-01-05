
<?php 
  include_once __DIR__ . '/../config/db.php';
  include_once __DIR__ . '/../includes/header.php';
  if(session_status() === PHP_SESSION_NONE) session_start();

  // (Opsional) Cek jika user belum login, arahkan ke login
  if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
  }
?>
<h2>Checkout</h2>
<!-- Form Alamat Pengiriman -->
<label>Alamat Pengiriman:</label><br/>
<input type="text" id="ship-address" name="address" placeholder="Masukkan alamat lengkap" /><br/>
<div id="map" style="width:100%;height:300px;"></div>
<p id="distance-info"></p>

<!-- Ringkasan Order -->
<h3>Ringkasan Order:</h3>
<ul>
  <?php 
    $total = 0;
    foreach($_SESSION['cart'] as $prod_id => $qty):
      $res = mysqli_query($conn, "SELECT name, price FROM products WHERE id=$prod_id");
      $prod = mysqli_fetch_assoc($res);
      $subtotal = $prod['price'] * $qty;
      $total += $subtotal;
  ?>
    <li><?php echo $prod['name'] . " x $qty - Rp " . number_format($subtotal); ?></li>
  <?php endforeach; ?>
</ul>
<p><strong>Total Bayar: Rp <?php echo number_format($total); ?></strong></p>

<button id="pay-button">Bayar Sekarang</button>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Script Google Maps API (untuk estimasi jarak) -->
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap&libraries=places" async defer></script>
<script>
  let map, marker, cafeLocation;
  function initMap() {
    // Koordinat lokasi cafe (contoh: ditentukan statis)
    cafeLocation = { lat: -2.9785, lng: 104.7458 };  // contoh koordinat
    map = new google.maps.Map(document.getElementById('map'), {
      center: cafeLocation,
      zoom: 14
    });
    // Marker cafe
    new google.maps.Marker({ position: cafeLocation, map: map, title: "Lokasi Cafe" });
    // Autocomplete untuk input alamat pengiriman
    const input = document.getElementById('ship-address');
    const autocomplete = new google.maps.places.Autocomplete(input);
    // Event saat alamat dipilih
    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place.geometry) return;
      if (marker) marker.setMap(null);
      marker = new google.maps.Marker({ position: place.geometry.location, map: map });
      map.panTo(place.geometry.location);
      // Hitung jarak dari cafe ke alamat
      const service = new google.maps.DistanceMatrixService();
      service.getDistanceMatrix({
        origins: [cafeLocation],
        destinations: [place.geometry.location],
        travelMode: 'DRIVING'
      }, (response, status) => {
        if(status === 'OK') {
          const distanceText = response.rows[0].elements[0].distance.text;
          document.getElementById('distance-info').innerText = "Jarak dari cafe: " + distanceText;
        }
      });
    });
  }
</script>

<!-- Script Midtrans Snap (untuk pembayaran) -->
<script type="text/javascript"
        src="https://app.sandbox.midtrans.com/snap/snap.js" 
        data-client-key="YOUR_MIDTRANS_CLIENT_KEY"></script>
<script>
  // Handler tombol bayar
  document.getElementById('pay-button').onclick = function() {
    // TODO: Mintalah transaction token dari server melalui AJAX
    const snapToken = "<<SNAP_TOKEN_FROM_SERVER>>";
    // Panggil pop-up pembayaran Midtrans
    window.snap.pay(snapToken, {
      onSuccess: function(result){ console.log("Payment success", result); },
      onPending: function(result){ console.log("Payment pending", result); },
      onError: function(result){ console.error("Payment error", result); }
    });
  };
</script>
