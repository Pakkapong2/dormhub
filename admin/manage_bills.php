<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if (isset($_GET['approve_id'])) {
    $pay_id = $_GET['approve_id'];
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("UPDATE payments SET status = 'paid', approved_by = ?, payment_date = NOW() WHERE payment_id = ?");
    $stmt->execute([$admin_id, $pay_id]);
    header("Location: manage_bills.php?msg=approved");
    exit();
}

$query = "
    SELECT p.*, u.fullname, r.room_number, m.billing_month
    FROM payments p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN rooms r ON u.room_id = r.room_id
    LEFT JOIN meters m ON p.meter_id = m.meter_id
    ORDER BY p.status DESC, p.payment_id DESC
";
$bills = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการบิลและรายได้ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="container mx-auto px-4 py-10">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4 no-print">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">Billing Management</h1>
                <p class="text-slate-500 font-medium">ตรวจสอบยอดชำระและหลักฐานการโอน</p>
            </div>
            <div class="flex gap-2">
                <a href="index.php" class="bg-slate-200 px-5 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-300 transition">
                   กลับหน้าหลัก
                </a>
                <button onclick="window.print()" class="bg-white px-5 py-2.5 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-100 transition">
                    <i class="fa-solid fa-print mr-2"></i> พิมพ์รายงาน
                </button>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white">
                            <th class="p-6 text-xs font-black uppercase tracking-widest">ห้อง</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest">ชื่อผู้เช่า</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest">รอบเดือน</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest">ยอดเงินสุทธิ</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest text-center">สลิป</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest text-center">สถานะ</th>
                            <th class="p-6 text-xs font-black uppercase tracking-widest text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($bills as $b): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-6">
                                <span class="font-black text-blue-600 text-lg italic">RM <?= $b['room_number'] ?></span>
                            </td>
                            <td class="p-6">
                                <p class="font-bold text-slate-700"><?= htmlspecialchars($b['fullname']) ?></p>
                            </td>
                            <td class="p-6">
                                <span class="bg-slate-100 px-3 py-1 rounded-full text-xs font-bold text-slate-500">
                                    <?= date('M Y', strtotime($b['billing_month'])) ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <p class="font-black text-slate-800 text-lg">฿<?= number_format($b['amount'], 2) ?></p>
                            </td>
                            <td class="p-6 text-center">
                                <?php if(!empty($b['slip_image'])): ?>
                                    <button onclick="viewSlip('../uploads/slips/<?= $b['slip_image'] ?>')" class="text-blue-500 hover:text-blue-700 transition">
                                        <i class="fa-solid fa-image text-2xl"></i>
                                    </button>
                                <?php else: ?>
                                    <i class="fa-solid fa-minus text-slate-300"></i>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-center">
                                <?php if($b['status'] == 'pending'): ?>
                                    <span class="bg-orange-100 text-orange-600 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest">ยังไม่จ่าย</span>
                                <?php elseif($b['status'] == 'waiting'): ?>
                                    <span class="bg-blue-100 text-blue-600 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest">รอตรวจสอบ</span>
                                <?php else: ?>
                                    <span class="bg-emerald-100 text-emerald-600 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest">จ่ายแล้ว</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-right">
                                <?php if($b['status'] != 'paid'): ?>
                                    <button onclick="confirmApprove(<?= $b['payment_id'] ?>, '<?= $b['room_number'] ?>')" 
                                        class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-black hover:bg-emerald-600 transition shadow-sm">
                                        ยืนยันยอดเงิน
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-slate-400 italic">เสร็จสิ้น <?= date('d/m/y', strtotime($b['payment_date'])) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // ฟังก์ชันดูรูปสลิป
        function viewSlip(url) {
            Swal.fire({
                title: 'หลักฐานการโอนเงิน',
                imageUrl: url,
                imageAlt: 'Slip Image',
                showCloseButton: true,
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#0f172a'
            });
        }

        function confirmApprove(id, room) {
            Swal.fire({
                title: 'ยืนยันการชำระเงิน?',
                text: `คุณได้ตรวจสอบยอดเงินของห้อง ${room} เรียบร้อยแล้วใช่ไหม?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยัน, รับเงินแล้ว',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_bills.php?approve_id=${id}`;
                }
            })
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'approved') {
            Swal.fire({ icon: 'success', title: 'เรียบร้อย!', text: 'อัปเดตสถานะการชำระเงินแล้ว', confirmButtonColor: '#0f172a' });
        }
    </script>
</body>
</html>