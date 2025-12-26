<?php
session_start();
require 'config/db_connect.php';

// 1. ตรวจสอบล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. ดึงข้อมูลผู้ใช้ (อิงตามฐานข้อมูลเดิม)
$stmt = $pdo->prepare("SELECT role, user_id, fullname, username, room_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 3. GATEKEEPER: ถ้าเป็น Admin ให้ไปหน้า Admin Dashboard
if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// --- โค้ดส่วนดึงข้อมูล User เดิมของคุณ ---
$stmt = $pdo->prepare("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.user_id = ?
");
$stmt->execute([$user['user_id']]);
$user_full = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงยอดค้างชำระ (อิงตามตาราง payments)
$stmt = $pdo->prepare("SELECT amount FROM payments WHERE user_id = ? AND status = 'pending' ORDER BY payment_id DESC LIMIT 1");
$stmt->execute([$user['user_id']]);
$pending_bill = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="th">
<head> </head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'navbar.php'; ?>
    <main class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold">Hi, <?= htmlspecialchars($user_full['fullname'] ?? $user_full['username']) ?> 👋</h1>
        <p class="text-slate-500">🏠 Room: <?= $user_full['room_number'] ?? 'Not Assigned' ?></p>
        
        <div class="bg-slate-900 rounded-[3rem] p-10 text-white mt-8">
             <p class="text-xs uppercase tracking-widest mb-2">ยอดค้างชำระปัจจุบัน</p>
             <h2 class="text-6xl font-black italic">฿ <?= number_format($pending_bill ?? 0, 2) ?></h2>
             <a href="users/view_bills.php" class="bg-blue-600 px-10 py-4 rounded-2xl mt-6 inline-block">Pay Now</a>
        </div>
    </main>
</body>
</html>