<?php
session_start();
require '../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. ดึงค่าตั้งค่าจากฐานข้อมูล
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$water_rate = $config['water_rate'] ?? 0;
$electric_rate = $config['electric_rate'] ?? 0;

// (ส่วนประมวลผล POST เหมือนเดิมที่คุณส่งมา ซึ่งเขียนไว้ดีมากแล้วครับ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_meter'])) {
    $room_id = $_POST['room_id'];
    $prev_water = (int)$_POST['prev_water_meter'];
    $curr_water = (int)$_POST['curr_water_meter'];
    $prev_electric = (int)$_POST['prev_electric_meter'];
    $curr_electric = (int)$_POST['curr_electric_meter'];
    $billing_month = $_POST['billing_month'];

    // ตรวจสอบการจดซ้ำ
    $check_stmt = $pdo->prepare("SELECT meter_id FROM meters WHERE room_id = ? AND billing_month = ?");
    $check_stmt->execute([$room_id, $billing_month]);
    if ($check_stmt->fetch()) {
        header("Location: meter_records.php?msg=already_exists");
        exit();
    }

    if ($curr_water < $prev_water || $curr_electric < $prev_electric) {
        header("Location: meter_records.php?msg=error_value");
        exit();
    }

    $water_units = $curr_water - $prev_water;
    $electric_units = $curr_electric - $prev_electric;
    $water_total = $water_units * $water_rate;
    $electric_total = $electric_units * $electric_rate;
    $meter_total_sum = $water_total + $electric_total;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO meters (room_id, prev_water_meter, curr_water_meter, prev_electric_meter, curr_electric_meter, billing_month, water_total, electric_total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $prev_water, $curr_water, $prev_electric, $curr_electric, $billing_month, $water_total, $electric_total, $meter_total_sum]);
        $meter_id = $pdo->lastInsertId();

        $user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE room_id = ? AND role = 'user' LIMIT 1");
        $user_stmt->execute([$room_id]);
        $user = $user_stmt->fetch();

        if ($user) {
            $room_info = $pdo->prepare("SELECT base_rent FROM rooms WHERE room_id = ?");
            $room_info->execute([$room_id]);
            $base_rent = $room_info->fetchColumn();
            $final_amount = $base_rent + $meter_total_sum;

            $stmt_pay = $pdo->prepare("INSERT INTO payments (user_id, meter_id, amount, status) VALUES (?, ?, ?, 'pending')");
            $stmt_pay->execute([$user['user_id'], $meter_id, $final_amount]);
        }

        $pdo->commit();
        header("Location: meter_records.php?msg=success");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}

