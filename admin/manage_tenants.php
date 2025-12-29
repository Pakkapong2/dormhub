<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- ส่วนจัดการ POST: เพิ่มผู้เช่า (Check-in) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $room_id = $_POST['room_id'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, phone, role, room_id) VALUES (?, ?, ?, ?, 'user', ?)");
        $stmt->execute([$username, $password, $name, $phone, $room_id]);

        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $updateRoom->execute([$room_id]);

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

// --- ส่วนจัดการ GET: ย้ายออก (Check-out) ---
if (isset($_GET['delete_id']) && isset($_GET['room_id'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE users SET room_id = NULL WHERE user_id = ?");
        $stmt->execute([$_GET['delete_id']]);

        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?")->execute([$_GET['room_id']]);
        $pdo->commit();
        header("Location: manage_tenants.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}

// --- ดึงข้อมูลแสดงผล ---
$tenants = $pdo->query("
    SELECT u.*, r.room_number 
    FROM users u 
    LEFT JOIN rooms r ON u.room_id = r.room_id 
    WHERE u.role = 'user' AND u.room_id IS NOT NULL 
    ORDER BY r.room_number ASC
")->fetchAll();

$available_rooms = $pdo->query("SELECT room_id, room_number FROM rooms WHERE status = 'available' ORDER BY room_number ASC")->fetchAll();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้เช่า | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">

    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3 italic uppercase tracking-tighter">
                    <i class="fa-solid fa-users-gear text-green-600"></i> Tenant Management
                </h1>
                <p class="text-slate-500 font-medium ml-10">รายชื่อผู้เข้าพักและการเช็คอิน</p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <a href="admin_dashboard.php" class="flex items-center gap-2 bg-white border border-slate-200 px-6 py-3 rounded-2xl font-black text-slate-600 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all shadow-sm group">
                    <i class="fa-solid fa-house-chimney transition-transform group-hover:-translate-y-0.5"></i>
                    กลับหน้าหลัก
                </a>
                <button onclick="openModal()" class="px-6 py-3 bg-green-600 text-white font-black rounded-2xl hover:bg-slate-900 transition-all shadow-lg shadow-green-100 flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i> เช็คอินผู้เช่าใหม่
                </button>
            </div>
        </div>

        <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden border border-slate-100">
            <div class="bg-slate-900 px-10 py-6 flex items-center gap-3 text-white">
                <span class="text-green-400"><i class="fa-solid fa-list-ul"></i></span>
                <h3 class="font-black italic uppercase tracking-tighter text-lg">รายชื่อผู้เช่าปัจจุบัน</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50 border-b">
                            <th class="px-10 py-5">Room</th>
                            <th class="px-8 py-5">Full Name</th>
                            <th class="px-8 py-5">Contact</th>
                            <th class="px-8 py-5">Account</th>
                            <th class="px-10 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($tenants)): ?>
                            <tr>
                                <td colspan="5" class="px-8 py-24 text-center">
                                    <div class="flex flex-col items-center gap-3 opacity-20">
                                        <i class="fa-solid fa-user-slash text-6xl"></i>
                                        <p class="font-black text-xl italic uppercase">No active tenants</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach($tenants as $t): ?>
                        <tr class="hover:bg-slate-50/50 transition-all group">
                            <td class="px-10 py-6">
                                <span class="bg-slate-900 text-white px-4 py-2 rounded-xl font-black text-xl italic shadow-md">
                                    <?= htmlspecialchars($t['room_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="px-8 py-6 font-black text-slate-700 text-lg italic uppercase tracking-tighter">
                                <?= htmlspecialchars($t['fullname'] ?? $t['username']) ?>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex flex-col">
                                    <span class="text-slate-600 font-bold tracking-widest"><i class="fa-solid fa-phone text-[10px] mr-1 text-green-500"></i> <?= htmlspecialchars($t['phone']) ?></span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <span class="text-[10px] font-black uppercase bg-blue-50 text-blue-500 px-3 py-1 rounded-full border border-blue-100">
                                    ID: <?= htmlspecialchars($t['username']) ?>
                                </span>
                            </td>
                            <td class="px-10 py-6 text-right">
                                <button onclick="confirmCheckout(<?= $t['user_id'] ?>, <?= $t['room_id'] ?>, '<?= htmlspecialchars($t['fullname'] ?? $t['username']) ?>')" 
                                        class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm flex items-center justify-center border border-rose-100 ml-auto">
                                    <i class="fa-solid fa-door-open"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="checkinModal" class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-md">
        <div class="bg-white rounded-[3rem] w-full max-w-md shadow-2xl overflow-hidden">
            <div class="p-10 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.3em] text-green-400 font-black mb-1">Reception</p>
                    <h2 class="text-3xl font-black italic uppercase">New Check-in</h2>
                </div>
                <button onclick="closeModal()" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all flex items-center justify-center font-black italic">✕</button>
            </div>

            <form method="POST" class="p-10 space-y-6">
                <input type="hidden" name="add_tenant" value="1">
                <input type="hidden" name="booking_id" id="booking_id">

                <div class="space-y-4">
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">ดึงข้อมูลการจอง</label>
                        <select id="booking_select" onchange="fillTenantData()" class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black text-slate-700 outline-none focus:ring-2 focus:ring-green-500 appearance-none">
                            <option value="">-- เลือกผู้จอง (ถ้ามี) --</option>
                            <?php foreach($bookings as $b): ?>
                                <option value="<?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?>" 
                                        data-phone="<?= htmlspecialchars($b['phone']) ?>"
                                        data-room="<?= htmlspecialchars($b['room_id']) ?>"
                                        data-id="<?= htmlspecialchars($b['id']) ?>">
                                    <?= htmlspecialchars($b['customer_name'] ?? $b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="custom">-- กรอกข้อมูลเอง --</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">ชื่อผู้เช่า</label>
                        <input type="text" name="name" id="tenant_name" required class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="tenant_phone" onkeyup="syncUserPass(this.value)" required class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none">
                    </div>

                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">ระบุห้องพัก</label>
                        <select name="room_id" id="room_select" required class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none appearance-none">
                            <option value="">-- เลือกห้องพักที่ว่าง --</option>
                            <?php foreach($available_rooms as $ar): ?>
                                <option value="<?= $ar['room_id'] ?>">ห้อง <?= $ar['room_number'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="text-[9px] font-black text-slate-400 uppercase">Username</label>
                        <input type="text" name="username" id="username" readonly class="w-full bg-transparent font-black text-blue-600 outline-none">
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="text-[9px] font-black text-slate-400 uppercase">Password</label>
                        <input type="password" name="password" id="password" readonly class="w-full bg-transparent font-black text-blue-600 outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-5 bg-green-600 text-white rounded-[2rem] font-black shadow-xl shadow-green-100 hover:bg-slate-900 transition-all uppercase tracking-widest mt-4">
                    ยืนยันการเช็คอิน
                </button>
            </form>
        </div>
    </div>

    <script>
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
                nameI.readOnly = true; phoneI.readOnly = true;
                syncUserPass(phoneI.value);
            }
        }

        function syncUserPass(val) {
            document.getElementById('username').value = val;
            document.getElementById('password').value = val;
        }

        function openModal() { document.getElementById('checkinModal').classList.remove('hidden'); }
        function closeModal() { document.getElementById('checkinModal').classList.add('hidden'); }

        function confirmCheckout(uId, rId, name) {
            Swal.fire({
                title: `เช็คเอาท์คุณ ${name}?`,
                text: "สถานะห้องจะกลับเป็น 'ว่าง' และรายชื่อจะถูกย้ายออก",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                borderRadius: '2rem'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `manage_tenants.php?delete_id=${uId}&room_id=${rId}`;
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'checked_in') Swal.fire({ title: 'สำเร็จ!', text: 'เช็คอินเรียบร้อยแล้ว', icon: 'success', borderRadius: '2rem' });
        if (urlParams.get('msg') === 'deleted') Swal.fire({ title: 'เรียบร้อย!', text: 'คืนสถานะห้องว่างแล้ว', icon: 'info', borderRadius: '2rem' });
    </script>
</body>
</html>