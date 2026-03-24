<?php
require_once __DIR__ . '/header.php';
$conn_login = $conn;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_util.php';
$conn_data = $conn;
$conn = $conn_login;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$service = null;
$fields = [];
if ($id > 0) {
    if (supa_mode()) {
        $rows = supa_select('services', ['id' => $id], 'id,service_name,description,price,fee,merchant_profit,status', 1, null);
        $service = $rows[0] ?? null;
        $fields = supa_select('service_fields', ['service_id' => $id], 'id,name,label,input_type,required', null, 'id.asc');
    } else {
        $stmt = $conn_data->prepare("SELECT id, service_name, description, price, fee, merchant_profit, status FROM services WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $fields = $conn_data->query("SELECT id, name, label, input_type, required FROM service_fields WHERE service_id={$id} ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['service_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $fee = (float)($_POST['fee'] ?? 0);
    $profit = max(0, $price + $fee - $price);
    $status = isset($_POST['status']) ? 1 : 0;
    if ($id > 0) {
        if (supa_mode()) {
            supa_update('services', ['id' => $id], [
                'service_name' => $name,
                'description' => $desc,
                'price' => $price,
                'fee' => $fee,
                'merchant_profit' => $profit,
                'status' => (bool)$status
            ]);
            $old = supa_select('service_fields', ['service_id' => $id]);
            foreach ($old as $o) { supa_delete('service_fields', ['id' => $o['id']]); }
            if (!empty($_POST['field_name'])) {
                $rows = [];
                foreach ($_POST['field_name'] as $i => $fname) {
                    $flabel = $_POST['field_label'][$i] ?? '';
                    $ftype = $_POST['field_type'][$i] ?? 'text';
                    $freq = isset($_POST['field_required'][$i]) ? true : false;
                    if (trim($fname) !== '') {
                        $rows[] = ['service_id' => $id, 'name' => $fname, 'label' => $flabel, 'input_type' => $ftype, 'required' => $freq];
                    }
                }
                if ($rows) supa_insert('service_fields', $rows);
            }
        } else {
            $stmt = $conn_data->prepare("UPDATE services SET service_name=?, description=?, price=?, fee=?, merchant_profit=?, status=? WHERE id=?");
            $stmt->bind_param("ssdddii", $name, $desc, $price, $fee, $profit, $status, $id);
            $stmt->execute();
            $stmt->close();
            $conn_data->query("DELETE FROM service_fields WHERE service_id=".$id);
            if (!empty($_POST['field_name'])) {
                foreach ($_POST['field_name'] as $i => $fname) {
                    $flabel = $_POST['field_label'][$i] ?? '';
                    $ftype = $_POST['field_type'][$i] ?? 'text';
                    $freq = isset($_POST['field_required'][$i]) ? 1 : 0;
                    if (trim($fname) !== '') {
                        $stmt = $conn_data->prepare("INSERT INTO service_fields(service_id, name, label, input_type, required) VALUES (?,?,?,?,?)");
                        $stmt->bind_param("isssi", $id, $fname, $flabel, $ftype, $freq);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
    } else {
        if (supa_mode()) {
            $ins = supa_insert('services', [[
                'service_name' => $name,
                'description' => $desc,
                'price' => $price,
                'fee' => $fee,
                'merchant_profit' => $profit,
                'status' => (bool)$status
            ]]);
            $new_id = $ins[0]['id'] ?? null;
            if ($new_id && !empty($_POST['field_name'])) {
                $rows = [];
                foreach ($_POST['field_name'] as $i => $fname) {
                    $flabel = $_POST['field_label'][$i] ?? '';
                    $ftype = $_POST['field_type'][$i] ?? 'text';
                    $freq = isset($_POST['field_required'][$i]) ? true : false;
                    if (trim($fname) !== '') {
                        $rows[] = ['service_id' => $new_id, 'name' => $fname, 'label' => $flabel, 'input_type' => $ftype, 'required' => $freq];
                    }
                }
                if ($rows) supa_insert('service_fields', $rows);
            }
            header("Location: services.php");
            exit;
        } else {
            $stmt = $conn_data->prepare("INSERT INTO services(service_name, description, price, fee, merchant_profit, status) VALUES(?,?,?,?,?,?)");
            $stmt->bind_param("ssdddi", $name, $desc, $price, $fee, $profit, $status);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();
            if (!empty($_POST['field_name'])) {
                foreach ($_POST['field_name'] as $i => $fname) {
                    $flabel = $_POST['field_label'][$i] ?? '';
                    $ftype = $_POST['field_type'][$i] ?? 'text';
                    $freq = isset($_POST['field_required'][$i]) ? 1 : 0;
                    if (trim($fname) !== '') {
                        $stmt = $conn_data->prepare("INSERT INTO service_fields(service_id, name, label, input_type, required) VALUES (?,?,?,?,?)");
                        $stmt->bind_param("isssi", $new_id, $fname, $flabel, $ftype, $freq);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            header("Location: services.php");
            exit;
        }
    }
    header("Location: services.php");
    exit;
}
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700"><i class="fas fa-plus"></i> <?= $id>0?'تعديل خدمة':'إضافة خدمة جديدة' ?></div>
    <div>
      <a class="btn secondary" href="services.php"><i class="fas fa-arrow-right"></i> رجوع</a>
    </div>
  </div>
  <form method="post" style="margin-top:12px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
      <div>
        <label>اسم الخدمة *</label>
        <input type="text" name="service_name" required value="<?= htmlspecialchars($service['service_name'] ?? '') ?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px">
      </div>
      <div>
        <label>سعر الخدمة *</label>
        <input type="number" name="price" step="0.01" required value="<?= htmlspecialchars($service['price'] ?? '0.00') ?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px">
      </div>
      <div>
        <label>رسوم الخدمة</label>
        <input type="number" name="fee" step="0.01" value="<?= htmlspecialchars($service['fee'] ?? '0.00') ?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px">
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-top:24px">
        <input type="checkbox" name="status" id="status" <?= ($service && ($service['status']??0))?'checked':'' ?>>
        <label for="status">مفعلة</label>
      </div>
      <div style="grid-column:1/-1">
        <label>وصف الخدمة</label>
        <textarea name="description" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
      </div>
    </div>
    <hr style="margin:16px 0;border:none;border-top:1px solid #e5e7eb">
    <div>
      <div style="font-weight:700;margin-bottom:8px"><i class="fas fa-list"></i> حقول إدخال الخدمة</div>
      <div id="fields">
        <?php if ($fields): foreach($fields as $f): ?>
        <div class="field-row" style="display:grid;grid-template-columns:repeat(4,1fr) 100px;gap:8px;margin-bottom:8px">
          <input type="text" name="field_label[]" placeholder="اسم الحقل (العربي)" value="<?= htmlspecialchars($f['label'] ?? '') ?>" />
          <input type="text" name="field_name[]" placeholder="المفتاح (انجليزي)" value="<?= htmlspecialchars($f['name'] ?? '') ?>" />
          <select name="field_type[]">
            <option value="text" <?= ($f['input_type']??'text')==='text'?'selected':'' ?>>نص</option>
            <option value="number" <?= ($f['input_type']??'')==='number'?'selected':'' ?>>رقم</option>
            <option value="date" <?= ($f['input_type']??'')==='date'?'selected':'' ?>>تاريخ</option>
          </select>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="field_required[]" <?= ($f['required']??0)?'checked':'' ?>> إجباري
          </label>
          <button type="button" class="btn secondary" onclick="this.parentElement.remove()">✕</button>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <button type="button" class="btn" onclick="addField()">+ إضافة حقل</button>
    </div>
    <div style="margin-top:12px">
      <button class="btn"><i class="fas fa-save"></i> حفظ الخدمة</button>
    </div>
  </form>
</div>
<script>
function addField(){
  const row=document.createElement('div');
  row.className='field-row';
  row.style.cssText='display:grid;grid-template-columns:repeat(4,1fr) 100px;gap:8px;margin-bottom:8px';
  row.innerHTML=`
    <input type="text" name="field_label[]" placeholder="اسم الحقل (العربي)" />
    <input type="text" name="field_name[]" placeholder="المفتاح (انجليزي)" />
    <select name="field_type[]">
      <option value="text">نص</option>
      <option value="number">رقم</option>
      <option value="date">تاريخ</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px">
      <input type="checkbox" name="field_required[]"> إجباري
    </label>
    <button type="button" class="btn secondary" onclick="this.parentElement.remove()">✕</button>
  `;
  document.getElementById('fields').appendChild(row);
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
