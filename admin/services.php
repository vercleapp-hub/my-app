<?php
require_once __DIR__ . '/header.php';
$conn_login = $conn;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_util.php';
$conn_data = $conn;
$conn = $conn_login;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $conn_data->prepare("DELETE FROM service_fields WHERE service_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn_data->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php");
    exit;
}

$q = trim($_GET['q'] ?? '');
$where = "1=1";
if (supa_mode()) {
    $rows = supa_select('services', [], 'id,service_name,price,fee,merchant_profit,status', null, 'id.desc');
    if ($q !== '') {
        $services = array_values(array_filter($rows, function($r) use ($q){ return mb_stripos($r['service_name'] ?? '', $q) !== false; }));
    } else {
        $services = $rows;
    }
    $total = count($rows);
    $active = count(array_filter($rows, function($r){ return (int)($r['status']??0)===1 || in_array($r['status']??'', ['1','t','true'], true); }));
    $sum_price = 0; foreach($rows as $r) $sum_price += (float)($r['price'] ?? 0);
} else {
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $conn_data->prepare("SELECT COUNT(*) FROM services WHERE service_name LIKE ?");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $stmt->bind_result($total_filtered);
        $stmt->fetch();
        $stmt->close();
        $services_stmt = $conn_data->prepare("SELECT id, service_name, price, fee, merchant_profit, status FROM services WHERE service_name LIKE ? ORDER BY id DESC LIMIT 300");
        $services_stmt->bind_param("s", $like);
        $services_stmt->execute();
        $services = $services_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $services_stmt->close();
    } else {
        $total_filtered = null;
        $services = $conn_data->query("SELECT id, service_name, price, fee, merchant_profit, status FROM services ORDER BY id DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
    }
    $total = $conn_data->query("SELECT COUNT(*) c FROM services")->fetch_assoc()['c'] ?? 0;
    $active = $conn_data->query("SELECT COUNT(*) c FROM services WHERE status=1 OR status='1' OR status='t' OR status='true'")->fetch_assoc()['c'] ?? 0;
    $sum_price = $conn_data->query("SELECT SUM(price) s FROM services")->fetch_assoc()['s'] ?? 0;
}
?>
<div class="cards">
  <div class="card"><div class="num"><?= (int)$total ?></div><div class="label">الخدمات</div></div>
  <div class="card"><div class="num"><?= (int)$active ?></div><div class="label">المفعلة</div></div>
  <div class="card"><div class="num"><?= number_format((float)$sum_price, 0) ?></div><div class="label">إجمالي السعر</div></div>
</div>
<div class="card" style="margin-top:12px;display:flex;justify-content:space-between;align-items:center">
  <div style="font-weight:700"><i class="fas fa-screwdriver-wrench"></i> إدارة الخدمات</div>
  <div>
    <a class="btn" href="service_form.php"><i class="fas fa-plus"></i> إضافة خدمة</a>
    <a class="btn secondary" href="index.php"><i class="fas fa-home"></i> الرئيسية</a>
  </div>
</div>
<div class="table" style="margin-top:12px">
  <div class="filters">
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="text" name="q" placeholder="🔍 بحث باسم الخدمة..." value="<?= htmlspecialchars($q) ?>">
      <button class="btn">بحث</button>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>الاسم</th><th>السعر</th><th>الرسوم</th><th>الربح</th><th>الحالة</th><th>الحقول</th><th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($services as $s):
        $fid = (int)$s['id'];
        if (supa_mode()) {
            $fields = db_count_supabase('service_fields', ['service_id' => $fid]);
        } else {
            $fields = $conn_data->query("SELECT COUNT(*) c FROM service_fields WHERE service_id=".$fid)->fetch_assoc()['c'] ?? 0;
        }
      ?>
      <tr>
        <td><?= $fid ?></td>
        <td><?= htmlspecialchars($s['service_name'] ?? '') ?></td>
        <td><?= number_format((float)($s['price'] ?? 0), 2) ?></td>
        <td><?= number_format((float)($s['fee'] ?? 0), 2) ?></td>
        <td><?= number_format((float)($s['merchant_profit'] ?? 0), 2) ?></td>
        <td><span class="badge <?= ($s['status']??0) ? 'ok':'fail' ?>"><?= ($s['status']??0) ? 'مفعلة' : 'معطلة' ?></span></td>
        <td><?= (int)$fields ?></td>
        <td>
          <a class="btn" href="service_form.php?id=<?= $fid ?>"><i class="fas fa-pen"></i> تعديل</a>
          <form method="post" style="display:inline" onsubmit="return confirm('حذف الخدمة؟')">
            <input type="hidden" name="delete_id" value="<?= $fid ?>">
            <button class="btn secondary"><i class="fas fa-trash"></i> حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
