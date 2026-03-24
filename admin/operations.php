<?php
require_once __DIR__ . '/header.php';
$conn_login = $conn;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_util.php';
$conn_data = $conn;
$conn = $conn_login;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_id'])) {
        $id = (int)$_POST['approve_id'];
        if (supa_mode()) {
            supa_update('operations', ['id' => $id], ['status' => 'approved', 'updated_at' => date('c')]);
        } else {
            $stmt = $conn_data->prepare("UPDATE operations SET status='approved', updated_at=NOW() WHERE id=? AND (status='pending' OR status IS NULL OR status='')");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: operations.php");
        exit;
    }
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        if (supa_mode()) {
            supa_delete('operations', ['id' => $id]);
        } else {
            $stmt = $conn_data->prepare("DELETE FROM operations WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: operations.php");
        exit;
    }
}

if (supa_mode()) {
    $rows = supa_select('operations', [], 'id,invoice_no,created_at,service_name,service_number,amount,fees,total,status', null, 'id.desc');
    $pending = array_values(array_filter($rows, function($r){ return ($r['status'] ?? 'pending') === 'pending'; }));
    $pending = array_slice($pending, 0, 100);
    $count_all = count($rows);
    $count_pending = count($pending);
    $sum_amount = 0; foreach($rows as $r) $sum_amount += (float)($r['amount'] ?? 0);
    $sum_fees = 0; foreach($rows as $r) $sum_fees += (float)($r['fees'] ?? 0);
    $sum_total = 0; foreach($rows as $r) $sum_total += (float)($r['total'] ?? 0);
} else {
    $pending = $conn_data->query("SELECT id, invoice_no, created_at, service_name, service_number, amount, total, status FROM operations WHERE status='pending' ORDER BY id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
    $count_all = $conn_data->query("SELECT COUNT(*) c FROM operations")->fetch_assoc()['c'] ?? 0;
    $count_pending = $conn_data->query("SELECT COUNT(*) c FROM operations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
    $sum_amount = $conn_data->query("SELECT COALESCE(SUM(amount),0) s FROM operations")->fetch_assoc()['s'] ?? 0;
    $sum_fees = $conn_data->query("SELECT COALESCE(SUM(fees),0) s FROM operations")->fetch_assoc()['s'] ?? 0;
    $sum_total = $conn_data->query("SELECT COALESCE(SUM(total),0) s FROM operations")->fetch_assoc()['s'] ?? 0;
}
?>
<div class="cards">
  <div class="card"><div class="num"><?=$count_all?></div><div class="label">العمليات</div></div>
  <div class="card"><div class="num"><?=$count_pending?></div><div class="label">المعلقة</div></div>
  <div class="card"><div class="num"><?=number_format((float)$sum_amount,0)?></div><div class="label">المبلغ</div></div>
  <div class="card"><div class="num"><?=number_format((float)$sum_fees,0)?></div><div class="label">الرسوم</div></div>
  <div class="card"><div class="num"><?=number_format((float)$sum_total,0)?></div><div class="label">الإجمالي</div></div>
</div>
<div class="card" style="margin-top:12px;display:flex;justify-content:space-between;align-items:center">
  <div style="font-weight:700"><i class="fas fa-list"></i> تحكم العمليات</div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="../operations/create.php"><i class="fas fa-plus"></i> إنشاء فاتورة</a>
    <a class="btn" href="../operations/reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a class="btn" href="services.php"><i class="fas fa-screwdriver-wrench"></i> الخدمات</a>
    <a class="btn secondary" href="index.php"><i class="fas fa-home"></i> الرئيسية</a>
  </div>
</div>
<div class="table" style="margin-top:12px">
  <div class="filters" style="justify-content:space-between">
    <div style="font-weight:700"><i class="fas fa-hourglass-half"></i> الفواتير المعلقة</div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>الفاتورة</th><th>التاريخ</th><th>الخدمة</th><th>الرقم</th><th>المبلغ</th><th>الإجمالي</th><th>الحالة</th><th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($pending as $op): ?>
      <tr>
        <td><?=$op['id']?></td>
        <td><?=htmlspecialchars($op['invoice_no'] ?? '')?></td>
        <td><?=htmlspecialchars($op['created_at'] ?? '')?></td>
        <td><?=htmlspecialchars($op['service_name'] ?? '')?></td>
        <td><?=htmlspecialchars($op['service_number'] ?? '')?></td>
        <td><?=number_format((float)($op['amount'] ?? 0),2)?></td>
        <td><?=number_format((float)($op['total'] ?? 0),2)?></td>
        <td><span class="badge"><?=htmlspecialchars($op['status'] ?? '')?></span></td>
        <td style="display:flex;gap:6px">
          <form method="post" onsubmit="return confirm('اعتماد الفاتورة؟')">
            <input type="hidden" name="approve_id" value="<?=$op['id']?>">
            <button class="btn"><i class="fas fa-check"></i> اعتماد</button>
          </form>
          <a class="btn" href="../operations/print.php?id=<?=$op['id']?>"><i class="fas fa-print"></i> طباعة</a>
          <form method="post" onsubmit="return confirm('حذف العملية؟')">
            <input type="hidden" name="delete_id" value="<?=$op['id']?>">
            <button class="btn secondary"><i class="fas fa-trash"></i> حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
