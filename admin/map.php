<?php
require_once __DIR__ . '/header.php';
$rows = $conn->query("
  SELECT username, ip_address, login_time, location_data
  FROM login_data_enhanced
  WHERE (location_data LIKE '%\"lat\"%' AND location_data LIKE '%\"lng\"%') OR (location_data LIKE '%\"latitude\"%' AND location_data LIKE '%\"longitude\"%')
  ORDER BY login_time DESC
  LIMIT 500
")->fetch_all(MYSQLI_ASSOC);
$points = [];
foreach($rows as $r){
  $loc = json_decode($r['location_data'] ?? '{}', true) ?: [];
  $lat = $loc['lat'] ?? ($loc['latitude'] ?? null);
  $lng = $loc['lng'] ?? ($loc['longitude'] ?? null);
  if ($lat && $lng) {
    $points[] = [
      'u' => $r['username'] ?: 'زائر',
      'ip' => $r['ip_address'],
      't' => $r['login_time'],
      'lat' => (float)$lat,
      'lng' => (float)$lng
    ];
  }
}
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>#map{height:70vh;border-radius:10px;border:1px solid #e5e7eb}</style>
<div class="card">
  <div style="margin-bottom:8px;font-weight:700"><i class="fas fa-map"></i> الخريطة التفاعلية (<?=count($points)?> نقطة)</div>
  <div id="map"></div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const pts = <?=json_encode($points, JSON_UNESCAPED_UNICODE)?>;
const map = L.map('map').setView([26.8206, 30.8025], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);
pts.forEach(p=>{
  const m = L.marker([p.lat, p.lng]).addTo(map);
  m.bindPopup(`<div style="font-family:Tahoma"><b>${p.u}</b><br>IP: ${p.ip}<br>الوقت: ${p.t}<br>Lat: ${p.lat}, Lng: ${p.lng}</div>`);
});
if (pts.length>0){
  const bounds = L.latLngBounds(pts.map(p=>[p.lat,p.lng]));
  map.fitBounds(bounds.pad(0.2));
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
