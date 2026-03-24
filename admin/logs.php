<?php
require_once __DIR__ . '/header.php';

$status = $_GET['status'] ?? '';
$loc = $_GET['loc'] ?? '';
$q = trim($_GET['q'] ?? '');

function build_where($status, $loc, $q, $conn) {
    $where = "1=1";
    if ($status === 'success') $where .= " AND 1=1";
    if ($status === 'failed') $where .= " AND 1=0"; // placeholder, we query failed separately
    if ($loc === 'with') $where .= " AND ((location_data LIKE '%\"lat\"%' AND location_data LIKE '%\"lng\"%') OR (location_data LIKE '%\"latitude\"%' AND location_data LIKE '%\"longitude\"%'))";
    if ($loc === 'without') $where .= " AND NOT ((location_data LIKE '%\"lat\"%' AND location_data LIKE '%\"lng\"%') OR (location_data LIKE '%\"latitude\"%' AND location_data LIKE '%\"longitude\"%'))";
    if ($q !== '') {
        $safe = "%".$conn->real_escape_string($q)."%";
        $where .= " AND (username LIKE '{$safe}' OR ip_address LIKE '{$safe}' OR user_agent LIKE '{$safe}')";
    }
    return $where;
}

$where = build_where($status, $loc, $q, $conn);
$success = $conn->query("SELECT username, login_time, ip_address, user_agent, location_data, system_data, browser_data, network_data FROM login_data_enhanced WHERE $where ORDER BY login_time DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$failed = $conn->query("SELECT id, username, ip_address, attempt_time, user_agent FROM login_attempts WHERE successful=0 ORDER BY attempt_time DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
?>
<div class="table">
  <div class="filters">
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select name="status">
        <option value="">كل الحالات</option>
        <option value="success" <?= $status==='success'?'selected':'' ?>>ناجح</option>
        <option value="failed" <?= $status==='failed'?'selected':'' ?>>فاشل</option>
      </select>
      <select name="loc">
        <option value="">كل المواقع</option>
        <option value="with" <?= $loc==='with'?'selected':'' ?>>بإحداثيات</option>
        <option value="without" <?= $loc==='without'?'selected':'' ?>>بدون إحداثيات</option>
      </select>
      <input type="text" name="q" placeholder="بحث: المستخدم/IP/المتصفح" value="<?=htmlspecialchars($q)?>">
      <button class="btn">تصفية</button>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>المستخدم</th><th>الوقت</th><th>IP</th><th>الدولة/المدينة</th><th>الإحداثيات</th><th>النظام</th><th>المتصفح</th><th>الشبكة</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($success as $i=>$row):
        $locj = json_decode($row['location_data'] ?? '{}', true) ?: [];
        $sys = json_decode($row['system_data'] ?? '{}', true) ?: [];
        $br = json_decode($row['browser_data'] ?? '{}', true) ?: [];
        $net = json_decode($row['network_data'] ?? '{}', true) ?: [];
        $country = $locj['country'] ?? '';
        $city = $locj['city'] ?? '';
        $lat = $locj['lat'] ?? ($locj['latitude'] ?? null);
        $lng = $locj['lng'] ?? ($locj['longitude'] ?? null);
      ?>
      <tr>
        <td><?=$i+1?></td>
        <td><?=htmlspecialchars($row['username'] ?? 'زائر')?></td>
        <td><?=htmlspecialchars($row['login_time'])?></td>
        <td><span class="badge"><?=htmlspecialchars($row['ip_address'])?></span></td>
        <td><?=htmlspecialchars(trim("$country / $city", " /"))?:'غير متوفر'?></td>
        <td><?=($lat&&$lng)? (round($lat,4).', '.round($lng,4)) : '—'?></td>
        <td><?=htmlspecialchars($sys['os'] ?? 'غير معروف')?></td>
        <td><?=htmlspecialchars($br['name'] ?? 'غير معروف')?></td>
        <td><?=htmlspecialchars($net['connection'] ?? 'غير معروف')?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="table" style="margin-top:14px">
  <table>
    <thead>
      <tr>
        <th>#</th><th>المستخدم</th><th>الوقت</th><th>IP</th><th>النظام</th><th>المتصفح</th><th>الجهاز</th><th>الحالة</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($failed as $j=>$f): 
        $ua = strtolower($f['user_agent'] ?? '');
        $os = 'غير معروف';
        $browser = 'غير معروف';
        $device = 'غير معروف';
        if ($ua) {
          if (strpos($ua,'windows')!==false) $os='Windows';
          elseif (strpos($ua,'mac')!==false) $os='macOS';
          elseif (strpos($ua,'linux')!==false) $os='Linux';
          elseif (strpos($ua,'android')!==false) $os='Android';
          elseif (strpos($ua,'iphone')!==false||strpos($ua,'ipad')!==false||strpos($ua,'ipod')!==false) $os='iOS';
          if (strpos($ua,'chrome')!==false) $browser='Chrome';
          elseif (strpos($ua,'firefox')!==false) $browser='Firefox';
          elseif (strpos($ua,'safari')!==false && strpos($ua,'chrome')===false) $browser='Safari';
          elseif (strpos($ua,'edge')!==false) $browser='Edge';
          elseif (strpos($ua,'opera')!==false) $browser='Opera';
          $device = (strpos($ua,'mobile')!==false||$os==='Android'||$os==='iOS') ? (strpos($ua,'tablet')!==false?'تابلت':'هاتف') : 'كمبيوتر';
        }
      ?>
      <tr>
        <td><?=$f['id']?></td>
        <td><?=htmlspecialchars($f['username'] ?? '')?></td>
        <td><?=htmlspecialchars($f['attempt_time'] ?? '')?></td>
        <td><span class="badge fail"><?=htmlspecialchars($f['ip_address'] ?? '')?></span></td>
        <td><?=$os?></td>
        <td><?=$browser?></td>
        <td><?=$device?></td>
        <td><span class="badge fail">فاشل</span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
