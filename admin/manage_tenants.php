<?php
session_start();
require '../config/db_connect.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Logic การดึงข้อมูลผู้เช่าปัจจุบัน ---
$stmt = $pdo->prepare("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.role = 'tenant' 
    ORDER BY r.room_number ASC
");
$stmt->execute();
$tenants = $stmt->fetchAll();

// ดึงรายชื่อห้องที่ว่าง
$roomStmt = $pdo->query("SELECT room_id, room_number FROM rooms WHERE status = 'available' ORDER BY room_number ASC");
$available_rooms = $roomStmt->fetchAll();

// ดึงข้อมูลการจองที่ยืนยันแล้ว (confirmed) เพื่อมาทำ Check-in
// ปรับชื่อคอลัมน์ให้ตรงกับ SQL: customer_name (ถ้าในเครื่องคุณเป็น name ให้เปลี่ยนตรงนี้ครับ)
$bookingStmt = $pdo->query("SELECT * FROM bookings WHERE status = 'confirmed'");
$bookings = $bookingStmt->fetchAll();

// --- ระบบเพิ่มผู้เช่า (Check-in) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $room_id = $_POST['room_id'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // 1. เพิ่มข้อมูล User ใหม่
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role, room_id) VALUES (?, ?, ?, ?, 'tenant', ?)");
        $stmt->execute([$username, $password, $name, $phone, $room_id]);

        // 2. อัปเดตสถานะห้องพัก
        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $updateRoom->execute([$room_id]);

        $pdo->commit();
        header("Location: manage_tenants.php?msg=checked_in");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}

// --- ระบบลบผู้เช่า ---
if (isset($_GET['delete_id']) && isset($_GET['room_id'])) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$_GET['delete_id']]);
        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?")->execute([$_GET['room_id']]);
        $pdo->commit();
        header("Location: manage_tenants.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
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
        .modal { display: none !important; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-users text-blue-600"></i> จัดการผู้เช่า
                </h1>
                <p class="text-slate-500 font-medium ml-10">รายชื่อผู้เข้าพักและการเช็คอินอัตโนมัติ</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openModal()" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-2xl hover:bg-blue-700 transition shadow-lg">
                    <i class="fa-solid fa-user-plus mr-2"></i> เช็คอินผู้เช่าใหม่
                </button>
                <a href="manage_rooms.php" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl hover:bg-slate-50 transition shadow-sm">
                    ไปหน้าจัดการห้อง
                </a>
            </div>
        </div>

        <div class="glass-card rounded-[2.5rem] shadow-xl overflow-hidden border border-white">
            <table class="w-full text-left">
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
                        <tr><td colspan="5" class="px-8 py-10 text-center text-slate-400">ยังไม่มีผู้เช่าในขณะนี้</td></tr>
                    <?php endif; ?>
                    <?php foreach($tenants as $t): ?>
                    <tr class="hover:bg-blue-50/50 transition-all">
                        <td class="px-8 py-5 font-black text-blue-600 text-xl italic"><?= htmlspecialchars($t['room_number'] ?? 'N/A') ?></td>
                        <td class="px-8 py-5 font-bold text-slate-700"><?= htmlspecialchars($t['name']) ?></td>
                        <td class="px-8 py-5 text-slate-500 font-medium"><i class="fa-solid fa-phone mr-1 text-xs"></i> <?= htmlspecialchars($t['phone']) ?></td>
                        <td class="px-8 py-5"><span class="px-3 py-1 bg-slate-100 rounded-lg text-xs font-mono"><?= htmlspecialchars($t['username']) ?></span></td>
                        <td class="px-8 py-5 text-right">
                            <button onclick="confirmCheckout(<?= $t['user_id'] ?>, <?= $t['room_id'] ?>, '<?= $t['name'] ?>')" class="text-rose-500 hover:text-rose-700 font-bold text-sm">
                                <i class="fa-solid fa-door-open mr-1"></i> เช็คเอาท์
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="checkinModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl overflow-hidden relative">
            <div class="p-8 bg-blue-600 text-white flex justify-between items-center">
                <h2 class="text-xl font-black italic uppercase">New Check-in</h2>
                <button onclick="closeModal()" class="text-2xl hover:rotate-90 transition-transform">✕</button>
            </div>
            <form method="POST" class="p-8 space-y-4">
                <input type="hidden" name="add_tenant" value="1">
                
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เลือกข้อมูลจากการจอง</label>
                    <select id="booking_select" onchange="fillTenantData()" class="w-full mt-1 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- เลือกรายชื่อผู้จอง --</option>
                        <?php foreach($bookings as $b): ?>
                            <option value="<?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?>" 
                                    data-phone="<?= htmlspecialchars($b['phone']) ?>"
                                    data-room="<?= htmlspecialchars($b['room_id']) ?>">
                                <?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom">-- กรอกข้อมูลใหม่เอง --</option>
                    </select>
                </div>

                <hr class="border-slate-100">

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-นามสกุล</label>
                    <input type="text" name="name" id="tenant_name" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" id="tenant_phone" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้องพักที่เข้าอยู่</label>
                    <select name="room_id" id="room_select" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- เลือกห้อง --</option>
                        <?php foreach($available_rooms as $ar): ?>
                            <option value="<?= $ar['room_id'] ?>">ห้อง <?= $ar['room_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Username</label>
                        <input type="text" name="username" required class="w-full mt-1 px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl font-mono text-sm outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Password</label>
                        <input type="password" name="password" required class="w-full mt-1 px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl font-mono text-sm outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black shadow-lg hover:bg-blue-600 transition uppercase tracking-widest mt-4">
                    ยืนยันการเช็คอิน
                </button>
            </form>
        </div>
    </div>

    <script>
        function fillTenantData() {
            const select = document.getElementById('booking_select');
            const nameInput = document.getElementById('tenant_name');
            const phoneInput = document.getElementById('tenant_phone');
            const roomSelect = document.getElementById('room_select');
            
            const selectedOption = select.options[select.selectedIndex];
            
            if (select.value === "custom") {
                nameInput.value = ""; phoneInput.value = "";
                nameInput.readOnly = false; phoneInput.readOnly = false;
            } else if (select.value !== "") {
                nameInput.value = select.value;
                phoneInput.value = selectedOption.getAttribute('data-phone');
                
                // Auto-select ห้องที่จองไว้ (ถ้าห้องนั้นยังว่างอยู่)
                const reservedRoomId = selectedOption.getAttribute('data-room');
                if(reservedRoomId) roomSelect.value = reservedRoomId;

                nameInput.readOnly = true;
                phoneInput.readOnly = true;
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'checked_in') Swal.fire({ title: 'เช็คอินสำเร็จ!', icon: 'success' });
        if (urlParams.get('msg') === 'deleted') Swal.fire({ title: 'เช็คเอาท์สำเร็จ!', icon: 'info' });

        function openModal() { document.getElementById('checkinModal').classList.add('active'); }
        function closeModal() { document.getElementById('checkinModal').classList.remove('active'); }

        function confirmCheckout(userId, roomId, name) {
            Swal.fire({
                title: `เช็คเอาท์คุณ ${name}?`,
                text: "ห้องจะเปลี่ยนสถานะเป็นว่างทันที",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `manage_tenants.php?delete_id=${userId}&room_id=${roomId}`;
            });
        }
    </script>
</body>
</html>