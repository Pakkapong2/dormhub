<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ดึงราคาต่อหน่วย
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$water_rate = $config['water_rate'] ?? 0;
$electric_rate = $config['electric_rate'] ?? 0;

// Logic บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_meter'])) {
    $room_id = $_POST['room_id'];
    $prev_water = (int)$_POST['prev_water_meter'];
    $curr_water = (int)$_POST['curr_water_meter'];
    $prev_electric = (int)$_POST['prev_electric_meter'];
    $curr_electric = (int)$_POST['curr_electric_meter'];
    $billing_month = $_POST['billing_month'];

    if ($curr_water < $prev_water || $curr_electric < $prev_electric) {
        header("Location: meter_records.php?msg=error_value");
        exit();
    }

    $water_units = $curr_water - $prev_water;
    $electric_units = $curr_electric - $prev_electric;
    $water_total = $water_units * $water_rate;
    $electric_total = $electric_units * $electric_rate;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO meters (room_id, prev_water_meter, curr_water_meter, prev_electric_meter, curr_electric_meter, billing_month, water_total, electric_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $prev_water, $curr_water, $prev_electric, $curr_electric, $billing_month, $water_total, $electric_total]);
        $meter_id = $pdo->lastInsertId();

        $user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE room_id = ? LIMIT 1");
        $user_stmt->execute([$room_id]);
        $user = $user_stmt->fetch();

        if ($user) {
            $room_info = $pdo->prepare("SELECT price FROM rooms WHERE room_id = ?");
            $room_info->execute([$room_id]);
            $room_price = $room_info->fetchColumn();
            $total_amount = $room_price + $water_total + $electric_total;

            $stmt_pay = $pdo->prepare("INSERT INTO payments (user_id, meter_id, amount, status) VALUES (?, ?, ?, 'pending')");
            $stmt_pay->execute([$user['user_id'], $meter_id, $total_amount]);
        }

        $pdo->commit();
        header("Location: meter_records.php?msg=success");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

