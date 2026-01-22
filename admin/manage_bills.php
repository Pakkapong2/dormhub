<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

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
    
    if ($img && file_exists(__DIR__ . "/../uploads/slips/" . $img)) { 
        unlink(__DIR__ . "/../uploads/slips/" . $img); 
    }
    
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-5px); }
        @media print { .no-print { display: none !important; } .lg\:ml-72 { margin-left: 0 !important; } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="lg:ml-72 transition-all">
        <div class="container mx-auto px-4 py-10">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4 no-print">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3 italic uppercase tracking-tighter">
                        <i class="fa-solid fa-file-invoice-dollar text-blue-600"></i> Billing Management
                    </h1>
                    <p class="text-slate-500 font-medium mt-1">ตรวจสอบการชำระเงินและจัดการรายได้</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-2xl font-black text-xs hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-print"></i> PRINT REPORT
                    </button>
                    <a href="meter_records.php" class="bg-slate-900 text-white px-8 py-3 rounded-2xl font-black text-xs hover:bg-blue-600 shadow-xl transition-all flex items-center gap-2 uppercase italic">
                        <i class="fa-solid fa-plus"></i> Create Bill
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-10 no-print">
                <div class="stat-card bg-emerald-500 p-8 rounded-[2.5rem] text-white shadow-xl relative overflow-hidden">
                    <i class="fa-solid fa-money-bill-trend-up absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-2">ยอดเงินรับแล้ว</p>
                    <h2 class="text-4xl font-black italic">฿<?= number_format($total_paid, 2) ?></h2>
                    <div class="mt-4 inline-block bg-white/20 px-4 py-1 rounded-full text-[10px] font-bold"><?= $count_paid ?> รายการสำเร็จ</div>
                </div>

                <div class="stat-card bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ค้างชำระ</p>
                    <h2 class="text-3xl font-black text-slate-800 italic">฿<?= number_format($total_pending, 2) ?></h2>
                    <p class="mt-4 text-[10px] font-black text-rose-500 uppercase tracking-tight italic">รอชำระ <?= $count_waiting + $count_pending ?> บิล</p>
                </div>

                <div class="lg:col-span-2 stat-card bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm flex items-center justify-between">
                    <div class="flex items-center gap-8">
                        <div class="w-24 h-24 relative">
                            <canvas id="paymentChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-lg font-black text-slate-800 italic"><?= $percent_paid ?>%</span>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-black text-slate-800 uppercase text-xs tracking-widest mb-3 italic">Payment Overview</h4>
                            <div class="space-y-2">
                                <div class="flex items-center gap-3 text-[10px] font-black text-emerald-500 uppercase"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Paid (<?= $count_paid ?>)</div>
                                <div class="flex items-center gap-3 text-[10px] font-black text-blue-500 uppercase"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Checking (<?= $count_waiting ?>)</div>
                                <div class="flex items-center gap-3 text-[10px] font-black text-slate-300 uppercase"><span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span> Pending (<?= $count_pending ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[3rem] shadow-xl border border-slate-100 overflow-hidden">
                <div class="px-10 py-8 border-b border-slate-50 flex flex-col md:flex-row justify-between items-center gap-6">
                    <h3 class="font-black italic uppercase tracking-tighter text-xl">Invoice Transactions</h3>
                    <div class="relative w-full md:w-80">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="billSearch" onkeyup="searchBill()" placeholder="ค้นหาห้อง หรือชื่อผู้เช่า..." 
                            class="w-full pl-12 pr-6 py-3 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold text-sm">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left" id="billTable">
                        <thead class="bg-slate-50/50">
                            <tr>
                                <th class="px-10 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Room</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Tenant / Month</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Amount</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Slip</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Status</th>
                                <th class="px-10 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($bills as $b): ?>
                            <tr class="hover:bg-slate-50/80 transition-all bill-row">
                                <td class="px-10 py-6">
                                    <span class="w-14 h-14 flex items-center justify-center bg-slate-900 text-white rounded-2xl font-black text-lg italic room-num shadow-lg">
                                        <?= $b['room_number'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-6">
                                    <p class="font-black text-slate-800 tenant-name"><?= htmlspecialchars($b['fullname']) ?></p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-1 italic">
                                        <?= $b['billing_month'] ? date('F Y', strtotime($b['billing_month'])) : 'Special Bill' ?>
                                    </p>
                                </td>
                                <td class="px-6 py-6 text-center">
                                    <p class="font-black text-slate-800 text-lg italic tracking-tight">฿<?= number_format($b['amount'], 2) ?></p>
                                    <button onclick='showDetail(<?= json_encode($b) ?>)' class="text-[9px] text-blue-500 font-black uppercase hover:underline mt-1">Details</button>
                                </td>
                                <td class="px-6 py-6 text-center">
                                    <?php if($b['slip_image']): ?>
                                        <button onclick="viewSlip('../uploads/slips/<?= $b['slip_image'] ?>')" class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all inline-flex items-center justify-center">
                                            <i class="fa-solid fa-receipt text-lg"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-200"><i class="fa-solid fa-minus"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-6 text-center">
                                    <?php 
                                        $status_style = [
                                            'pending' => 'bg-slate-100 text-slate-400',
                                            'waiting' => 'bg-blue-50 text-blue-600 animate-pulse ring-1 ring-blue-100',
                                            'rejected' => 'bg-rose-50 text-rose-600',
                                            'approved' => 'bg-emerald-50 text-emerald-600'
                                        ];
                                        $current_style = $status_style[$b['status']] ?? $status_style['pending'];
                                    ?>
                                    <span class="<?= $current_style ?> px-4 py-1.5 rounded-full text-[9px] font-black uppercase italic">
                                        <?= $b['status'] ?>
                                    </span>
                                </td>
                                <td class="px-10 py-6 text-right">
                                    <?php if($b['status'] != 'approved'): ?>
                                        <div class="flex justify-end gap-2">
                                            <button onclick="confirmApprove(<?= $b['payment_id'] ?>, '<?= $b['room_number'] ?>')" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black hover:bg-slate-900 transition shadow-lg uppercase">Approve</button>
                                            <?php if($b['status'] == 'waiting'): ?>
                                                <button onclick="confirmReject(<?= $b['payment_id'] ?>, '<?= $b['room_number'] ?>')" class="bg-white border border-rose-200 text-rose-500 px-5 py-2.5 rounded-xl text-[10px] font-black hover:bg-rose-50 transition uppercase">Reject</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-right">
                                            <p class="text-[8px] font-black text-slate-300 uppercase">Verified</p>
                                            <p class="text-[10px] font-black text-slate-400 uppercase italic leading-none"><?= date('d M Y', strtotime($b['payment_date'])) ?></p>
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
    </div>

    <script>
        // Chart Config
        const ctx = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?= $count_paid ?>, <?= $count_waiting ?>, <?= $count_pending ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#e2e8f0'],
                    borderWidth: 0,
                    cutout: '80%'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        // Search
        function searchBill() {
            let input = document.getElementById('billSearch').value.toLowerCase();
            Array.from(document.getElementsByClassName('bill-row')).forEach(row => {
                let text = row.querySelector('.room-num').innerText + row.querySelector('.tenant-name').innerText;
                row.style.display = text.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // SwAl Functions
        function viewSlip(url) { 
            Swal.fire({ title: 'Transfer Evidence', imageUrl: url, imageAlt: 'Receipt Slip', confirmButtonColor: '#0f172a', borderRadius: '2rem' }); 
        }
        
        function showDetail(data) {
            const wUnits = data.curr_water_meter - data.prev_water_meter;
            const eUnits = data.curr_electric_meter - data.prev_electric_meter;
            Swal.fire({
                title: `<p class="text-xl font-black italic italic tracking-tighter">INVOICE: RM ${data.room_number}</p>`,
                html: `
                    <div class="text-left space-y-4 p-4 text-sm font-bold">
                        <div class="flex justify-between border-b pb-2"><span>Room Rent</span><span>฿${parseFloat(data.base_rent).toLocaleString()}</span></div>
                        <div class="flex justify-between border-b pb-2 text-blue-600"><span>Water (${wUnits} Unit)</span><span>฿${parseFloat(data.water_total).toLocaleString()}</span></div>
                        <div class="flex justify-between border-b pb-2 text-orange-500"><span>Electric (${eUnits} Unit)</span><span>฿${parseFloat(data.electric_total).toLocaleString()}</span></div>
                        <div class="flex justify-between pt-2 text-slate-900 text-2xl font-black italic"><span>Total</span><span class="underline">฿${parseFloat(data.amount).toLocaleString()}</span></div>
                    </div>
                `,
                confirmButtonColor: '#0f172a', borderRadius: '2rem'
            });
        }

        function confirmApprove(id, room) {
            Swal.fire({
                title: 'ยืนยันการชำระเงิน?', text: `ห้อง ${room} ชำระถูกต้องหรือไม่?`, icon: 'question',
                showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'ยืนยัน (Approve)', borderRadius: '2rem'
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_bills.php?approve_id=${id}`; });
        }

        function confirmReject(id, room) {
            Swal.fire({
                title: 'ปฏิเสธการชำระเงิน', text: 'ระบุเหตุผลที่ยกเลิก:', input: 'text',
                showCancelButton: true, confirmButtonColor: '#f43f5e', confirmButtonText: 'ยืนยันยกเลิก', borderRadius: '2rem'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
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