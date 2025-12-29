<?php
session_start();
require '../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. Logic: อนุมัติการชำระเงิน
if (isset($_GET['approve_id'])) {
    $pay_id = $_GET['approve_id'];
    $admin_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("UPDATE payments SET status = 'approved', approved_by = ?, payment_date = NOW(), reject_reason = NULL WHERE payment_id = ?");
    $stmt->execute([$admin_id, $pay_id]);
    header("Location: manage_bills.php?msg=approved");
    exit();
}

// 3. Logic: ปฏิเสธการชำระเงิน
if (isset($_POST['reject_id'])) {
    $pay_id = $_POST['reject_id'];
    $reason = $_POST['reason'] ?? 'หลักฐานการโอนเงินไม่ชัดเจน';
    $stmt_img = $pdo->prepare("SELECT slip_image FROM payments WHERE payment_id = ?");
    $stmt_img->execute([$pay_id]);
    $img = $stmt_img->fetchColumn();
    if ($img && file_exists("../uploads/slips/" . $img)) { unlink("../uploads/slips/" . $img); }
    $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', slip_image = NULL, reject_reason = ? WHERE payment_id = ?");
    $stmt->execute([$reason, $pay_id]);
    header("Location: manage_bills.php?msg=rejected");
    exit();
}

// 4. ดึงข้อมูลสถิติ
$stats_query = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status != 'approved' THEN amount ELSE 0 END) as pending_amount,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as count_paid,
        COUNT(CASE WHEN status = 'waiting' THEN 1 END) as count_waiting,
        COUNT(CASE WHEN status IN ('pending', 'rejected') THEN 1 END) as count_pending
    FROM payments
")->fetch();

$total_paid = $stats_query['paid_amount'] ?? 0;
$total_pending = $stats_query['pending_amount'] ?? 0;
$count_paid = $stats_query['count_paid'] ?? 0;
$count_waiting = $stats_query['count_waiting'] ?? 0;
$count_pending = $stats_query['count_pending'] ?? 0;
$total_bills = $count_paid + $count_waiting + $count_pending;
$percent_paid = $total_bills > 0 ? round(($count_paid / $total_bills) * 100) : 0;

// 5. ดึงข้อมูลบิลทั้งหมด
$query = "
    SELECT p.*, u.fullname, r.room_number, r.base_rent,
           m.prev_water_meter, m.curr_water_meter, m.water_total,
           m.prev_electric_meter, m.curr_electric_meter, m.electric_total, m.billing_month
    FROM payments p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN rooms r ON u.room_id = r.room_id
    LEFT JOIN meters m ON p.meter_id = m.meter_id
    ORDER BY FIELD(p.status, 'waiting', 'rejected', 'pending', 'approved'), p.payment_id DESC
