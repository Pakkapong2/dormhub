<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in and has the 'user' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch meter records for the logged-in user
try {
    $stmt = $pdo->prepare("
        SELECT m.*, r.room_number
        FROM meters m
        JOIN rooms r ON m.room_id = r.room_id
        JOIN users u ON r.room_id = u.room_id
        WHERE u.user_id = ?
        ORDER BY m.billing_month DESC
    ");
    $stmt->execute([$user_id]);
    $meter_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching meter records: " . $e->getMessage());
}

$page_title = "ประวัติการใช้น้ำ-ไฟ";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen p-6 md:p-10 transition-all">
        <h1 class="text-3xl font-black text-slate-800 italic uppercase tracking-tighter mb-6 flex items-center gap-3">
            <i class="fa-solid fa-gauge-high text-blue-600"></i> <?= htmlspecialchars($page_title) ?>
        </h1>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">เดือน</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">น้ำ (หน่วย)</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">ค่าน้ำ</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">ไฟ (หน่วย)</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">ค่าไฟ</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">รวมทั้งหมด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (count($meter_records) > 0): ?>
                            <?php foreach ($meter_records as $record): ?>
                                <tr class="hover:bg-slate-50/80 transition-all">
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800"><?= date('F Y', strtotime($record['billing_month'])) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="text-blue-600 font-bold"><?= $record['curr_water_meter'] - $record['prev_water_meter'] ?> <span class="text-xs text-slate-400">หน่วย</span></p>
                                        <p class="text-xs text-slate-400">(<?= $record['prev_water_meter'] ?> → <?= $record['curr_water_meter'] ?>)</p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800">฿<?= number_format($record['water_total'], 2) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="text-orange-600 font-bold"><?= $record['curr_electric_meter'] - $record['prev_electric_meter'] ?> <span class="text-xs text-slate-400">หน่วย</span></p>
                                        <p class="text-xs text-slate-400">(<?= $record['prev_electric_meter'] ?> → <?= $record['curr_electric_meter'] ?>)</p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800">฿<?= number_format($record['electric_total'], 2) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-black text-emerald-600 text-lg">฿<?= number_format($record['total_amount'], 2) ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-8 py-10 text-center text-slate-500 font-medium">
                                    ยังไม่มีข้อมูลการจดมิเตอร์
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
