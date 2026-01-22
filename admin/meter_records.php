<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. ดึงค่าตั้งค่าจากฐานข้อมูล
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$water_rate = $config['water_rate'] ?? 0;
$electric_rate = $config['electric_rate'] ?? 0;

// 3. ประมวลผลเมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_meter'])) {
    $room_id = $_POST['room_id'];
    $prev_water = (int)$_POST['prev_water_meter'];
    $curr_water = (int)$_POST['curr_water_meter'];
    $prev_electric = (int)$_POST['prev_electric_meter'];
    $curr_electric = (int)$_POST['curr_electric_meter'];
    $billing_month = $_POST['billing_month'];

    // ตรวจสอบการจดซ้ำในเดือนเดียวกัน
    $check_stmt = $pdo->prepare("SELECT meter_id FROM meters WHERE room_id = ? AND billing_month = ?");
    $check_stmt->execute([$room_id, $billing_month]);
    if ($check_stmt->fetch()) {
        header("Location: meter_records.php?msg=already_exists");
        exit();
    }

    // ตรวจสอบความถูกต้องของตัวเลข
    if ($curr_water < $prev_water || $curr_electric < $prev_electric) {
        header("Location: meter_records.php?msg=error_value");
        exit();
    }

    // คำนวณยอดเงิน
    $water_units = $curr_water - $prev_water;
    $electric_units = $curr_electric - $prev_electric;
    $water_total = $water_units * $water_rate;
    $electric_total = $electric_units * $electric_rate;
    $meter_total_sum = $water_total + $electric_total;

    try {
        $pdo->beginTransaction();
        
        // บันทึกลงตาราง meters
        $stmt = $pdo->prepare("INSERT INTO meters (room_id, prev_water_meter, curr_water_meter, prev_electric_meter, curr_electric_meter, billing_month, water_total, electric_total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $prev_water, $curr_water, $prev_electric, $curr_electric, $billing_month, $water_total, $electric_total, $meter_total_sum]);
        $meter_id = $pdo->lastInsertId();

        // ค้นหา User ที่อยู่ในห้องนี้เพื่อสร้าง Payment
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

// 4. ดึงรายชื่อห้องที่มีผู้เช่า (Occupied) เท่านั้น
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

// คำนวณ Progress สรุปผล
$total_rooms = count($rooms);
$recorded_count = 0;
foreach($rooms as $rm) if($rm['is_recorded']) $recorded_count++;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกมิเตอร์ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .is-done { opacity: 0.6; filter: grayscale(0.5); pointer-events: none; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="lg:ml-72 transition-all">
        <div class="container mx-auto px-4 py-10">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
                <div>
                    <h1 class="text-4xl font-black text-slate-800 tracking-tighter uppercase italic flex items-center gap-3">
                        <i class="fa-solid fa-gauge-high text-blue-600"></i> Meter Recording
                    </h1>
                    <p class="text-slate-500 font-medium mt-1">
                        ประจำเดือน: <span class="text-blue-600 font-bold"><?= date('F Y') ?></span>
                    </p>
                </div>
                
                <div class="bg-white p-5 rounded-[2.5rem] shadow-sm border border-slate-100 flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center text-white text-xl font-black italic">
                        <?= $recorded_count ?>/<?= $total_rooms ?>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Monthly Progress</p>
                        <p class="text-lg font-black text-slate-700 italic">บันทึกแล้ว</p>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 rounded-[3rem] p-8 mb-10 text-white flex flex-col md:flex-row justify-between items-center gap-8 shadow-2xl shadow-slate-200">
                <div class="flex gap-10">
                    <div class="flex flex-col">
                        <span class="text-blue-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Water Rate</span>
                        <span class="text-3xl font-black italic tracking-tighter">฿<?= number_format($water_rate, 2) ?></span>
                    </div>
                    <div class="flex flex-col border-l border-slate-700 pl-10">
                        <span class="text-amber-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Electric Rate</span>
                        <span class="text-3xl font-black italic tracking-tighter">฿<?= number_format($electric_rate, 2) ?></span>
                    </div>
                </div>
                
                <div class="w-full md:w-96 relative group">
                    <i class="fa-solid fa-magnifying-glass absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                    <input type="text" id="roomSearch" onkeyup="searchRoom()" placeholder="ค้นหาเลขห้องที่นี่..." 
                        class="w-full pl-14 pr-6 py-5 bg-white text-slate-900 border-none rounded-3xl focus:ring-4 focus:ring-blue-500/40 outline-none shadow-xl transition-all font-bold italic placeholder:text-slate-300">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8" id="roomContainer">
                <?php foreach($rooms as $r): ?>
                <div class="bg-white rounded-[3rem] border border-slate-100 shadow-sm flex flex-col room-item transition-all hover:shadow-xl hover:-translate-y-1 <?= $r['is_recorded'] ? 'is-done' : '' ?>" data-room="<?= $r['room_number'] ?>">
                    
                    <div class="p-8 pb-4 flex justify-between items-start">
                        <div>
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full">Room Status: Occupied</span>
                            <h3 class="text-5xl font-black text-slate-800 italic mt-3 tracking-tighter">#<?= $r['room_number'] ?></h3>
                        </div>
                        <div class="text-right">
                            <?php if($r['is_recorded']): ?>
                                <div class="bg-emerald-500 text-white w-12 h-12 flex items-center justify-center rounded-2xl shadow-lg shadow-emerald-200 animate-pulse">
                                    <i class="fa-solid fa-check-double text-xl"></i>
                                </div>
                            <?php else: ?>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tenant</p>
                                <p class="font-black text-slate-700 italic"><?= htmlspecialchars($r['fullname']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="POST" class="p-8 pt-4 space-y-6">
                        <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                        <input type="hidden" name="save_meter" value="1">
                        <input type="hidden" name="billing_month" value="<?= $current_month ?>">

                        <div class="bg-blue-50/50 p-6 rounded-[2.5rem] border border-blue-100/50">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-black text-blue-600 uppercase tracking-widest"><i class="fa-solid fa-droplet mr-2"></i> Water Meter</span>
                                <p class="text-xs font-black text-blue-400 total-display italic">EST. ฿0.00</p>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Previous</label>
                                    <input type="number" name="prev_water_meter" value="<?= $r['last_water'] ?? 0 ?>" readonly class="w-full bg-transparent font-black text-slate-400 outline-none text-2xl italic">
                                </div>
                                <div class="text-right">
                                    <label class="text-[9px] font-black text-blue-500 uppercase tracking-widest block mb-1">Current</label>
                                    <input type="number" name="curr_water_meter" required 
                                        oninput="calc(this, <?= $r['last_water'] ?? 0 ?>, <?= $water_rate ?>)"
                                        class="w-full bg-white border-2 border-blue-100 rounded-2xl px-4 py-3 text-right font-black text-blue-600 outline-none focus:border-blue-500 text-2xl shadow-inner italic transition-all">
                                </div>
                            </div>
                        </div>

                        <div class="bg-amber-50/50 p-6 rounded-[2.5rem] border border-amber-100/50">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-black text-amber-600 uppercase tracking-widest"><i class="fa-solid fa-bolt mr-2"></i> Electric Meter</span>
                                <p class="text-xs font-black text-amber-400 total-display italic">EST. ฿0.00</p>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Previous</label>
                                    <input type="number" name="prev_electric_meter" value="<?= $r['last_electric'] ?? 0 ?>" readonly class="w-full bg-transparent font-black text-slate-400 outline-none text-2xl italic">
                                </div>
                                <div class="text-right">
                                    <label class="text-[9px] font-black text-amber-600 uppercase tracking-widest block mb-1">Current</label>
                                    <input type="number" name="curr_electric_meter" required 
                                        oninput="calc(this, <?= $r['last_electric'] ?? 0 ?>, <?= $electric_rate ?>)"
                                        class="w-full bg-white border-2 border-amber-100 rounded-2xl px-4 py-3 text-right font-black text-amber-600 outline-none focus:border-amber-500 text-2xl shadow-inner italic transition-all">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-5 bg-slate-900 text-white rounded-[2rem] font-black italic shadow-xl shadow-slate-200 hover:bg-blue-600 hover:-translate-y-1 active:scale-95 transition-all uppercase tracking-widest text-sm">
                            Generate Monthly Bill
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // ฟังก์ชันคำนวณยอดเงินแบบ Real-time
        function calc(input, prev, rate) {
            const current = parseInt(input.value) || 0;
            const container = input.closest('div[class*="rounded"]');
            const display = container.querySelector('.total-display');
            
            if (current > 0 && current < prev) {
                input.classList.add('border-rose-500');
                display.innerText = "INVALID READING";
                display.classList.add('text-rose-500');
            } else {
                input.classList.remove('border-rose-500');
                const total = Math.max(0, current - prev) * rate;
                display.innerText = "EST. ฿" + total.toLocaleString(undefined, {minimumFractionDigits: 2});
                display.classList.remove('text-rose-500');
            }
        }

        // ฟังก์ชันค้นหาห้อง
        function searchRoom() {
            const input = document.getElementById('roomSearch').value.toLowerCase();
            const items = document.getElementsByClassName('room-item');
            Array.from(items).forEach(item => {
                const roomNum = item.getAttribute('data-room').toLowerCase();
                item.style.display = roomNum.includes(input) ? 'flex' : 'none';
            });
        }

        // แจ้งเตือน SweetAlert2
        const urlParams = new URLSearchParams(window.location.search);
        const config = { confirmButtonColor: '#0f172a', borderRadius: '2rem' };
        
        if (urlParams.get('msg') === 'success') Swal.fire({ ...config, icon: 'success', title: 'SAVED!', text: 'จดมิเตอร์และสร้างบิลเรียบร้อย' });
        if (urlParams.get('msg') === 'already_exists') Swal.fire({ ...config, icon: 'warning', title: 'DUPLICATED!', text: 'ห้องนี้บันทึกข้อมูลเดือนนี้ไปแล้ว' });
        if (urlParams.get('msg') === 'error_value') Swal.fire({ ...config, icon: 'error', title: 'ERROR!', text: 'เลขมิเตอร์ใหม่ต้องไม่น้อยกว่าเลขเดิม' });
        
        // เคลียร์ URL หลังแสดงแจ้งเตือน
        if(urlParams.has('msg')) window.history.replaceState({}, document.title, window.location.pathname);
    </script>
</body>
</html>