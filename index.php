<?php
session_start();
require 'config/db_connect.php';

// ตรวจสอบว่าล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ดึงข้อมูลผู้ใช้จาก user_id ที่อยู่ใน Session
$stmt = $pdo->prepare("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $user['role'];

// ดึงสถิติต่างๆ
$stats = [];
if ($role === 'admin') {
    // สถิติสำหรับ Admin
    $stats['empty_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
    $stats['pending_bills'] = $pdo->query("SELECT COUNT(*) FROM meters WHERE status = 'unpaid'")->fetchColumn();
    
    // เช็คก่อนว่ามี table maintenance ไหม ถ้าไม่มีให้ใส่ 0 ไปก่อนป้องกัน Error
    try {
        $stats['pending_repairs'] = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        $stats['pending_repairs'] = 0;
    }
} else {
    // ยอดค้างชำระสำหรับผู้เช่า
    $stmt = $pdo->prepare("SELECT total_amount FROM meters WHERE room_id = ? AND status = 'unpaid' ORDER BY meter_id DESC LIMIT 1");
    $stmt->execute([$user['room_id']]);
    $stats['my_pending_bill'] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50">

    <?php  include 'navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">สวัสดี, <?= htmlspecialchars($user['fullname']) ?> 👋</h1>
                <p class="text-slate-500">
                    <?= $role === 'admin' ? '🛡️ ผู้ดูแลระบบ' : '🏠 เลขห้อง: ' . ($user['room_number'] ?? 'ยังไม่ได้ระบุห้อง') ?>
                </p>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-400">ห้องว่าง</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['empty_rooms'] ?></p>
                    </div>
                    <div class="bg-blue-50 w-14 h-14 rounded-2xl flex items-center justify-center text-blue-500 text-2xl">
                        <i class="fa-solid fa-door-open"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-400">บิลค้างจ่าย</p>
                        <p class="text-3xl font-bold text-orange-500"><?= $stats['pending_bills'] ?></p>
                    </div>
                    <div class="bg-orange-50 w-14 h-14 rounded-2xl flex items-center justify-center text-orange-500 text-2xl">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-400">รายการแจ้งซ่อม</p>
                        <p class="text-3xl font-bold text-red-500"><?= $stats['pending_repairs'] ?></p>
                    </div>
                    <div class="bg-red-50 w-14 h-14 rounded-2xl flex items-center justify-center text-red-500 text-2xl">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                </div>
            </div>

            <h2 class="text-xl font-bold mb-6 text-slate-800">เมนูจัดการระบบ</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="admin/manage_rooms.php" class="bg-white p-6 rounded-3xl shadow-sm hover:shadow-md transition border border-slate-100 text-center">
                    <i class="fa-solid fa-bed text-3xl text-blue-500 mb-3"></i>
                    <span class="block font-semibold">จัดการห้องพัก</span>
                </a>
                <a href="admin/manage_tenants.php" class="bg-white p-6 rounded-3xl shadow-sm hover:shadow-md transition border border-slate-100 text-center">
                    <i class="fa-solid fa-users text-3xl text-green-500 mb-3"></i>
                    <span class="block font-semibold">จัดการผู้เช่า</span>
                </a>
                <a href="admin/meter_records.php" class="bg-white p-6 rounded-3xl shadow-sm hover:shadow-md transition border border-slate-100 text-center">
                    <i class="fa-solid fa-bolt-lightning text-3xl text-yellow-500 mb-3"></i>
                    <span class="block font-semibold">จดมิเตอร์</span>
                </a>
                <a href="admin/view_bills.php" class="bg-white p-6 rounded-3xl shadow-sm hover:shadow-md transition border border-slate-100 text-center">
                    <i class="fa-solid fa-receipt text-3xl text-purple-500 mb-3"></i>
                    <span class="block font-semibold">บิลทั้งหมด</span>
                </a>
            </div>

        <?php else: ?>
            <div class="max-w-4xl">
                <div class="bg-gradient-to-br from-green-600 to-emerald-700 rounded-[2.5rem] p-10 text-white shadow-xl mb-8 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-green-100 mb-2">ยอดที่ต้องชำระในเดือนนี้</p>
                        <h2 class="text-5xl font-black mb-8">฿ <?= number_format($stats['my_pending_bill'] ?? 0, 2) ?></h2>
                        <a href="users/view_bills.php" class="bg-white text-green-700 px-8 py-3 rounded-2xl font-bold hover:bg-green-50 transition shadow-lg inline-block">
                            ดูรายละเอียดและชำระเงิน
                        </a>
                    </div>
                    <i class="fa-solid fa-wallet text-[12rem] absolute -right-10 -bottom-10 opacity-10"></i>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="users/payment_history.php" class="flex items-center gap-5 bg-white p-6 rounded-3xl shadow-sm hover:bg-slate-50 transition border border-slate-100">
                        <div class="bg-blue-100 w-14 h-14 rounded-2xl flex items-center justify-center text-blue-600 text-2xl">
                            <i class="fa-solid fa-history"></i>
                        </div>
                        <div>
                            <p class="font-bold text-lg">ประวัติการชำระ</p>
                            <p class="text-sm text-slate-500">ตรวจสอบรายการย้อนหลัง</p>
                        </div>
                    </a>
                    <a href="report_issue.php" class="flex items-center gap-5 bg-white p-6 rounded-3xl shadow-sm hover:bg-slate-50 transition border border-slate-100">
                        <div class="bg-red-100 w-14 h-14 rounded-2xl flex items-center justify-center text-red-600 text-2xl">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <p class="font-bold text-lg">แจ้งปัญหา / แจ้งซ่อม</p>
                            <p class="text-sm text-slate-500">ส่งเรื่องถึงผู้ดูแลหอพัก</p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>