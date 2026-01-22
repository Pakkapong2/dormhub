<?php
session_start();
require '../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. ดึงสถิติพื้นฐาน
$stats = [
    'empty_rooms' => $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn(),
    'occupied_rooms' => $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn(),
    'pending_bills' => $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn()
];

// 3. ดึงข้อมูลกราฟรายได้ (สรุป 6 เดือนย้อนหลัง)
// หมายเหตุ: ปรับชื่อตารางและ Column ตามจริง (ตัวอย่างอิงจากตาราง payments)
$monthly_income = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%M') as month, SUM(amount) as total 
    FROM payments 
    WHERE status = 'approved' 
    GROUP BY MONTH(payment_date) 
    ORDER BY payment_date ASC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$months = json_encode(array_column($monthly_income, 'month'));
$amounts = json_encode(array_column($monthly_income, 'total'));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | DORMHUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">

    <?php include '../sidebar.php'; ?>

    <main class="lg:ml-72 p-4 md:p-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 tracking-tighter uppercase">Overview Dashboard</h1>
            <p class="text-slate-500">สรุปภาพรวมและสถิติของหอพัก</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ห้องว่าง</p>
                <p class="text-3xl font-black text-green-600"><?= $stats['empty_rooms'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ห้องที่มีคนเช่า</p>
                <p class="text-3xl font-black text-blue-600"><?= $stats['occupied_rooms'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">บิลรอตรวจสอบ</p>
                <p class="text-3xl font-black text-orange-500"><?= $stats['pending_bills'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ผู้เช่าทั้งหมด</p>
                <p class="text-3xl font-black text-slate-800"><?= $stats['total_users'] ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-blue-500"></i> สรุปรายได้รายเดือน (บาท)
                </h3>
                <canvas id="incomeChart" height="200"></canvas>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-green-500"></i> สัดส่วนการใช้ห้องพัก
                </h3>
                <div class="max-w-[250px] mx-auto">
                    <canvas id="roomStatusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="manage_rooms.php" class="bg-blue-600 text-white p-6 rounded-[2rem] hover:bg-blue-700 transition flex items-center justify-center gap-3 font-bold shadow-lg shadow-blue-200">
                <i class="fa-solid fa-door-open"></i> จัดการห้อง
            </a>
            <a href="manage_bills.php" class="bg-white text-slate-700 p-6 rounded-[2rem] border border-slate-200 hover:bg-slate-50 transition flex items-center justify-center gap-3 font-bold shadow-sm">
                <i class="fa-solid fa-receipt text-purple-500"></i> บิลทั้งหมด
            </a>
            </div>

    </main>

    <script>
        // --- กราฟรายได้ (Line Chart) ---
        const ctxIncome = document.getElementById('incomeChart').getContext('2d');
        new Chart(ctxIncome, {
            type: 'line',
            data: {
                labels: <?= $months ?>,
                datasets: [{
                    label: 'รายได้',
                    data: <?= $amounts ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 4,
                    pointRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
            }
        });

        // --- กราฟสถานะห้อง (Doughnut Chart) ---
        const ctxRoom = document.getElementById('roomStatusChart').getContext('2d');
        new Chart(ctxRoom, {
            type: 'doughnut',
            data: {
                labels: ['ห้องว่าง', 'มีคนเช่า'],
                datasets: [{
                    data: [<?= $stats['empty_rooms'] ?>, <?= $stats['occupied_rooms'] ?>],
                    backgroundColor: ['#10b981', '#3b82f6'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                cutout: '70%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } }
            }
        });
    </script>
</body>
</html>