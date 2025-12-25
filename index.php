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
    // 1. นับห้องว่าง
    $stats['empty_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
    
    // 2. [FIXED] นับบิลที่ยังไม่ได้จ่ายจากตาราง payments
    $stats['pending_bills'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    
    // 3. รายการแจ้งซ่อม (เช็คเผื่อไม่มีตาราง)
    try {
        $stats['pending_repairs'] = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        $stats['pending_repairs'] = 0;
    }
} else {
    // [FIXED] ดึงยอดค้างชำระล่าสุดของผู้เช่าจากตาราง payments
    $stmt = $pdo->prepare("
        SELECT amount 
        FROM payments 
        WHERE user_id = ? AND status = 'pending' 
        ORDER BY payment_id DESC LIMIT 1
    ");
    $stmt->execute([$user['user_id']]);
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
<body class="bg-slate-50 min-h-screen">

    <?php include 'navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 uppercase tracking-tighter">
                    Hi, <?= htmlspecialchars($user['fullname'] ?? $user['username']) ?> 👋
                </h1>
                <p class="text-slate-500 font-medium">
                    <?= $role === 'admin' ? '🛡️ Administrator Access' : '🏠 Room: ' . ($user['room_number'] ?? 'Not Assigned') ?>
                </p>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
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
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รอชำระเงิน</p>
                        <p class="text-4xl font-black text-orange-500"><?= $stats['pending_bills'] ?></p>
                    </div>
                    <div class="bg-orange-50 w-16 h-16 rounded-2xl flex items-center justify-center text-orange-500 text-2xl">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">แจ้งซ่อม</p>
                        <p class="text-4xl font-black text-rose-500"><?= $stats['pending_repairs'] ?></p>
                    </div>
                    <div class="bg-rose-50 w-16 h-16 rounded-2xl flex items-center justify-center text-rose-500 text-2xl">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                </div>
            </div>

            <h2 class="text-sm font-black mb-6 text-slate-400 uppercase tracking-[0.2em]">Management Tools</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="admin/manage_rooms.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                    <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-bed text-2xl"></i>
                    </div>
                    <span class="block font-bold text-slate-700">จัดการห้องพัก</span>
                </a>
                <a href="admin/manage_tenants.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                    <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-users text-2xl"></i>
                    </div>
                    <span class="block font-bold text-slate-700">จัดการผู้เช่า</span>
                </a>
                <a href="admin/meter_records.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                    <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-yellow-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-bolt-lightning text-2xl"></i>
                    </div>
                    <span class="block font-bold text-slate-700">จดมิเตอร์</span>
                </a>
                <a href="admin/manage_bills.php" class="bg-white p-8 rounded-[2rem] shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all border border-slate-100 text-center group">
                    <div class="bg-slate-50 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-receipt text-2xl"></i>
                    </div>
                    <span class="block font-bold text-slate-700">บิลและรายได้</span>
                </a>
            </div>

        <?php else: ?>
            <div class="max-w-4xl">
                <div class="bg-slate-900 rounded-[3rem] p-10 text-white shadow-2xl mb-8 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-slate-400 font-bold uppercase tracking-widest text-xs mb-2">ยอดค้างชำระปัจจุบัน</p>
                        <h2 class="text-6xl font-black mb-10 italic">฿ <?= number_format($stats['my_pending_bill'] ?? 0, 2) ?></h2>
                        <a href="users/view_bills.php" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black hover:bg-blue-700 transition shadow-lg inline-block uppercase tracking-tighter">
                            Pay Now <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <i class="fa-solid fa-wallet text-[15rem] absolute -right-10 -bottom-10 opacity-5 rotate-12"></i>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <a href="users/payment_history.php" class="flex items-center gap-6 bg-white p-8 rounded-[2.5rem] shadow-sm hover:shadow-md transition border border-slate-100 group">
                        <div class="bg-slate-100 w-16 h-16 rounded-2xl flex items-center justify-center text-slate-600 text-2xl group-hover:bg-blue-600 group-hover:text-white transition-all">
                            <i class="fa-solid fa-history"></i>
                        </div>
                        <div>
                            <p class="font-black text-xl text-slate-800">ประวัติการชำระ</p>
                            <p class="text-sm text-slate-400">History & Receipts</p>
                        </div>
                    </a>
                    <a href="report_issue.php" class="flex items-center gap-6 bg-white p-8 rounded-[2.5rem] shadow-sm hover:shadow-md transition border border-slate-100 group">
                        <div class="bg-slate-100 w-16 h-16 rounded-2xl flex items-center justify-center text-slate-600 text-2xl group-hover:bg-rose-600 group-hover:text-white transition-all">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <p class="font-black text-xl text-slate-800">แจ้งปัญหา / ซ่อม</p>
                            <p class="text-sm text-slate-400">Support Ticket</p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>