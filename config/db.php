<?php
/*********************************
 * عرض الأخطاء (للتطوير فقط)
 *********************************/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*********************************
 * إعدادات MySQLi
 *********************************/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*********************************
 * الاتصال بقاعدة البيانات
 *********************************/
if (!defined('SUPABASE_MODE')) {
    $env = getenv('SUPABASE_MODE');
    define('SUPABASE_MODE', $env === '1' || strtolower($env) === 'true');
}
if (SUPABASE_MODE) {
    require_once __DIR__ . '/supabase.php';
    $conn = null;
} else {
    $conn = new mysqli(
        "sql302.infinityfree.com",
        "if0_40974310",
        "1p7F4ABr5utA4",
        "if0_40974310_data"
    );
}

/*********************************
 * ترميز عربي صحيح
 *********************************/
if ($conn) { $conn->set_charset("utf8mb4"); }

/*********************************
 * ضبط التوقيت (مصر)
 *********************************/
if ($conn) { $conn->query("SET time_zone = '+02:00'"); }
?>
