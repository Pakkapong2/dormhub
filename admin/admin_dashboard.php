<?php
session_start();
require '../config/db_connect.php';

// 1. ตรวจสอบล็อกอินและสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ดึงข้อมูลชื่อแอดมิน
$stmt = $pdo->prepare("SELECT fullname FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_info = $stmt->fetch();

// 2. ดึงสถิติของ Admin (อิงตามโครงสร้างตารางที่คุณให้มา)
$stats = [
    'empty_rooms' => $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn(),
    'pending_bills' => $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
    'pending_repairs' => 0
];

// ดึงข้อมูลการแจ้งซ่อม (ใช้สถานะ pending และ in_progress จากตาราง maintenance)
try {
    $stmt_repair = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status IN ('pending', 'in_progress')");
    $stats['pending_repairs'] = $stmt_repair->fetchColumn();
} catch (Exception $e) {
    $stats['pending_repairs'] = 0;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include '../navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 uppercase tracking-tighter">
                    🛡️ Admin Panel
                </h1>
                <p class="text-slate-500 font-medium">
                    สวัสดีคุณ <?= htmlspecialchars($admin_info['fullname'] ?? $_SESSION['name']) ?> | จัดการระบบหอพักของคุณที่นี่
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้องว่าง</p>
                    <p class="text-4xl font-black text-blue-600"><?= $stats['empty_rooms'] ?></p>
                </div>
                <div class="bg-blue-50 w-16 h-16 rounded-2xl flex items-center justify-center text-blue-500 text-2xl">
                    <i class="fa-solid fa-door-open"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รอชำระเงิน/ตรวจสอบ</p>
                    <p class="text-4xl font-black text-orange-500"><?= $stats['pending_bills'] ?></p>
                </div>
                <div class="bg-orange-50 w-16 h-16 rounded-2xl flex items-center justify-center text-orange-500 text-2xl">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รายการแจ้งซ่อม</p>
                    <p class="text-4xl font-black text-rose-500"><?= $stats['pending_repairs'] ?></p>
                </div>
                <div class="bg-rose-50 w-16 h-16 rounded-2xl flex items-center justify-center text-rose-500 text-2xl">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
            </div>
        </div>

        <h2 class="text-sm font-black mb-6 text-slate-400 uppercase tracking-[0.2em]">Management Tools</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <a href="manage_rooms.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-bed text-2xl"></i>
                </div>
                <span class="block font-bold text-slate-700">จัดการห้องพัก</span>
            </a>

            <a href="manage_tenants.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-users text-2xl"></i>
                </div>
                <span class="block font-bold text-slate-700">จัดการผู้เช่า</span>
            </a>

            <a href="meter_records.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-yellow-500 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-bolt-lightning text-2xl"></i>
                </div>
                <span class="block font-bold text-slate-700">จดมิเตอร์</span>
            </a>

            <a href="manage_bills.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-receipt text-2xl"></i>
                </div>
                <span class="block font-bold text-slate-700">บิลและรายได้</span>
            </a>

            <a href="manage_maintenance.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-rose-500 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-screwdriver-wrench text-2xl"></i>
                </div>
                <span class="block font-bold text-slate-700">จัดการการแจ้งซ่อม</span>
            </a>
        </div>

    </main>

</body>
</html>