<?php
session_start();
require_once 'config/db_connect.php';

// 1. รับค่า Room ID และตรวจสอบ
$room_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$room_id) {
    header("Location: index.php");
    exit;
}

// 2. Query ข้อมูลห้อง
try {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        die("ไม่พบข้อมูลห้องพักที่ระบุ");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>รายละเอียดห้อง <?= htmlspecialchars($room['room_number']) ?> | DormHub</title>
</head>

<body class="bg-gray-50">

    <?php include 'sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen p-6 md:p-10">
        <div class="max-w-5xl mx-auto">

            <a href="index.php" class="inline-flex items-center text-slate-500 hover:text-indigo-600 mb-6 transition">
                <i class="fa-solid fa-arrow-left mr-2"></i> ย้อนกลับไปหน้าค้นหา
            </a>

            <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100 flex flex-col md:flex-row">

                <div class="md:w-1/2 relative h-[300px] md:h-auto">
                    <img src="uploads/<?= htmlspecialchars($room['room_image'] ?: 'default_room.jpg') ?>"
                        class="w-full h-full object-cover">
                    <div class="absolute top-6 left-6">
                        <span class="bg-white/90 backdrop-blur px-4 py-2 rounded-2xl text-sm font-black shadow-lg border border-white/20">
                            🏢 ชั้น <?= htmlspecialchars($room['floor'] ?: '-') ?>
                        </span>
                    </div>
                </div>

                <div class="md:w-1/2 p-8 md:p-12 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h1 class="text-4xl font-black text-slate-800 tracking-tighter">ห้อง <?= htmlspecialchars($room['room_number']) ?></h1>
                            <span class="<?= $room['status'] === 'available' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> px-3 py-1 rounded-full text-xs font-bold uppercase">
                                <?= $room['status'] === 'available' ? 'ว่าง' : ($room['status'] === 'occupied' ? 'มีผู้เช่าแล้ว' : 'ปิดปรับปรุง') ?>
                            </span>
                        </div>

                        <div class="text-3xl font-black text-indigo-600 mb-6">
                            ฿<?= number_format($room['base_rent']) ?> <span class="text-sm font-medium text-slate-400">/ เดือน</span>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">สิ่งอำนวยความสะดวก</h3>
                                <p class="text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                    <?= nl2br(htmlspecialchars($room['amenities'] ?: 'ไม่มีข้อมูล')) ?>
                                </p>
                            </div>
                            <?php if($room['description']): ?>
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">รายละเอียดเพิ่มเติม</h3>
                                <p class="text-slate-600 text-sm leading-relaxed">
                                    <?= htmlspecialchars($room['description']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-10 pt-8 border-t border-slate-100">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 mb-4">
                                <p class="text-sm text-indigo-700 font-bold flex items-center">
                                    <i class="fa-solid fa-circle-info mr-2"></i> กรุณาเข้าสู่ระบบเพื่อดำเนินการจองห้องพัก
                                </p>
                            </div>
                            <a href="login.php?redirect_to=<?= urlencode('room_detail.php?id=' . $room['room_id']) ?>"
                                class="block w-full text-center bg-indigo-600 text-white py-4 rounded-2xl font-black shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">
                                เข้าสู่ระบบเพื่อจองห้อง
                            </a>
                        <?php elseif ($room['status'] === 'available'): ?>
                            <a href="booking.php?room_id=<?= $room['room_id'] ?>" 
                               class="block w-full text-center bg-green-500 text-white py-4 rounded-2xl font-black shadow-lg shadow-green-200 hover:bg-green-600 transition text-lg uppercase italic">
                                <i class="fa-solid fa-calendar-check mr-2"></i> จองห้องนี้ทันที
                            </a>
                            <p class="text-center text-[10px] text-slate-400 mt-4 uppercase font-bold tracking-widest">
                                * หลังจากกดจอง ระบบจะพาไปหน้ากรอกข้อมูลวันที่เข้าอยู่และแนบสลิปเงินมัดจำ
                            </p>
                        <?php else: ?>
                            <button disabled class="w-full bg-slate-200 text-slate-400 py-4 rounded-2xl font-black cursor-not-allowed">
                                ไม่สามารถจองได้ (ห้องไม่ว่าง)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>