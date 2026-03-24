<?php
session_start();
require_once __DIR__ . '/../config/login.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, username, email, role, last_login FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user || !in_array($user['role'], ['admin'])) {
    header("Location: ../index.php");
    exit;
}
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة الإدارة - Dr Pay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;font-family:'Segoe UI',Tahoma,Arial}
body{margin:0;background:#f5f7fb;color:#1f2937}
.nav{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.nav .brand{display:flex;align-items:center;gap:8px;font-weight:700}
.nav .links{display:flex;gap:8px;flex-wrap:wrap}
.nav a{color:#fff;text-decoration:none;padding:8px 12px;border-radius:6px;background:rgba(255,255,255,.12)}
.nav a:hover{background:rgba(255,255,255,.2)}
.container{max-width:1280px;margin:18px auto;padding:0 12px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;box-shadow:0 6px 18px rgba(0,0,0,.05)}
.card .num{font-size:22px;font-weight:800;color:#111827}
.card .label{color:#6b7280;font-size:13px;margin-top:4px}
.table{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb}
thead th{background:#f8fafc;text-align:right}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
.btn{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer}
.btn.secondary{background:#6b7280}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.ok{background:#e8f5e9;color:#2e7d32}
.fail{background:#ffebee;color:#c62828}
.muted{color:#6b7280}
.footer{margin-top:20px;text-align:center;color:#6b7280}
</style>
</head>
<body>
<div class="nav">
  <div class="brand"><i class="fas fa-shield-halved"></i> لوحة الإدارة</div>
  <div class="links">
    <a href="index.php"><i class="fas fa-gauge"></i> الرئيسية</a>
    <a href="users.php"><i class="fas fa-users"></i> المستخدمون</a>
    <a href="operations.php"><i class="fas fa-list"></i> العمليات</a>
    <a href="services.php"><i class="fas fa-screwdriver-wrench"></i> الخدمات</a>
    <a href="logs.php"><i class="fas fa-list-check"></i> سجلات الدخول</a>
    <a href="map.php"><i class="fas fa-map-location-dot"></i> الخريطة</a>
    <a href="backup.php"><i class="fas fa-database"></i> النسخ الاحتياطي</a>
    <a href="../index.php"><i class="fas fa-home"></i> النظام</a>
    <a href="../logout.php"><i class="fas fa-right-from-brush"></i> خروج</a>
  </div>
</div>
<div class="container">
