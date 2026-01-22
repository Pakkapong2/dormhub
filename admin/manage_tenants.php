<?php
session_start();
error_reporting(0); // ปิดการแสดงผล Error เพื่อไม่ให้ Warning หลุดเข้าไปใน UI
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
    $booking_id = $_POST['booking_id'] ?? null;

    try {
        $pdo->beginTransaction();

        // 1. จัดการข้อมูล User (อัปเดตข้อมูลถ้ามีอยู่แล้ว หรือเพิ่มใหม่)
        $checkUser = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone = ?");
        $checkUser->execute([$username, $phone]);
        $existingUser = $checkUser->fetch();

        if ($existingUser) {
            // อัปเดตสิทธิ์เป็น user และใส่เลขห้อง
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ?, room_id = ?, password = ?, role = 'user' WHERE user_id = ?");
            $stmt->execute([$name, $phone, $room_id, $password, $existingUser['user_id']]);
        } else {
            // เพิ่มใหม่เป็น user
            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, phone, role, room_id) VALUES (?, ?, ?, ?, 'user', ?)");
            $stmt->execute([$username, $password, $name, $phone, $room_id]);
        }

        // 2. อัปเดตสถานะห้องพักเป็น 'occupied'
        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $updateRoom->execute([$room_id]);

        // 3. อัปเดตสถานะการจอง (ถ้ามี)
        if (!empty($booking_id)) {
            $updateBooking = $pdo->prepare("UPDATE bookings SET status = 'checked_in' WHERE booking_id = ?");
            $updateBooking->execute([$booking_id]);
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
        
        // แก้ไขจุดนี้: ปลดล็อกห้องพัก และเปลี่ยน ROLE กลับเป็น viewer ทันที
        $stmt = $pdo->prepare("UPDATE users SET room_id = NULL, role = 'viewer' WHERE user_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        
        // คืนสถานะห้องเป็นว่าง (available)
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
$tenants = $pdo->query("SELECT u.*, r.room_number FROM users u JOIN rooms r ON u.room_id = r.room_id WHERE u.role = 'user' AND u.room_id IS NOT NULL ORDER BY r.room_number ASC")->fetchAll();
$available_rooms = $pdo->query("SELECT room_id, room_number, status FROM rooms WHERE status IN ('available', 'booked') ORDER BY room_number ASC")->fetchAll();

try {
    $bookings = $pdo->query("
        SELECT b.booking_id, b.room_id, u.fullname, u.phone 
        FROM bookings b 
        INNER JOIN users u ON b.user_id = u.user_id 
        WHERE b.status = 'confirmed'
    ")->fetchAll();
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Anuphan', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    
    <div class="lg:ml-72 transition-all p-4 md:p-10">
        <div class="max-w-7xl mx-auto">
            <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h1 class="text-5xl font-black text-slate-800 italic uppercase tracking-tighter">Tenant <span class="text-green-600">Operations</span></h1>
                </div>
                <button onclick="openModal()" class="px-8 py-5 bg-slate-900 text-white font-black rounded-[2rem] hover:bg-green-600 transition-all shadow-2xl flex items-center gap-3 uppercase italic tracking-widest text-sm text-center">
                    <i class="fa-solid fa-user-plus text-lg"></i> New Check-in
                </button>
            </div>

            <div class="bg-white rounded-[3rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-900 px-10 py-7 text-white">
                    <h3 class="font-black italic uppercase tracking-widest text-center md:text-left">Active Resident List</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] text-slate-400 uppercase tracking-[0.2em] bg-slate-50 border-b">
                                <th class="px-10 py-6">Room</th>
                                <th class="px-8 py-6">Resident Info</th>
                                <th class="px-10 py-6 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($tenants as $t): ?>
                            <tr class="hover:bg-slate-50 transition-all">
                                <td class="px-10 py-7">
                                    <span class="bg-slate-900 text-white px-5 py-2.5 rounded-2xl font-black text-2xl italic"><?= htmlspecialchars($t['room_number']) ?></span>
                                </td>
                                <td class="px-8 py-7">
                                    <div class="flex items-center gap-4">
                                        <img src="<?= (isset($t['line_picture_url']) && $t['line_picture_url']) ? $t['line_picture_url'] : 'https://ui-avatars.com/api/?background=f8fafc&color=16a34a&name='.urlencode($t['fullname']) ?>" class="w-12 h-12 rounded-xl object-cover">
                                        <div>
                                            <p class="font-black text-slate-800 text-xl italic uppercase"><?= htmlspecialchars($t['fullname']) ?></p>
                                            <p class="text-slate-400 font-bold text-sm"><?= htmlspecialchars($t['phone']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-10 py-7 text-right">
                                    <button onclick="confirmCheckout(<?= $t['user_id'] ?>, <?= $t['room_id'] ?>, '<?= htmlspecialchars($t['fullname']) ?>')" class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                                        <i class="fa-solid fa-door-open"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($tenants)): ?>
                                <tr><td colspan="3" class="p-20 text-center text-slate-300 font-bold italic uppercase tracking-widest">No active tenants found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="checkinModal" class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-xl">
        <div class="bg-white rounded-[3.5rem] w-full max-w-lg shadow-2xl overflow-hidden p-10">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-4xl font-black italic uppercase tracking-tighter">Check-in</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-rose-500 text-2xl font-black transition-colors">✕</button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_tenant" value="1">
                <input type="hidden" name="booking_id" id="booking_id">

                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Booking Reference</label>
                    <select id="booking_select" onchange="fillTenantData()" class="w-full mt-1 px-6 py-4 bg-slate-50 border rounded-2xl font-bold outline-none focus:ring-2 focus:ring-green-500 transition-all">
                        <option value="">-- No Booking (Walk-in) --</option>
                        <?php foreach($bookings as $b): ?>
                            <option value="<?= htmlspecialchars($b['fullname']) ?>" 
                                    data-phone="<?= htmlspecialchars($b['phone']) ?>" 
                                    data-room="<?= htmlspecialchars($b['room_id']) ?>" 
                                    data-id="<?= htmlspecialchars($b['booking_id']) ?>">
                                จองโดย: <?= htmlspecialchars($b['fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom" class="text-green-600 font-black">+ Manual Entry</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Full Name</label>
                        <input type="text" name="name" id="tenant_name" required class="w-full mt-1.5 px-6 py-4 bg-slate-50 border rounded-2xl font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Phone Number</label>
                        <input type="text" name="phone" id="tenant_phone" required onkeyup="syncUserPass(this.value)" class="w-full mt-1.5 px-6 py-4 bg-slate-50 border rounded-2xl font-bold outline-none">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Assign Room</label>
                    <select name="room_id" id="room_select" required class="w-full mt-1.5 px-6 py-4 bg-slate-50 border rounded-2xl font-black text-green-600 italic outline-none">
                        <option value="">-- Select Room --</option>
                        <?php foreach($available_rooms as $ar): ?>
                            <option value="<?= $ar['room_id'] ?>">ROOM <?= $ar['room_number'] ?> (<?= strtoupper($ar['status']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bg-blue-50/50 p-4 rounded-2xl flex gap-4">
                    <div class="flex-1">
                        <label class="text-[9px] font-black text-blue-400 uppercase">Username</label>
                        <input type="text" name="username" id="username" readonly class="w-full bg-transparent font-black text-blue-700 outline-none italic">
                    </div>
                    <div class="flex-1">
                        <label class="text-[9px] font-black text-blue-400 uppercase">Password</label>
                        <input type="password" name="password" id="password" readonly class="w-full bg-transparent font-black text-blue-700 outline-none italic">
                    </div>
                </div>

                <button type="submit" class="w-full py-6 bg-green-600 text-white rounded-[2rem] font-black uppercase italic shadow-lg hover:bg-slate-900 transition-all transform active:scale-95">Complete Check-in</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('checkinModal').classList.remove('hidden'); }
        function closeModal() { document.getElementById('checkinModal').classList.add('hidden'); }
        
        function syncUserPass(val) {
            document.getElementById('username').value = val;
            document.getElementById('password').value = val;
        }

        function fillTenantData() {
            const select = document.getElementById('booking_select');
            const opt = select.options[select.selectedIndex];
            const nameI = document.getElementById('tenant_name');
            const phoneI = document.getElementById('tenant_phone');
            const roomS = document.getElementById('room_select');
            const bIdH = document.getElementById('booking_id');
            
            if (select.value === "custom") {
                nameI.value = ""; phoneI.value = ""; bIdH.value = "";
                nameI.readOnly = false; phoneI.readOnly = false;
            } else if (select.value !== "") {
                nameI.value = select.value;
                phoneI.value = opt.getAttribute('data-phone');
                bIdH.value = opt.getAttribute('data-id');
                const roomVal = opt.getAttribute('data-room');
                if(roomVal) roomS.value = roomVal;
                
                nameI.readOnly = true; phoneI.readOnly = true;
                syncUserPass(phoneI.value);
            }
        }

        function confirmCheckout(uId, rId, name) {
            Swal.fire({
                title: `Check-out ${name}?`,
                text: "ยืนยันการย้ายออก สิทธิ์ผู้ใช้จะถูกเปลี่ยนกลับเป็น viewer ทันที",
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
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            const config = {
                confirmButtonColor: '#16a34a',
                borderRadius: '2rem',
                icon: 'success'
            };
            if (msg === 'checked_in') {
                Swal.fire({...config, title: 'Check-in Success!', text: 'เพิ่มผู้เช่าเรียบร้อย'});
            } else if (msg === 'deleted') {
                Swal.fire({...config, title: 'Check-out Success!', text: 'เปลี่ยนสิทธิ์เป็น Viewer เรียบร้อย'});
            }
            window.history.replaceState({}, document.title, "manage_tenants.php");
        }
    </script>
</body>
</html>