<?php
// 1. ต้องรัน Logic PHP ก่อนการแสดงผล HTML เสมอ
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- ส่วนจัดการการยกเลิกการจอง (ย้ายขึ้นมาไว้บนสุด) ---
if (isset($_GET['cancel_id'])) {
    $cancel_id = $_GET['cancel_id'];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT room_id FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$cancel_id, $user_id]);
        $booking = $stmt->fetch();

        if ($booking) {
            $del = $pdo->prepare("DELETE FROM bookings WHERE booking_id = ?");
            $del->execute([$cancel_id]);

            $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?");
            $updateRoom->execute([$booking['room_id']]);

            $pdo->commit();
            // เมื่อรันตรงนี้จะไม่มีปัญหา Headers already sent แล้ว
            header("Location: view_booking.php?msg=cancelled");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

// 2. ค่อยดึงข้อมูลมาโชว์
$stmt = $pdo->prepare("
    SELECT b.*, r.room_number, r.base_rent 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// 3. เริ่มต้นการแสดงผล HTML (รวมถึงการ include sidebar)
include 'sidebar.php'; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>ประวัติการจองของฉัน</title>
</head>
<body class="bg-slate-50">
    <main class="lg:ml-72 min-h-screen p-4 md:p-10">
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-black text-slate-800 uppercase italic">ประวัติการจองห้องพัก</h1>
                    <p class="text-slate-500 font-bold text-xs md:text-sm mt-1">ติดตามสถานะการจองและหลักฐานการโอนเงิน</p>
                </div>
                <a href="index.php" class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all">
                    <i class="fa-solid fa-plus mr-1"></i> จองเพิ่ม
                </a>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="bg-white rounded-[2.5rem] p-20 text-center border border-slate-100 shadow-xl">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                        <i class="fa-solid fa-calendar-xmark text-4xl"></i>
                    </div>
                    <h2 class="text-xl font-black text-slate-400 uppercase">ไม่พบรายการจอง</h2>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($bookings as $row): ?>
                        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden hover:border-indigo-200 transition-all group">
                            <div class="p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                                <div class="flex items-center space-x-6">
                                    <div class="w-16 h-16 bg-indigo-600 rounded-[1.5rem] flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                                        <span class="text-xl font-black italic">H</span>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-black text-slate-800">ห้อง <?= htmlspecialchars($row['room_number']) ?></h3>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                                            จองเมื่อ: <?= date('d/m/Y H:i', strtotime($row['booking_date'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="text-right mr-4">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ยอดเงินมัดจำ</p>
                                        <p class="text-lg font-black text-slate-800 italic">฿<?= number_format($row['booking_fee'], 2) ?></p>
                                    </div>

                                    <?php
                                    $status_class = "bg-slate-100 text-slate-500";
                                    $status_text = "กำลังตรวจสอบ";
                                    if ($row['status'] == 'confirmed') {
                                        $status_class = "bg-emerald-100 text-emerald-600";
                                        $status_text = "ยืนยันแล้ว";
                                    } elseif ($row['status'] == 'cancelled') {
                                        $status_class = "bg-red-100 text-red-600";
                                        $status_text = "ถูกยกเลิก";
                                    }
                                    ?>
                                    <span class="<?= $status_class ?> px-6 py-2 rounded-full text-xs font-black uppercase tracking-widest shadow-sm">
                                        <?= $status_text ?>
                                    </span>

                                    <button onclick="window.open('uploads/slips/<?= $row['slip_image'] ?>', '_blank')" 
                                            class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 hover:bg-indigo-600 hover:text-white transition-all">
                                        <i class="fa-solid fa-image"></i>
                                    </button>

                                    <?php if ($row['status'] == 'pending'): ?>
                                        <button onclick="confirmCancel(<?= $row['booking_id'] ?>, '<?= $row['room_number'] ?>')" 
                                                class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-400 hover:bg-rose-600 hover:text-white transition-all">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function confirmCancel(id, room) {
            Swal.fire({
                title: 'ยกเลิกการจองห้อง ' + room + '?',
                text: "ข้อมูลการจองจะถูกลบทิ้งถาวร",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยันการลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'view_booking.php?cancel_id=' + id;
                }
            })
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'cancelled') {
            Swal.fire({ title: 'ลบรายการแล้ว', icon: 'success', timer: 2000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>