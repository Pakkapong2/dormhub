<?php
session_start();
// ใช้ __DIR__ เพื่ออ้างอิงตำแหน่งไฟล์ที่แน่นอน
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, user_id, fullname, username, room_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] === 'admin') {
    header('Location: ' . ($user['role'] === 'admin' ? '../admin/admin_dashboard.php' : '../login.php'));
    exit;
}

// ดึงข้อมูลห้องพักและบิล
$stmt = $pdo->prepare("
    SELECT u.*, r.room_number, 
    (SELECT amount FROM payments WHERE user_id = u.user_id AND status = 'pending' ORDER BY payment_id DESC LIMIT 1) as pending_bill
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.user_id = ?
");
$stmt->execute([$user['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DormHub - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-slate-800">Hi, <?= htmlspecialchars($user_data['fullname'] ?? $user_data['username']) ?> 👋</h1>
        <p class="text-slate-500 mt-1">🏠 Room: <span class="font-bold text-slate-700"><?= $user_data['room_number'] ?? 'Not Assigned' ?></span></p>
        
        <div class="bg-slate-900 rounded-[3rem] p-10 text-white mt-8 shadow-2xl relative overflow-hidden">
             <div class="absolute top-[-20px] right-[-20px] opacity-10 text-[12rem] rotate-12">💰</div>
             <p class="text-xs uppercase tracking-[0.3em] mb-2 font-bold text-green-400">ยอดค้างชำระปัจจุบัน</p>
             <h2 class="text-6xl font-black italic">฿ <?= number_format($user_data['pending_bill'] ?? 0, 2) ?></h2>
             <a href="view_bills.php" class="bg-blue-600 hover:bg-blue-700 px-10 py-4 rounded-2xl mt-8 inline-block font-bold transition-all shadow-lg shadow-blue-500/40">
                Pay Now <i class="fa-solid fa-arrow-right ml-2"></i>
             </a>
        </div>
    </main>
</body>
</html>