<?php
require_once __DIR__ . '/header.php';

function count_val($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_row();
    return (int)($row[0] ?? 0);
}

$total_logs = count_val($conn, "SELECT COUNT(*) FROM login_data_enhanced");
$unique_ips = count_val($conn, "SELECT COUNT(DISTINCT ip_address) FROM login_data_enhanced");
$today_logs = count_val($conn, "SELECT COUNT(*) FROM login_data_enhanced WHERE DATE(login_time)=CURDATE()");
$active_users = count_val($conn, "SELECT COUNT(DISTINCT username) FROM login_data_enhanced WHERE TIMESTAMPDIFF(MINUTE, login_time, NOW())<=60");
$success_count = $total_logs;
$failed_count = count_val($conn, "SELECT COUNT(*) FROM login_attempts WHERE successful=0");
$locations_count = count_val($conn, "SELECT COUNT(*) FROM login_data_enhanced WHERE (location_data LIKE '%\"lat\"%' AND location_data LIKE '%\"lng\"%') OR (location_data LIKE '%\"latitude\"%' AND location_data LIKE '%\"longitude\"%')");

$logs = $conn->query("
    SELECT username, login_time, ip_address, user_agent, location_data, system_data, browser_data, network_data 
    FROM login_data_enhanced 
    ORDER BY login_time DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<div class="cards">
  <div class="card"><div class="num"><?=$total_logs?></div><div class="label">إجمالي السجلات</div></div>
  <div class="card"><div class="num"><?=$unique_ips?></div><div class="label">IP فريد</div></div>
  <div class="card"><div class="num"><?=$today_logs?></div><div class="label">سجلات اليوم</div></div>
  <div class="card"><div class="num"><?=$active_users?></div><div class="label">مستخدمين نشطين (ساعة)</div></div>
  <div class="card"><div class="num"><?=$success_count?></div><div class="label">دخول ناجح</div></div>
  <div class="card"><div class="num"><?=$failed_count?></div><div class="label">دخول فاشل</div></div>
  <div class="card"><div class="num"><?=$locations_count?></div><div class="label">مواقع مسجلة</div></div>
</div>

<div class="table" style="margin-top:14px">
  <div class="filters">
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select name="status">
        <option value="">كل الحالات</option>
        <option value="success">ناجح</option>
        <option value="failed">فاشل</option>
      </select>
      <select name="loc">
        <option value="">كل المواقع</option>
        <option value="with">بإحداثيات</option>
        <option value="without">بدون إحداثيات</option>
      </select>
      <input type="text" name="q" placeholder="بحث: المستخدم/IP/المتصفح" value="<?=htmlspecialchars($_GET['q'] ?? '')?>">
      <button class="btn">تصفية</button>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>المستخدم</th><th>الوقت</th><th>IP</th><th>الدولة/المدينة</th><th>الإحداثيات</th><th>الجهاز</th><th>النظام</th><th>المتصفح</th><th>الشبكة</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($logs as $i=>$row):
        $loc = json_decode($row['location_data'] ?? '{}', true) ?: [];
        $sys = json_decode($row['system_data'] ?? '{}', true) ?: [];
        $brw = json_decode($row['browser_data'] ?? '{}', true) ?: [];
        $net = json_decode($row['network_data'] ?? '{}', true) ?: [];
        $country = $loc['country'] ?? '';
        $city = $loc['city'] ?? '';
        $lat = $loc['lat'] ?? ($loc['latitude'] ?? null);
        $lng = $loc['lng'] ?? ($loc['longitude'] ?? null);
    ?>
      <tr>
        <td><?=$i+1?></td>
        <td><?=htmlspecialchars($row['username'] ?? 'زائر')?></td>
        <td><?=htmlspecialchars($row['login_time'])?></td>
        <td><span class="badge"><?=htmlspecialchars($row['ip_address'])?></span></td>
        <td><?=htmlspecialchars(trim("$country / $city", " /"))?:'غير متوفر'?></td>
        <td><?=($lat&&$lng)? (round($lat,4).', '.round($lng,4)) : '—'?></td>
        <td><?=htmlspecialchars($sys['device'] ?? 'غير معروف')?></td>
        <td><?=htmlspecialchars($sys['os'] ?? 'غير معروف')?></td>
        <td><?=htmlspecialchars($brw['name'] ?? 'غير معروف')?></td>
        <td><?=htmlspecialchars($net['connection'] ?? 'غير معروف')?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
