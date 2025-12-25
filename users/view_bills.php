<?php
session_start();
require_once '../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); 
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * 2. ดึงข้อมูลบิลล่าสุด
 * แก้ไขคอลัมน์ r.price เป็น r.base_rent ให้ตรงกับฐานข้อมูลจริงของคุณ
 */
$stmt = $pdo->prepare("
    SELECT p.*, m.*, r.room_number, r.base_rent as room_price
    FROM payments p
    LEFT JOIN meters m ON p.meter_id = m.meter_id
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN rooms r ON u.room_id = r.room_id
    WHERE p.user_id = ? AND p.status != 'paid'
    ORDER BY p.payment_id DESC LIMIT 1
");
$stmt->execute([$user_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงการตั้งค่าราคาหน่วย
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดค่าใช้จ่าย | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen pb-10">

    <div class="container mx-auto px-4 py-8 max-w-md">
        <a href="../index.php" class="inline-flex items-center mb-6 text-slate-500 hover:text-slate-800 transition-colors font-bold text-sm">
            <i class="fa-solid fa-chevron-left mr-2"></i> กลับหน้า Dashboard
        </a>

        <?php if ($bill): ?>
            <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-100">
                <div class="bg-slate-900 p-8 text-white relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Room Unit</p>
                                <h1 class="text-4xl font-black italic tracking-tighter">RM <?= htmlspecialchars($bill['room_number']) ?></h1>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Billing Month</p>
                                <p class="font-bold text-sm"><?= date('M Y', strtotime($bill['billing_month'])) ?></p>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-slate-800">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">ยอดรวมที่ต้องชำระ</p>
                            <h2 class="text-5xl font-black text-emerald-400 tracking-tighter">฿<?= number_format($bill['amount'], 2) ?></h2>
                        </div>
                    </div>
                    <i class="fa-solid fa-receipt absolute -right-4 -bottom-4 text-8xl text-white/5 -rotate-12"></i>
                </div>

                <div class="p-8 space-y-5">
                    <div class="flex justify-between items-center py-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500">
                                <i class="fa-solid fa-house-user"></i>
                            </div>
                            <span class="font-bold text-slate-700">ค่าเช่าห้องพัก</span>
                        </div>
                        <span class="font-black text-slate-900">฿<?= number_format($bill['room_price'], 2) ?></span>
                    </div>

                    <div class="bg-blue-50/50 p-5 rounded-[1.5rem] border border-blue-100 shadow-sm shadow-blue-50">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-droplet text-blue-500"></i>
                                <span class="text-xs font-black text-blue-600 uppercase tracking-widest italic">Water</span>
                            </div>
                            <span class="font-black text-blue-700">฿<?= number_format($bill['water_total'] ?? 0, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-[10px] text-blue-400 font-bold uppercase tracking-tight">
                            <span>มิเตอร์: <?= $bill['prev_water_meter'] ?> → <?= $bill['curr_water_meter'] ?></span>
                            <span>ใช้ไป <?= ($bill['curr_water_meter'] - $bill['prev_water_meter']) ?> หน่วย</span>
                        </div>
                    </div>

                    <div class="bg-orange-50/50 p-5 rounded-[1.5rem] border border-orange-100 shadow-sm shadow-orange-50">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-bolt text-orange-500"></i>
                                <span class="text-xs font-black text-orange-600 uppercase tracking-widest italic">Electric</span>
                            </div>
                            <span class="font-black text-orange-700">฿<?= number_format($bill['electric_total'] ?? 0, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-[10px] text-orange-400 font-bold uppercase tracking-tight">
                            <span>มิเตอร์: <?= $bill['prev_electric_meter'] ?> → <?= $bill['curr_electric_meter'] ?></span>
                            <span>ใช้ไป <?= ($bill['curr_electric_meter'] - $bill['prev_electric_meter']) ?> หน่วย</span>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-dashed border-slate-200">
                        <?php if ($bill['status'] == 'pending'): ?>
                            <button onclick="location.href='upload_slip.php?pay_id=<?= $bill['payment_id'] ?>'" 
                                class="w-full py-4 bg-emerald-500 text-white rounded-2xl font-black text-lg shadow-xl shadow-emerald-100 hover:bg-emerald-600 transition-all active:scale-95 uppercase tracking-tighter">
                                แจ้งชำระเงิน <i class="fa-solid fa-cloud-arrow-up ml-2"></i>
                            </button>
                        <?php else: ?>
                            <div class="bg-blue-50 p-4 rounded-2xl text-center border border-blue-100 shadow-inner">
                                <p class="text-blue-600 font-black text-sm uppercase italic">
                                    <i class="fa-solid fa-hourglass-half mr-2 animate-pulse"></i> รอตรวจสอบสลิป...
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-[2.5rem] p-12 text-center shadow-sm border border-slate-100 mt-10">
                <div class="bg-emerald-50 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-8 text-emerald-400 text-4xl">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-800 mb-2 italic">No Pending Bills</h3>
                <p class="text-slate-400 font-medium">คุณไม่มีมียอดค้างชำระในขณะนี้</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>