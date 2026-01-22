<?php
include 'sidebar.php'; 
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูลผู้ใช้ปัจจุบันเพื่อเอามาเติมในฟอร์มอัตโนมัติ (ถ้ามี)
$user_id = $_SESSION['user_id'];
$user_stmt = $pdo->prepare("SELECT fullname, phone FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch();

$room_id = $_GET['room_id'] ?? null;
// ดึงข้อมูลห้องพักเพื่อเอาค่า base_rent มาคำนวณ
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) die("ไม่พบข้อมูลห้องพัก");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>จองห้องพัก - <?= htmlspecialchars($room['room_number']) ?></title>
</head>
<body class="bg-slate-50">
    <main class="lg:ml-72 min-h-screen p-4 md:p-10">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-2xl md:text-3xl font-black text-slate-800 mb-6 md:mb-8 uppercase italic ml-2">ยืนยันการจองห้องพัก</h1>
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 md:gap-8 items-start">
                
                <div class="lg:col-span-5 xl:col-span-4 bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl p-6 md:p-8 border border-slate-100 text-center lg:sticky lg:top-10">
                    <h2 class="text-lg font-black text-slate-800 mb-6 uppercase tracking-tight">ช่องทางชำระเงิน</h2>
                    
                    <div class="relative inline-block mb-6 group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-[2.5rem] blur opacity-25"></div>
                        <div class="relative bg-white p-4 rounded-[2rem] border border-slate-100 shadow-inner">
                            <img src="assets/img/qr.jpg" alt="QR Code" class="w-48 h-48 md:w-56 md:h-56 object-contain mx-auto rounded-xl">
                            <div class="mt-4 flex flex-col items-center">
                                <span class="bg-emerald-100 text-emerald-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-1">Thai QR Payment</span>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-tighter">สแกนเพื่อจ่าย (PromptPay)</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-50 text-left">
                        <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50 text-center">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">เลขบัญชี (กสิกรไทย)</p>
                            <p class="text-indigo-600 text-xl md:text-2xl font-black tracking-tighter italic">012-3-45678-9</p>
                            <p class="text-xs font-bold text-slate-800">ชื่อบัญชี: หอพัก DormHub</p>
                        </div>

                        <div class="bg-amber-50 p-5 rounded-2xl border border-amber-100">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-black uppercase text-amber-600">เงินมัดจำประกันหอพัก</span>
                                <span class="font-bold text-amber-700">฿<?= number_format($room['base_rent'], 2) ?></span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-amber-200">
                                <span class="text-xs font-black text-amber-800 uppercase italic">ยอดที่ต้องโอนจอง</span>
                                <span class="text-xl font-black text-amber-800 leading-none">฿<?= number_format($room['base_rent'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7 xl:col-span-8">
                    <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl p-6 md:p-10 border border-slate-100">
                        <div class="flex items-center space-x-4 mb-8 md:mb-10">
                            <div class="w-12 h-12 md:w-14 md:h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-indigo-200">
                                <i class="fa-solid fa-file-invoice text-lg md:text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl md:text-2xl font-black text-slate-800 uppercase italic leading-none">แจ้งหลักฐานการโอน</h3>
                                <p class="text-xs md:text-sm font-bold text-slate-400 mt-1 uppercase">ห้องที่เลือก: <?= htmlspecialchars($room['room_number']) ?></p>
                            </div>
                        </div>

                        <form action="booking_process.php" method="POST" enctype="multipart/form-data" class="space-y-6 md:space-y-8">
                            <input type="hidden" name="room_id" value="<?= htmlspecialchars($room['room_id']) ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 bg-slate-50/50 p-6 rounded-[1.5rem] border border-dashed border-slate-200">
                                <div class="space-y-2 lg:col-span-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-2 italic"><i class="fa-solid fa-user-tag mr-1"></i> ข้อมูลผู้จอง (จะถูกอัปเดตลงโปรไฟล์)</h4>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] md:text-xs font-black uppercase tracking-widest text-slate-400 ml-1">ชื่อ-นามสกุลจริง</label>
                                    <input type="text" name="fullname" required value="<?= htmlspecialchars($current_user['fullname'] ?? '') ?>"
                                           placeholder="เช่น นายสมชาย ใจดี"
                                           class="w-full p-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-bold text-slate-700 shadow-sm text-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] md:text-xs font-black uppercase tracking-widest text-slate-400 ml-1">เบอร์โทรศัพท์ติดต่อ</label>
                                    <input type="tel" name="phone" required value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>"
                                           placeholder="08XXXXXXXX"
                                           class="w-full p-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-bold text-slate-700 shadow-sm text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                                <div class="space-y-2">
                                    <label class="text-[10px] md:text-xs font-black uppercase tracking-widest text-slate-400 ml-1">วันที่แจ้งจะเข้าอยู่</label>
                                    <input type="date" name="move_in_date" required 
                                           class="w-full p-4 md:p-5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-bold text-slate-700 shadow-sm text-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] md:text-xs font-black uppercase tracking-widest text-slate-400 ml-1">เงินมัดจำ (ล็อคตามราคาห้อง)</label>
                                    <div class="relative">
                                        <span class="absolute left-5 top-1/2 -translate-y-1/2 font-black text-indigo-600">฿</span>
                                        <input type="number" name="booking_fee" value="<?= (int)$room['base_rent'] ?>" readonly 
                                               class="w-full p-4 md:p-5 pl-10 rounded-2xl border border-slate-100 bg-slate-50 outline-none font-black text-indigo-600 shadow-sm text-sm cursor-not-allowed">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] md:text-xs font-black uppercase tracking-widest text-slate-400 ml-1">อัปโหลดสลิปโอนเงิน</label>
                                <input type="file" name="slip_image" accept="image/*" required 
                                       class="w-full p-4 md:p-5 rounded-2xl border border-slate-200 file:mr-4 file:py-2 file:px-4 md:file:px-6 file:rounded-xl file:border-0 file:text-[10px] md:file:text-xs file:font-black file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 transition-all cursor-pointer bg-white shadow-sm text-xs">
                            </div>

                            <div class="pt-4 md:pt-6">
                                <button type="submit" class="w-full py-5 md:py-6 bg-indigo-600 text-white rounded-[1.5rem] md:rounded-[2rem] font-black text-lg md:text-xl shadow-2xl shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-1 transition-all uppercase italic flex items-center justify-center space-x-3">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span>ยืนยันการจองห้องพัก</span>
                                </button>
                                <p class="text-center text-[10px] text-slate-400 mt-6 uppercase font-black tracking-[0.2em]">
                                    ระบบจะตรวจสอบสลิปภายใน 24 ชม.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>