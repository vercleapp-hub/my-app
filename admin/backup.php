<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db_util.php';
$tables = ['users','permission_modules','site_permissions','permission_logs','login_sessions','login_attempts','otp_codes','device_logs','services','service_fields','clients','operations','transactions','deposits','deposit_methods','notifications','agent_commissions'];
$dir = __DIR__ . '/../uploads/backups/';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$ts = date('Ymd_His');
$files = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($tables as $t) {
        $rows = supa_mode() ? supa_select($t, [], '*', null, null) : [];
        $path = $dir . $t . '_' . $ts . '.json';
        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $files[] = $path;
    }
    $zipPath = $dir . 'backup_' . $ts . '.zip';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($files as $f) $zip->addFile($f, basename($f));
            $zip->close();
        }
    }
}
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700"><i class="fas fa-database"></i> النسخ الاحتياطي</div>
    <div><a class="btn secondary" href="index.php">الرئيسية</a></div>
  </div>
  <p class="muted">ينشئ نسخة احتياطية من جداول Supabase على المجلد المحلي uploads/backups. يُفضل جدولة مهمة أسبوعية عبر Vercel Cron Jobs.</p>
  <form method="post">
    <button class="btn"><i class="fas fa-download"></i> إنشاء نسخة الآن</button>
  </form>
  <?php if (!empty($zipPath) && file_exists($zipPath)): ?>
  <div style="margin-top:10px">
    <a class="btn" href="../uploads/backups/<?= htmlspecialchars(basename($zipPath)) ?>">تحميل النسخة: <?= htmlspecialchars(basename($zipPath)) ?></a>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