// 3. ดึงรายชื่อห้อง
$current_month = date('Y-m');
$rooms = $pdo->query("
    SELECT r.*, u.fullname, 
    (SELECT curr_water_meter FROM meters WHERE room_id = r.room_id ORDER BY meter_id DESC LIMIT 1) as last_water,
    (SELECT curr_electric_meter FROM meters WHERE room_id = r.room_id ORDER BY meter_id DESC LIMIT 1) as last_electric,
    (SELECT meter_id FROM meters WHERE room_id = r.room_id AND billing_month = '$current_month') as is_recorded
    FROM rooms r 
    JOIN users u ON r.room_id = u.room_id 
    WHERE r.status = 'occupied'
    ORDER BY r.room_number ASC
")->fetchAll();

// คำนวณความคืบหน้า
$total_rooms = count($rooms);
$recorded_count = 0;
foreach($rooms as $rm) if($rm['is_recorded']) $recorded_count++;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกมิเตอร์ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; background-color: #f8fafc; }
        .is-done { opacity: 0.6; pointer-events: none; transform: scale(0.98); transition: all 0.3s ease; }
        .is-done .btn-save { display: none; }
    </style>
</head>
<body class="pb-20">

    <?php include '../navbar.php'; ?>

    <div class="container mx-auto px-4 py-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
            <div>
                <a href="admin_dashboard.php" class="text-blue-600 font-bold text-sm hover:underline mb-2 inline-block">
                    <i class="fa-solid fa-arrow-left mr-1"></i> กลับหน้าแผงควบคุม
                </a>
                <h1 class="text-4xl font-black text-slate-800 tracking-tighter uppercase italic flex items-center gap-3">
                    <i class="fa-solid fa-gauge-high text-slate-900"></i> Meter Recording
                </h1>
                <p class="text-slate-500 font-medium">รอบประจำเดือน: <span class="text-blue-600 font-bold"><?= date('F Y') ?></span></p>
            </div>
            
            <div class="flex gap-4">
                <div class="bg-white p-4 rounded-3xl shadow-sm border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600 font-black">
                        <?= $recorded_count ?>/<?= $total_rooms ?>
                    </div>
                    <div class="pr-4">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Progress</p>
                        <p class="text-sm font-black text-slate-700">บันทึกแล้ว</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="roomContainer">
            <div class="lg:col-span-3 bg-slate-900 rounded-[2.5rem] p-8 text-white flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex gap-6">
                    <div class="flex flex-col">
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Water Rate</span>
                        <span class="text-2xl font-black">฿<?= number_format($water_rate, 2) ?></span>
                    </div>
                    <div class="flex flex-col border-l border-slate-700 pl-6">
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Electric Rate</span>
                        <span class="text-2xl font-black">฿<?= number_format($electric_rate, 2) ?></span>
                    </div>
                </div>
                <div class="w-full md:w-96 relative text-slate-900">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="roomSearch" onkeyup="searchRoom()" placeholder="ค้นหาเลขห้อง..." 
                        class="w-full pl-12 pr-4 py-4 bg-white border-none rounded-2xl focus:ring-4 focus:ring-blue-500/30 outline-none shadow-xl transition-all font-bold">
                </div>
            </div>

            <?php foreach($rooms as $r): ?>
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm flex flex-col room-item <?= $r['is_recorded'] ? 'is-done' : '' ?>" data-room="<?= $r['room_number'] ?>">
                <div class="p-8 pb-4 flex justify-between items-start">
                    <div>
                        <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest bg-blue-50 px-2 py-1 rounded-lg">Occupied</span>
                        <h3 class="text-4xl font-black text-slate-800 italic mt-2">RM <?= $r['room_number'] ?></h3>
                    </div>
                    <div class="text-right">
                        <?php if($r['is_recorded']): ?>
                            <div class="bg-emerald-500 text-white p-2 rounded-xl shadow-lg shadow-emerald-100">
                                <i class="fa-solid fa-check-double"></i>
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tenant</p>
                            <p class="font-bold text-slate-600"><?= htmlspecialchars($r['fullname']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" class="p-8 pt-4 space-y-6">
                    <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                    <input type="hidden" name="save_meter" value="1">
                    <input type="hidden" name="billing_month" value="<?= $current_month ?>">

                    <div class="bg-blue-50/40 p-5 rounded-[2rem] border border-blue-100">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-black text-blue-600 uppercase tracking-wider"><i class="fa-solid fa-droplet mr-1"></i> Water</span>
                            <p class="text-[10px] font-bold text-blue-400 total-display italic">฿0.00</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 uppercase">Previous</label>
                                <input type="number" name="prev_water_meter" value="<?= $r['last_water'] ?? 0 ?>" readonly class="w-full bg-transparent font-black text-slate-400 outline-none text-xl">
                            </div>
                            <div class="text-right">
                                <label class="text-[9px] font-bold text-blue-400 uppercase">Current</label>
                                <input type="number" name="curr_water_meter" required 
                                    oninput="calc(this, <?= $r['last_water'] ?? 0 ?>, <?= $water_rate ?>)"
                                    class="w-full bg-white border-2 border-blue-200 rounded-2xl px-3 py-2 text-right font-black text-blue-600 outline-none focus:border-blue-500 text-xl shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="bg-orange-50/40 p-5 rounded-[2rem] border border-orange-100">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-black text-orange-600 uppercase tracking-wider"><i class="fa-solid fa-bolt mr-1"></i> Electric</span>
                            <p class="text-[10px] font-bold text-orange-400 total-display italic">฿0.00</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 uppercase">Previous</label>
                                <input type="number" name="prev_electric_meter" value="<?= $r['last_electric'] ?? 0 ?>" readonly class="w-full bg-transparent font-black text-slate-400 outline-none text-xl">
                            </div>
                            <div class="text-right">
                                <label class="text-[9px] font-bold text-orange-400 uppercase">Current</label>
                                <input type="number" name="curr_electric_meter" required 
                                    oninput="calc(this, <?= $r['last_electric'] ?? 0 ?>, <?= $electric_rate ?>)"
                                    class="w-full bg-white border-2 border-orange-200 rounded-2xl px-3 py-2 text-right font-black text-orange-600 outline-none focus:border-orange-500 text-xl shadow-sm">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save w-full py-5 bg-slate-900 text-white rounded-[2rem] font-black shadow-xl shadow-slate-200 hover:bg-blue-600 hover:-translate-y-1 transition-all uppercase tracking-widest text-sm">
                        Confirm & Generate Bill
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // (JavaScript ส่วนเดิม แต่ปรับความสวยงามเล็กน้อย)
        function calc(input, prev, rate) {
            const current = parseInt(input.value) || 0;
            const container = input.closest('div[class*="rounded-[2rem]"]');
            const display = container.querySelector('.total-display');
            
            if (current > 0 && current < prev) {
                input.classList.add('border-rose-500');
                display.innerText = "Error: Invalid Value";
                display.classList.add('text-rose-500');
            } else {
                input.classList.remove('border-rose-500');
                const total = Math.max(0, current - prev) * rate;
                display.innerText = "฿" + total.toLocaleString(undefined, {minimumFractionDigits: 2});
                display.classList.remove('text-rose-500');
            }
        }

        function searchRoom() {
            const input = document.getElementById('roomSearch').value.toLowerCase();
            const items = document.getElementsByClassName('room-item');
            Array.from(items).forEach(item => {
                const roomNum = item.getAttribute('data-room').toLowerCase();
                item.style.display = roomNum.includes(input) ? 'flex' : 'none';
            });
        }

        // SweetAlert 2 Configuration
        const urlParams = new URLSearchParams(window.location.search);
        const swalConfig = { confirmButtonColor: '#0f172a', borderRadius: '2rem' };
        
        if (urlParams.get('msg') === 'success') Swal.fire({ ...swalConfig, icon: 'success', title: 'บันทึกสำเร็จ', text: 'ระบบสร้างบิลแจ้งหนี้เรียบร้อยแล้ว' });
        if (urlParams.get('msg') === 'already_exists') Swal.fire({ ...swalConfig, icon: 'warning', title: 'ดำเนินการแล้ว', text: 'ห้องนี้มีการบันทึกมิเตอร์ของเดือนนี้ไปแล้ว' });
        if (urlParams.get('msg') === 'error_value') Swal.fire({ ...swalConfig, icon: 'error', title: 'ข้อมูลผิดพลาด', text: 'เลขมิเตอร์ใหม่ต้องไม่น้อยกว่าเลขเดิม' });
    </script>
</body>
</html>