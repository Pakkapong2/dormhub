<?php
session_start();
include '../sidebar.php'; 
require_once __DIR__ . '/../config/db_connect.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- ส่วนจัดการอนุมัติ / ปฏิเสธ ---
if (isset($_POST['update_booking'])) {
    $b_id = $_POST['booking_id'];
    $status = $_POST['status']; // 'confirmed' หรือ 'cancelled'

    try {
        $pdo->beginTransaction(); // ใช้ Transaction เพื่อความปลอดภัยของข้อมูล

        // 1. อัปเดตสถานะการจอง
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->execute([$status, $b_id]);

        // 2. ถ้ากดยกเลิก (cancelled) ให้คืนสถานะห้องเป็น 'available'
        if ($status === 'cancelled') {
            // หา room_id จากรายการจองนี้ก่อน
            $getRoom = $pdo->prepare("SELECT room_id FROM bookings WHERE booking_id = ?");
            $getRoom->execute([$b_id]);
            $room = $getRoom->fetch();

            if ($room) {
                $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?");
                $updateRoom->execute([$room['room_id']]);
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='manage_bookings.php?msg=success';</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); // ถ้าพังให้คืนค่าเดิมทั้งหมด
        echo "Error: " . $e->getMessage();
    }
}

// ดึงรายการจองที่สถานะเป็น 'pending'
$stmt = $pdo->query("
    SELECT b.*, u.fullname, u.phone, r.room_number 
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN rooms r ON b.room_id = r.room_id
    WHERE b.status = 'pending'
    ORDER BY b.booking_date DESC
");
$pending_bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบการจอง | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">
    <main class="lg:ml-72 p-4 md:p-10">
        <div class="max-w-6xl mx-auto">
            <div class="mb-10">
                <h1 class="text-4xl font-black text-slate-800 italic uppercase tracking-tighter">
                    Booking <span class="text-indigo-600">Verification</span>
                </h1>
                <p class="text-slate-500 font-medium">ตรวจสอบหลักฐานการโอนเงินและอนุมัติการจอง</p>
            </div>

            <?php if (empty($pending_bookings)): ?>
                <div class="bg-white rounded-[3rem] p-20 text-center border border-dashed border-slate-300">
                    <i class="fa-solid fa-clipboard-check text-6xl text-slate-200 mb-4"></i>
                    <p class="text-slate-400 font-bold italic">ยังไม่มีรายการที่รอการตรวจสอบในขณะนี้</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($pending_bookings as $row): ?>
                        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col md:flex-row hover:shadow-xl transition-all group">
                            <div class="md:w-64 bg-slate-900 flex items-center justify-center p-4 relative overflow-hidden">
                                <img src="../uploads/slips/<?= $row['slip_image'] ?>" 
                                     class="max-h-48 rounded-2xl shadow-2xl cursor-pointer hover:scale-105 transition-transform"
                                     onclick="window.open(this.src)">
                                <div class="absolute bottom-2 right-2 bg-white/20 backdrop-blur-md text-white text-[9px] px-2 py-1 rounded-lg uppercase font-black">คลิกเพื่อขยาย</div>
                            </div>

                            <div class="flex-1 p-8 flex flex-col md:flex-row justify-between gap-6">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <span class="bg-indigo-600 text-white px-4 py-1.5 rounded-xl font-black italic text-sm">ROOM <?= $row['room_number'] ?></span>
                                        <span class="text-slate-300">|</span>
                                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest italic">
                                            <?= date('d M Y - H:i', strtotime($row['booking_date'])) ?>
                                        </li>
                                    </div>
                                    <h2 class="text-2xl font-black text-slate-800 italic uppercase tracking-tight"><?= htmlspecialchars($row['fullname']) ?></h2>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-bold text-slate-500"><i class="fa-solid fa-phone mr-2 text-indigo-500"></i><?= $row['phone'] ?></p>
                                        <p class="text-sm font-black text-slate-800 mt-2">ยอดเงินมัดจำ: <span class="text-xl text-green-600 italic">฿<?= number_format($row['booking_fee'], 2) ?></span></p>
                                    </div>
                                </div>

                                <div class="flex flex-row md:flex-col justify-center gap-3">
                                    <form method="POST" onsubmit="return confirmAction('ยืนยันการอนุมัติ?', 'เมื่ออนุมัติแล้ว รายการนี้จะไปปรากฏในหน้าเช็คอิน')">
                                        <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button name="update_booking" class="w-full md:w-32 py-4 bg-green-500 text-white rounded-2xl font-black italic uppercase text-xs shadow-lg shadow-green-100 hover:bg-slate-900 transition-all transform hover:-translate-y-1">อนุมัติ</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirmAction('ปฏิเสธการจอง?', 'รายการนี้จะถูกยกเลิกและผู้จองจะเห็นสถานะถูกปฏิเสธ')">
                                        <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button name="update_booking" class="w-full md:w-32 py-4 bg-rose-50 text-rose-500 border border-rose-100 rounded-2xl font-black italic uppercase text-xs hover:bg-rose-500 hover:text-white transition-all transform hover:-translate-y-1">ปฏิเสธ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function confirmAction(title, text) {
            return true; // หรือใช้ SweetAlert2 มาดักก็นะครับ แต่เพื่อความชัวร์ใช้ return true ไว้ก่อน
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({
                title: 'ดำเนินการสำเร็จ!',
                text: 'อัปเดตสถานะการจองเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                borderRadius: '2rem'
            });
        }
    </script>
</body>
</html>