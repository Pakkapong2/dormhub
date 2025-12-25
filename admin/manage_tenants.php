<?php
session_start();
require '../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. Logic: เพิ่มผู้เช่า (Check-in)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $room_id = $_POST['room_id'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // [FIX] ใช้ role = 'user' แทน 'tenant' เพื่อให้ตรงกับ Database Schema ที่กำหนด enum ไว้
        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, phone, role, room_id) VALUES (?, ?, ?, ?, 'user', ?)");
        $stmt->execute([$username, $password, $name, $phone, $room_id]);

        // อัปเดตห้องเป็น 'occupied'
        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $updateRoom->execute([$room_id]);

        // ถ้าเลือกมาจากการจอง ให้เปลี่ยนสถานะการจองด้วย
        if (!empty($_POST['booking_id'])) {
            $updateBooking = $pdo->prepare("UPDATE bookings SET status = 'checked_in' WHERE id = ?");
            $updateBooking->execute([$_POST['booking_id']]);
        }

        $pdo->commit();
        header("Location: manage_tenants.php?msg=checked_in");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}

// 3. Logic: ย้ายผู้เช่าออก (Check-out) - [FIXED]
if (isset($_GET['delete_id']) && isset($_GET['room_id'])) {
    try {
        $pdo->beginTransaction();
        
        // [FIX] เปลี่ยนจาก DELETE เป็น UPDATE เพื่อรักษาประวัติการเงิน (Revenue) ไว้
        // ปลด room_id ออก แต่ยังเก็บ user ไว้ในระบบ (อาจจะเปลี่ยน role หรือคงเดิมก็ได้)
        $stmt = $pdo->prepare("UPDATE users SET room_id = NULL WHERE user_id = ?");
        $stmt->execute([$_GET['delete_id']]);

        // คืนสถานะห้องเป็นว่าง
        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?")->execute([$_GET['room_id']]);
        
        $pdo->commit();
        header("Location: manage_tenants.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}

// 4. ดึงข้อมูลแสดงผล
// [FIX] ดึงเฉพาะ user ที่มีห้องอยู่ (Active Tenants) แทนการเช็ค role='tenant' ซึ่งไม่มีใน DB
$tenants = $pdo->query("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.role = 'user' AND u.room_id IS NOT NULL 
    ORDER BY r.room_number ASC
")->fetchAll();

$available_rooms = $pdo->query("SELECT room_id, room_number FROM rooms WHERE status = 'available' ORDER BY room_number ASC")->fetchAll();

// เช็คก่อนว่ามีตาราง bookings หรือไม่ เพื่อกัน Error กรณีจองยังไม่เสร็จ
try {
    $bookings = $pdo->query("SELECT * FROM bookings WHERE status = 'confirmed'")->fetchAll();
} catch (Exception $e) {
    $bookings = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้เช่า | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-users text-blue-600"></i> จัดการผู้เช่า
                </h1>
                <p class="text-slate-500 font-medium ml-10">รายชื่อผู้เข้าพักและการเช็คอิน</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openModal()" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-2xl hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                    <i class="fa-solid fa-user-plus mr-2"></i> เช็คอินผู้เช่าใหม่
                </button>
                <a href="manage_rooms.php" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl hover:bg-slate-50 transition shadow-sm">
                    หน้าจัดการห้อง
                </a>
            </div>
        </div>

        <div class="glass-card rounded-[2.5rem] shadow-xl overflow-hidden border border-white">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-800 text-white">
                    <tr class="text-[11px] uppercase tracking-widest">
                        <th class="px-8 py-5">ห้อง</th>
                        <th class="px-8 py-5">ชื่อ-นามสกุล</th>
                        <th class="px-8 py-5">เบอร์โทรศัพท์</th>
                        <th class="px-8 py-5">Username</th>
                        <th class="px-8 py-5 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($tenants)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center text-slate-400 font-medium">ไม่พบผู้เข้าพักในขณะนี้</td></tr>
                    <?php endif; ?>
                    <?php foreach($tenants as $t): ?>
                    <tr class="hover:bg-blue-50/50 transition-all">
                        <td class="px-8 py-5 font-black text-blue-600 text-xl italic"><?= htmlspecialchars($t['room_number'] ?? 'N/A') ?></td>
                        <td class="px-8 py-5 font-bold text-slate-700"><?= htmlspecialchars($t['fullname'] ?? $t['username']) ?></td>
                        <td class="px-8 py-5 text-slate-500 font-medium tracking-tighter"><i class="fa-solid fa-phone mr-1 text-xs text-slate-300"></i> <?= htmlspecialchars($t['phone']) ?></td>
                        <td class="px-8 py-5"><span class="px-3 py-1 bg-slate-100 rounded-lg text-xs font-mono text-slate-600"><?= htmlspecialchars($t['username']) ?></span></td>
                        <td class="px-8 py-5 text-right">
                            <button onclick="confirmCheckout(<?= $t['user_id'] ?>, <?= $t['room_id'] ?>, '<?= htmlspecialchars($t['fullname'] ?? $t['username']) ?>')" class="text-rose-500 hover:text-rose-700 font-bold text-sm flex items-center justify-end gap-1 ml-auto">
                                <i class="fa-solid fa-door-open"></i> ย้ายออก
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="checkinModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl overflow-hidden relative animate-in fade-in zoom-in duration-300">
            <div class="p-8 bg-blue-600 text-white flex justify-between items-center">
                <h2 class="text-xl font-black italic uppercase tracking-tighter">New Check-in</h2>
                <button onclick="closeModal()" class="text-2xl hover:rotate-90 transition-transform">✕</button>
            </div>
            <form method="POST" class="p-8 space-y-4">
                <input type="hidden" name="add_tenant" value="1">
                
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ดึงข้อมูลจากการจอง</label>
                    <select id="booking_select" onchange="fillTenantData()" class="w-full mt-1 px-4 py-3 bg-blue-50 border border-blue-100 rounded-xl font-bold text-blue-700 outline-none">
                        <option value="">-- เลือกผู้จอง (ถ้ามี) --</option>
                        <?php foreach($bookings as $b): ?>
                            <option value="<?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?>" 
                                    data-phone="<?= htmlspecialchars($b['phone']) ?>"
                                    data-room="<?= htmlspecialchars($b['room_id']) ?>"
                                    data-id="<?= htmlspecialchars($b['id']) ?>">
                                <?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?> (<?= $b['phone'] ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="custom">-- กรอกข้อมูลเอง --</option>
                    </select>
                    <input type="hidden" name="booking_id" id="booking_id">
                </div>

                <hr class="border-slate-100">

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-นามสกุล</label>
                    <input type="text" name="name" id="tenant_name" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" id="tenant_phone" onkeyup="syncUserPass(this.value)" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้องพักที่ว่าง</label>
                    <select name="room_id" id="room_select" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกห้อง --</option>
                        <?php foreach($available_rooms as $ar): ?>
                            <option value="<?= $ar['room_id'] ?>">ห้อง <?= $ar['room_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-blue-600">Username</label>
                        <input type="text" name="username" id="username" required class="w-full mt-1 px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl font-mono text-xs outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-blue-600">Password</label>
                        <input type="password" name="password" id="password" required class="w-full mt-1 px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl font-mono text-xs outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black shadow-xl hover:bg-blue-600 transition uppercase tracking-widest mt-4">
                    ยืนยันการเช็คอิน
                </button>
            </form>
        </div>
    </div>

    <script>
        // ฟังก์ชันช่วยเติมข้อมูลจากการจอง
        function fillTenantData() {
            const select = document.getElementById('booking_select');
            const nameI = document.getElementById('tenant_name');
            const phoneI = document.getElementById('tenant_phone');
            const roomS = document.getElementById('room_select');
            const bIdH = document.getElementById('booking_id');
            
            const opt = select.options[select.selectedIndex];
            
            if (select.value === "custom") {
                nameI.value = ""; phoneI.value = ""; bIdH.value = "";
                nameI.readOnly = false; phoneI.readOnly = false;
            } else if (select.value !== "") {
                nameI.value = select.value;
                phoneI.value = opt.getAttribute('data-phone');
                bIdH.value = opt.getAttribute('data-id');
                
                const rId = opt.getAttribute('data-room');
                if(rId) roomS.value = rId;

                nameI.readOnly = true;
                phoneI.readOnly = true;
                syncUserPass(phoneI.value);
            }
        }

        // ฟังก์ชันช่วย Auto-fill Username/Password ด้วยเบอร์โทร
        function syncUserPass(val) {
            document.getElementById('username').value = val;
            document.getElementById('password').value = val;
        }

        function openModal() { document.getElementById('checkinModal').classList.add('active'); }
        function closeModal() { document.getElementById('checkinModal').classList.remove('active'); }

        function confirmCheckout(uId, rId, name) {
            Swal.fire({
                title: `เช็คเอาท์คุณ ${name}?`,
                text: "สถานะห้องจะกลับเป็น 'ว่าง' และรายชื่อจะถูกย้ายออกจากห้อง",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'ยืนยันการย้ายออก',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `manage_tenants.php?delete_id=${uId}&room_id=${rId}`;
            });
        }

        // แจ้งเตือนจาก PHP
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'checked_in') Swal.fire({ title: 'เช็คอินสำเร็จ!', text: 'ข้อมูลผู้เช่าถูกบันทึกแล้ว', icon: 'success', confirmButtonColor: '#2563eb' });
        if (urlParams.get('msg') === 'deleted') Swal.fire({ title: 'เช็คเอาท์สำเร็จ!', text: 'คืนสถานะห้องเรียบร้อย', icon: 'info', confirmButtonColor: '#2563eb' });
    </script>
</body>
</html>