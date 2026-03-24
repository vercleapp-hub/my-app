<?php
require_once __DIR__ . '/header.php';
$q = trim($_GET['q'] ?? '');
$where = '1=1';
if ($q !== '') {
  $safe = "%".$conn->real_escape_string($q)."%";
  $stmt = $conn->prepare("SELECT id, username, email, role, status, last_login, login_count, balance FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 200");
  $stmt->bind_param("ss", $safe, $safe);
  $stmt->execute();
  $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  $users = $conn->query("SELECT id, username, email, role, status, last_login, login_count, balance FROM users ORDER BY id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="table">
  <div class="filters">
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="text" name="q" placeholder="بحث: اسم/بريد" value="<?=htmlspecialchars($q)?>">
      <button class="btn">بحث</button>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>المستخدم</th><th>البريد</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th><th>عدد الدخول</th><th>الرصيد</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($users as $u): ?>
      <tr>
        <td><?=$u['id']?></td>
        <td><?=htmlspecialchars($u['username'] ?? '')?></td>
        <td><?=htmlspecialchars($u['email'] ?? '')?></td>
        <td><span class="badge"><?=htmlspecialchars($u['role'] ?? '')?></span></td>
        <td><span class="badge <?=($u['status']??'')=='1'||($u['status']??'')=='active'?'ok':'fail'?>"><?=htmlspecialchars($u['status'] ?? '')?></span></td>
        <td><?=htmlspecialchars($u['last_login'] ?? 'غير معروف')?></td>
        <td><?= (int)($u['login_count'] ?? 0) ?></td>
        <td><?= number_format((float)($u['balance'] ?? 0), 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