// ดึงรายชื่อห้อง
$rooms = $pdo->query("
    SELECT r.*, u.fullname, 
    (SELECT curr_water_meter FROM meters WHERE room_id = r.room_id ORDER BY meter_id DESC LIMIT 1) as last_water,
    (SELECT curr_electric_meter FROM meters WHERE room_id = r.room_id ORDER BY meter_id DESC LIMIT 1) as last_electric
    FROM rooms r 
    JOIN users u ON r.room_id = u.room_id 
    WHERE r.status = 'occupied'
    ORDER BY r.room_number ASC
")->fetchAll();
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
        .meter-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .meter-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="pb-20">
    <div class="container mx-auto px-4 py-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
            <div>
                <h1 class="text-4xl font-black text-slate-800 tracking-tighter italic uppercase">Meter Recording</h1>
                <p class="text-slate-500 font-medium">บันทึกการใช้พลังงานประจำเดือน <span class="text-blue-600"><?= date('M Y') ?></span></p>
            </div>
            <div class="flex gap-4 bg-white p-3 rounded-2xl shadow-sm border border-slate-100">
                <div class="text-center px-4 border-r border-slate-100">
                    <p class="text-[10px] font-bold text-blue-500 uppercase">Water Rate</p>
                    <p class="text-lg font-black">฿<?= number_format($water_rate) ?></p>
                </div>
                <div class="text-center px-4">
                    <p class="text-[10px] font-bold text-orange-500 uppercase">Electric Rate</p>
                    <p class="text-lg font-black">฿<?= number_format($electric_rate) ?></p>
                </div>
            </div>
        </div>

        <div class="mb-8 relative max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" id="roomSearch" onkeyup="searchRoom()" placeholder="ค้นหาเลขห้อง..." 
                class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 outline-none shadow-sm transition-all">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="roomContainer">
            <?php foreach($rooms as $r): ?>
            <div class="meter-card bg-white rounded-[2.5rem] overflow-hidden border border-slate-100 shadow-sm flex flex-col room-item" data-room="<?= $r['room_number'] ?>">
                <div class="p-8 pb-4 flex justify-between items-center">
                    <h3 class="text-3xl font-black text-slate-800 italic">RM <?= $r['room_number'] ?></h3>
                    <div class="text-right">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Tenant</p>
                        <p class="font-bold text-slate-600"><?= htmlspecialchars($r['fullname']) ?></p>
                    </div>
                </div>

                <form method="POST" class="p-8 pt-4 space-y-6">
                    <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                    <input type="hidden" name="save_meter" value="1">
                    <input type="hidden" name="billing_month" value="<?= date('Y-m') ?>">

                    <div class="bg-blue-50/50 p-5 rounded-3xl border border-blue-100/50">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-droplet text-blue-500"></i>
                            <span class="text-xs font-black text-blue-600 uppercase tracking-widest">Water Meter</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 block mb-1 uppercase">Prev</label>
                                <input type="number" name="prev_water_meter" value="<?= $r['last_water'] ?? 0 ?>" readonly class="w-full bg-transparent font-bold text-slate-500 outline-none">
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-blue-400 block mb-1 uppercase text-right">Current</label>
                                <input type="number" name="curr_water_meter" required 
                                    oninput="calc(this, <?= $r['last_water'] ?? 0 ?>, <?= $water_rate ?>)"
                                    class="w-full bg-white border-2 border-blue-200 rounded-xl px-3 py-2 text-right font-black text-blue-600 outline-none focus:border-blue-500 transition-colors">
                            </div>
                        </div>
                        <p class="mt-2 text-[10px] font-bold text-blue-400 text-right total-display">Total: ฿0.00</p>
                    </div>

                    <div class="bg-orange-50/50 p-5 rounded-3xl border border-orange-100/50">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-bolt text-orange-500"></i>
                            <span class="text-xs font-black text-orange-600 uppercase tracking-widest">Electric Meter</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 block mb-1 uppercase">Prev</label>
                                <input type="number" name="prev_electric_meter" value="<?= $r['last_electric'] ?? 0 ?>" readonly class="w-full bg-transparent font-bold text-slate-500 outline-none">
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-orange-400 block mb-1 uppercase text-right">Current</label>
                                <input type="number" name="curr_electric_meter" required 
                                    oninput="calc(this, <?= $r['last_electric'] ?? 0 ?>, <?= $electric_rate ?>)"
                                    class="w-full bg-white border-2 border-orange-200 rounded-xl px-3 py-2 text-right font-black text-orange-600 outline-none focus:border-orange-500 transition-colors">
                            </div>
                        </div>
                        <p class="mt-2 text-[10px] font-bold text-orange-400 text-right total-display">Total: ฿0.00</p>
                    </div>

                    <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black shadow-lg hover:bg-blue-600 transition-all uppercase tracking-widest active:scale-95">
                        Confirm & Save
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // ฟังก์ชันคำนวณเงินสดๆ (Real-time)
        function calc(input, prev, rate) {
            const current = parseInt(input.value) || 0;
            const display = input.closest('div.bg-blue-50\\/50, div.bg-orange-50\\/50').querySelector('.total-display');
            
            if (current < prev) {
                input.classList.add('border-rose-500');
                display.innerText = "Error: เลขต่ำกว่าเดิม";
                display.classList.add('text-rose-500');
            } else {
                input.classList.remove('border-rose-500');
                const total = (current - prev) * rate;
                display.innerText = "Total: ฿" + total.toLocaleString(undefined, {minimumFractionDigits: 2});
                display.classList.remove('text-rose-500');
            }
        }

        // ฟังก์ชันค้นหาห้อง
        function searchRoom() {
            const input = document.getElementById('roomSearch').value.toLowerCase();
            const cards = document.getElementsByClassName('room-item');
            
            Array.from(cards).forEach(card => {
                const roomNum = card.getAttribute('data-room').toLowerCase();
                card.style.display = roomNum.includes(input) ? 'flex' : 'none';
            });
        }

        // การแจ้งเตือน
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: 'สร้างใบแจ้งหนี้ให้ผู้เช่าเรียบร้อยแล้ว', confirmButtonColor: '#0f172a' });
        }
        if (urlParams.get('msg') === 'error_value') {
            Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ถูกต้อง', text: 'เลขมิเตอร์ใหม่ต้องไม่น้อยกว่าเลขเดิม', confirmButtonColor: '#f43f5e' });
        }
    </script>
</body>
</html>