";
$bills = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบิลและรายได้ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; background-color: #f8fafc; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-slate-50 pb-20">

    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 no-print">
            <div>
                <h2 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Billing Management</h2>
                <div class="flex items-center gap-2 text-slate-400 text-sm font-bold mt-1">
                    <a href="admin_dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-slate-600">จัดการบิลและการชำระเงิน</span>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="add_meter.php" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold text-sm hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> จดมิเตอร์/ออกบิล
                </a>
                <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-2xl font-bold text-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> พิมพ์รายงาน
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-10 no-print">
            <div class="stat-card bg-gradient-to-br from-emerald-500 to-teal-600 p-6 rounded-[2rem] text-white shadow-xl flex flex-col justify-between overflow-hidden relative">
                <i class="fa-solid fa-money-bill-wave absolute -right-4 -top-4 text-8xl opacity-10"></i>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">ยอดเงินที่ได้รับแล้ว</p>
                    <h2 class="text-4xl font-black">฿<?= number_format($total_paid, 2) ?></h2>
                </div>
                <p class="mt-4 text-xs font-bold bg-white/20 w-fit px-3 py-1 rounded-full italic"><?= $count_paid ?> รายการสำเร็จ</p>
            </div>

            <div class="stat-card bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ยอดเงินค้างชำระ</p>
                    <h2 class="text-3xl font-black text-slate-800">฿<?= number_format($total_pending, 2) ?></h2>
                </div>
                <p class="mt-4 text-xs font-bold text-rose-500 italic uppercase">รอชำระ <?= $count_waiting + $count_pending ?> บิล</p>
            </div>

            <div class="lg:col-span-2 stat-card bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-8">
                    <div class="w-24 h-24 relative">
                        <canvas id="paymentChart"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-black text-slate-800"><?= $percent_paid ?>%</span>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-800 uppercase text-xs tracking-tighter mb-2">สรุปภาพรวม</h4>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-emerald-500 uppercase"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> จ่ายแล้ว (<?= $count_paid ?>)</div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-blue-500 uppercase"><span class="w-2 h-2 rounded-full bg-blue-500"></span> รอตรวจ (<?= $count_waiting ?>)</div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-300 uppercase"><span class="w-2 h-2 rounded-full bg-slate-200"></span> ค้าง (<?= $count_pending ?>)</div>
                        </div>
                    </div>
                </div>
                <a href="income_report.php" class="text-blue-500 text-xs font-black uppercase hover:underline p-2">ดูรายงาน <i class="fa-solid fa-arrow-right ml-1"></i></a>
            </div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 no-print">
            <div class="relative w-full md:w-96">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="billSearch" onkeyup="searchBill()" placeholder="ค้นหาเบอร์ห้อง หรือชื่อผู้เช่า..." 
                    class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 outline-none transition-all shadow-sm font-medium">
            </div>
            <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-sort"></i> เรียงตาม: รอตรวจสอบล่าสุด
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="billTable">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500">ห้องพัก</th>
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500">ผู้เช่า / รอบบิล</th>
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500 text-center">ยอดสุทธิ</th>
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500 text-center">หลักฐาน</th>
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500 text-center">สถานะ</th>
                            <th class="p-6 text-[10px] font-black uppercase tracking-widest text-slate-500 text-right">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($bills as $b): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors bill-row">
                            <td class="p-6">
                                <span class="w-14 h-14 flex items-center justify-center bg-slate-900 text-white rounded-2xl font-black text-lg italic room-num shadow-inner">
                                    <?= $b['room_number'] ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <p class="font-bold text-slate-700 text-base tenant-name"><?= htmlspecialchars($b['fullname']) ?></p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">
                                    <i class="fa-regular fa-calendar-check mr-1"></i>
                                    <?= $b['billing_month'] ? date('F Y', strtotime($b['billing_month'])) : 'รอบพิเศษ' ?>
                                </p>
                            </td>
                            <td class="p-6 text-center">
                                <p class="font-black text-slate-800 text-xl tracking-tighter">฿<?= number_format($b['amount'], 2) ?></p>
                                <button onclick='showDetail(<?= json_encode($b) ?>)' class="text-[9px] text-blue-500 font-black uppercase hover:underline mt-1">
                                    View Details
                                </button>
                            </td>
                            <td class="p-6 text-center">
                                <?php if($b['slip_image']): ?>
                                    <button onclick="viewSlip('../uploads/slips/<?= $b['slip_image'] ?>')" class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all inline-flex items-center justify-center shadow-sm">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-200"><i class="fa-solid fa-minus"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-center">
                                <?php if($b['status'] == 'pending'): ?>
                                    <span class="bg-slate-100 text-slate-400 px-3 py-1.5 rounded-full text-[9px] font-black uppercase italic">Unpaid</span>
                                <?php elseif($b['status'] == 'waiting'): ?>
                                    <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full text-[9px] font-black uppercase italic animate-pulse ring-1 ring-blue-100">Checking</span>
                                <?php elseif($b['status'] == 'rejected'): ?>
                                    <span class="bg-rose-50 text-rose-600 px-3 py-1.5 rounded-full text-[9px] font-black uppercase italic ring-1 ring-rose-100">Rejected</span>
                                <?php else: ?>
                                    <span class="bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-full text-[9px] font-black uppercase italic ring-1 ring-emerald-100">Approved</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-right space-x-1">
                                <?php if($b['status'] != 'approved'): ?>
                                    <button onclick="confirmApprove(<?= $b['payment_id'] ?>, '<?= $b['room_number'] ?>')" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black hover:bg-emerald-600 transition shadow-lg shadow-emerald-100 uppercase tracking-wider">Approve</button>
                                    <?php if($b['status'] == 'waiting'): ?>
                                    <button onclick="confirmReject(<?= $b['payment_id'] ?>, '<?= $b['room_number'] ?>')" class="bg-white border border-rose-200 text-rose-500 px-5 py-2.5 rounded-xl text-[10px] font-black hover:bg-rose-50 transition uppercase tracking-wider">Reject</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="flex flex-col items-end">
                                        <p class="text-[9px] font-bold text-slate-400">Verified at:</p>
                                        <p class="text-[10px] font-black text-slate-500 uppercase italic leading-none"><?= date('d M y H:i', strtotime($b['payment_date'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="admin_dashboard.php" class="fixed bottom-8 right-8 w-14 h-14 bg-slate-900 text-white rounded-2xl shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all no-print z-50 ring-4 ring-white">
        <i class="fa-solid fa-house"></i>
    </a>

    <script>
        // Chart.js Configuration
        const ctx = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?= $count_paid ?>, <?= $count_waiting ?>, <?= $count_pending ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#cbd5e1'],
                    borderWidth: 0,
                    cutout: '80%'
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } } 
            }
        });

        // Live Search
        function searchBill() {
            let input = document.getElementById('billSearch').value.toLowerCase();
            Array.from(document.getElementsByClassName('bill-row')).forEach(row => {
                let text = row.querySelector('.room-num').innerText + row.querySelector('.tenant-name').innerText;
                row.style.display = text.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        function viewSlip(url) { 
            Swal.fire({ 
                title: 'Transfer Evidence', 
                imageUrl: url, 
                imageAlt: 'Receipt Slip', 
                showCloseButton: true, 
                confirmButtonColor: '#0f172a',
                confirmButtonText: 'Close'
            }); 
        }
        
        function showDetail(data) {
            const wUnits = data.curr_water_meter - data.prev_water_meter;
            const eUnits = data.curr_electric_meter - data.prev_electric_meter;
            Swal.fire({
                title: `<p class="text-xl font-black italic tracking-tighter">INVOICE: RM ${data.room_number}</p>`,
                html: `
                    <div class="text-left space-y-4 p-2 text-sm">
                        <div class="flex justify-between border-b border-slate-100 pb-2"><span>Room Rent</span><span class="font-bold">฿${parseFloat(data.base_rent).toLocaleString()}</span></div>
                        <div class="flex justify-between border-b border-slate-100 pb-2"><span>Water (${wUnits} Unit)</span><span class="font-bold text-blue-600">฿${parseFloat(data.water_total).toLocaleString()}</span></div>
                        <div class="flex justify-between border-b border-slate-100 pb-2"><span>Electric (${eUnits} Unit)</span><span class="font-bold text-orange-500">฿${parseFloat(data.electric_total).toLocaleString()}</span></div>
                        <div class="flex justify-between pt-2 text-slate-900 font-black text-2xl tracking-tighter"><span>Total Amount</span><span class="underline decoration-blue-500">฿${parseFloat(data.amount).toLocaleString()}</span></div>
                    </div>
                `,
                confirmButtonColor: '#0f172a'
            });
        }

        function confirmApprove(id, room) {
            Swal.fire({
                title: 'Approve Payment?', text: `Verify payment for Room ${room}?`, icon: 'question',
                showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'Yes, Approve',
                cancelButtonText: 'Cancel', borderRadius: '20px'
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_bills.php?approve_id=${id}`; });
        }

        function confirmReject(id, room) {
            Swal.fire({
                title: 'Reject Payment', text: 'Reason for rejection:', input: 'text',
                inputPlaceholder: 'e.g. Invalid slip image, wrong amount...',
                showCancelButton: true, confirmButtonColor: '#f43f5e', confirmButtonText: 'Reject Now',
                preConfirm: (reason) => { if (!reason) { Swal.showValidationMessage('Please provide a reason'); } return reason; }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST'; form.action = 'manage_bills.php';
                    const idInp = document.createElement('input'); idInp.type='hidden'; idInp.name='reject_id'; idInp.value=id;
                    const reInp = document.createElement('input'); reInp.type='hidden'; reInp.name='reason'; reInp.value=result.value;
                    form.appendChild(idInp); form.appendChild(reInp); document.body.appendChild(form); form.submit();
                }
            });
        }
    </script>
</body>
</html>