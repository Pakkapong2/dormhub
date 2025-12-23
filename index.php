<?php
session_start();
require 'config/db_connect.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.line_user_id = ? OR u.username = ?
");
$stmt->execute([$_SESSION['user'], $_SESSION['user']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $user['role'];

$stats = [];
if ($role === 'admin') {
    $stats['empty_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
    $stats['pending_bills'] = $pdo->query("SELECT COUNT(*) FROM meters WHERE status = 'unpaid'")->fetchColumn();
    $stats['pending_repairs'] = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status = 'pending'")->fetchColumn();
} else {
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
    <title>Dashboard | ระบบจัดการหอพัก</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <?php include 'navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">ยินดีต้อนรับ, <?= htmlspecialchars($user['fullname']) ?></h1>
            <p class="text-slate-500">
                <?= $role === 'admin' ? 'ระดับผู้ดูแลระบบ' : 'เลขห้องของคุณ: ' . ($user['room_number'] ?? 'ยังไม่ได้ระบุห้อง') ?>
            </p>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">ห้องว่างพร้อมจอง</p>
                        <p class="text-2xl font-bold"><?= $stats['empty_rooms'] ?> ห้อง</p>
                    </div>
                    <i class="fa-solid fa-door-open text-3xl text-blue-100"></i>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-orange-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">บิลที่รอการชำระ</p>
                        <p class="text-2xl font-bold"><?= $stats['pending_bills'] ?> รายการ</p>
                    </div>
                    <i class="fa-solid fa-file-invoice-dollar text-3xl text-orange-100"></i>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-red-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">แจ้งซ่อมค้างคา</p>
                        <p class="text-2xl font-bold text-red-600"><?= $stats['pending_repairs'] ?> รายการ</p>
                    </div>
                    <i class="fa-solid fa-tools text-3xl text-red-100"></i>
                </div>
            </div>

            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> เมนูการจัดการ
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="admin/manage_rooms.php" class="group bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center border border-slate-100">
                    <div class="bg-blue-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                        <i class="fa-solid fa-bed text-blue-600"></i>
                    </div>
                    <span class="font-semibold block">จัดการห้องพัก</span>
                </a>
                <a href="admin/manage_users.php" class="group bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center border border-slate-100">
                    <div class="bg-green-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                        <i class="fa-solid fa-users text-green-600"></i>
                    </div>
                    <span class="font-semibold block">จัดการผู้เช่า</span>
                </a>
                <a href="admin/meter_records.php" class="group bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center border border-slate-100">
                    <div class="bg-cyan-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                        <i class="fa-solid fa-bolt text-cyan-600"></i>
                    </div>
                    <span class="font-semibold block">บันทึกค่าน้ำไฟ</span>
                </a>
                <a href="admin/view_bills.php" class="group bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center border border-slate-100">
                    <div class="bg-purple-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                        <i class="fa-solid fa-receipt text-purple-600"></i>
                    </div>
                    <span class="font-semibold block">ดูบิลทั้งหมด</span>
                </a>
            </div>

        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 bg-gradient-to-br from-green-500 to-emerald-600 rounded-3xl p-8 text-white shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="opacity-80 mb-1">ยอดค้างชำระปัจจุบัน</p>
                        <h3 class="text-4xl font-bold mb-6">
                            <?= number_format($stats['my_pending_bill'] ?? 0, 2) ?> บาท
                        </h3>
                        <a href="users/view_bills.php" class="bg-white text-green-600 px-6 py-2 rounded-full font-bold hover:bg-opacity-90 transition">
                            ชำระเงินตอนนี้
                        </a>
                    </div>
                    <i class="fa-solid fa-wallet text-9xl absolute -right-5 -bottom-5 opacity-10"></i>
                </div>

                <div class="flex flex-col gap-4">
                    <a href="users/payment_history.php" class="flex items-center gap-4 bg-white p-4 rounded-2xl shadow-sm hover:bg-slate-50 transition border border-slate-100">
                        <div class="bg-blue-50 w-12 h-12 rounded-xl flex items-center justify-center text-blue-600 text-xl">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <p class="font-bold">ประวัติจ่ายเงิน</p>
                            <p class="text-xs text-slate-500">ดูรายการที่ชำระแล้ว</p>
                        </div>
                    </a>
                    <a href="report_issue.php" class="flex items-center gap-4 bg-white p-4 rounded-2xl shadow-sm hover:bg-slate-50 transition border border-slate-100">
                        <div class="bg-red-50 w-12 h-12 rounded-xl flex items-center justify-center text-red-600 text-xl">
                            <i class="fa-solid fa-comment-dots"></i>
                        </div>
                        <div>
                            <p class="font-bold">แจ้งปัญหา</p>
                            <p class="text-xs text-slate-500">แจ้งซ่อมหรือติดต่อหอ</p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <footer class="text-center py-10 text-slate-400 text-sm">
        &copy; <?= date('Y') ?> ระบบจัดการหอพักอัจฉริยะ. All rights reserved.
    </footer>

</body>
</html>