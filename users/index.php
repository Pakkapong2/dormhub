<?php
require_once __DIR__ . '/../config/app_config.php'; // Handles session_start()
require_once __DIR__ . '/../config/db_connect.php';

// error_reporting(0); // Removed for better error visibility during development

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// 1. ดึงข้อมูลล่าสุดเช็คสิทธิ์
$stmt = $pdo->prepare("SELECT role, user_id, fullname, username, room_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['role'] === 'admin') {
    header('Location: ' . BASE_URL . 'admin/manage_tenants.php');
    exit;
}

if (empty($user['room_id'])) {
    $_SESSION['role'] = 'viewer';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// 2. ดึงข้อมูลบิลและข้อมูลห้องพัก
$stmt = $pdo->prepare("
    SELECT u.*, r.room_number, r.base_rent as room_price,
    (SELECT SUM(amount) FROM payments WHERE user_id = u.user_id AND status = 'pending') as total_pending
    FROM users u
    LEFT JOIN rooms r ON u.room_id = r.room_id
    WHERE u.user_id = ?
");
$stmt->execute([$user['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. (Optional) ดึงรายการแจ้งซ่อมล่าสุด
$stmt = $pdo->prepare("SELECT status FROM maintenance WHERE user_id = ? ORDER BY reported_at DESC LIMIT 1");
$stmt->execute([$user['user_id']]);
$last_repair = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main class="container mx-auto px-4 md:px-6 py-12 max-w-4xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-4">
            <div>
                <h1 class="text-5xl font-black text-slate-800 italic uppercase tracking-tighter">Resident <span class="text-blue-600">Portal</span></h1>
                <p class="text-slate-500 font-medium mt-1 uppercase text-xs tracking-widest">ยินดีต้อนรับ, <?= htmlspecialchars($user_data['fullname']) ?></p>
            </div>
            <div class="flex items-center gap-4 bg-white p-2 pr-6 rounded-3xl border border-slate-100 shadow-sm">
                <div class="bg-slate-900 text-white w-14 h-14 rounded-2xl flex items-center justify-center font-black text-xl italic shadow-lg">
                    <?= htmlspecialchars($user_data['room_number']) ?>
                </div>
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Room Number</span>
                    <span class="text-slate-700 font-bold">ชั้น <?= substr($user_data['room_number'], 0, 1) ?></span>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-[3.5rem] p-8 md:p-12 text-white shadow-2xl relative overflow-hidden group mb-8">
             <div class="absolute top-[-30px] right-[-30px] opacity-10 text-[15rem] rotate-12 group-hover:rotate-0 transition-all duration-1000 pointer-events-none">💰</div>
             
             <div class="relative z-10">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                    <p class="text-[10px] uppercase tracking-[0.3em] font-black text-blue-400">Total Outstanding Balance</p>
                </div>
                
                <div class="flex items-baseline gap-3">
                    <span class="text-3xl font-black text-slate-500 italic">฿</span>
                    <h2 class="text-7xl md:text-8xl font-black italic tracking-tighter leading-none">
                        <?= number_format($user_data['total_pending'] ?? 0, 2) ?>
                    </h2>
                </div>

                <div class="mt-12 flex flex-wrap gap-4">
                    <a href="view_bills.php" class="bg-blue-600 hover:bg-blue-500 text-white px-10 py-5 rounded-[2rem] font-black text-sm uppercase italic tracking-widest transition-all shadow-xl shadow-blue-500/20 flex items-center gap-3">
                        Pay Bill <i class="fa-solid fa-arrow-right text-sm"></i>
                    </a>
                    <a href="maintenance.php" class="bg-white/5 hover:bg-white/10 backdrop-blur-md px-10 py-5 rounded-[2rem] font-black text-sm uppercase italic tracking-widest transition-all flex items-center gap-3 border border-white/10">
                        Repair <i class="fa-solid fa-wrench text-sm text-slate-400"></i>
                    </a>
                </div>
             </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fa-solid fa-bed text-xl"></i>
                </div>
                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Room Price</h4>
                <p class="text-2xl font-black text-slate-800 italic uppercase italic">฿<?= number_format($user_data['room_price']) ?><span class="text-xs text-slate-400 ml-1">/mo</span></p>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fa-solid fa-toolbox text-xl"></i>
                </div>
                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Last Request</h4>
                <p class="text-xl font-black text-slate-800 italic uppercase">
                    <?= $last_repair ? strtoupper($last_repair['status']) : 'NO ACTIVE' ?>
                </p>
            </div>

            <a href="payment_history.php" class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:border-blue-500 hover:shadow-md transition-all group">
                <div class="w-12 h-12 bg-slate-50 text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 rounded-2xl flex items-center justify-center mb-4 transition-all">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                </div>
                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Billing</h4>
                <p class="text-xl font-black text-slate-800 italic uppercase">View History</p>
            </a>
        </div>

        <div class="mt-12 bg-blue-50/50 border border-blue-100 rounded-[3rem] p-10">
            <div class="flex items-center gap-4 mb-6">
                <i class="fa-solid fa-bullhorn text-blue-500 text-2xl"></i>
                <h3 class="font-black italic uppercase tracking-tighter text-2xl text-slate-800">Announcements</h3>
            </div>
            <div class="space-y-4">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-blue-100">
                    <span class="text-[9px] font-black bg-blue-500 text-white px-3 py-1 rounded-full uppercase mb-2 inline-block">Update</span>
                    <p class="text-slate-700 font-bold">ปิดปรับปรุงระบบน้ำประปาในวันที่ 25 นี้ เวลา 10:00 - 12:00 น.</p>
                </div>
            </div>
        </div>

    </main>

</body>
</html>