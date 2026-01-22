<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in and has the 'user' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch payment history for the logged-in user
try {
    $stmt = $pdo->prepare("
        SELECT p.*, m.billing_month, r.room_number
        FROM payments p
        LEFT JOIN meters m ON p.meter_id = m.meter_id
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN rooms r ON u.room_id = r.room_id
        WHERE p.user_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$user_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching payment history: " . $e->getMessage());
}

$page_title = "ประวัติการชำระเงิน";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen p-6 md:p-10 transition-all">
        <h1 class="text-3xl font-black text-slate-800 italic uppercase tracking-tighter mb-6 flex items-center gap-3">
            <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> <?= htmlspecialchars($page_title) ?>
        </h1>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">ห้อง</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">เดือน</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">จำนวน</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">วันที่ชำระ</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">สถานะ</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">สลิป</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (count($payment_history) > 0): ?>
                            <?php foreach ($payment_history as $payment): ?>
                                <tr class="hover:bg-slate-50/80 transition-all">
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800"><?= htmlspecialchars($payment['room_number']) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800"><?= date('F Y', strtotime($payment['billing_month'])) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-black text-emerald-600">฿<?= number_format($payment['amount'], 2) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <p class="font-bold text-slate-800"><?= date('d M Y H:i', strtotime($payment['payment_date'])) ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <?php
                                            $status_style = [
                                                'pending' => 'bg-slate-100 text-slate-400',
                                                'waiting' => 'bg-blue-50 text-blue-600',
                                                'rejected' => 'bg-rose-50 text-rose-600',
                                                'approved' => 'bg-emerald-50 text-emerald-600'
                                            ];
                                            $current_style = $status_style[$payment['status']] ?? $status_style['pending'];
                                        ?>
                                        <span class="<?= $current_style ?> px-4 py-1.5 rounded-full text-[9px] font-black uppercase italic">
                                            <?= htmlspecialchars($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-4">
                                        <?php if ($payment['slip_image']): ?>
                                            <button onclick="viewSlip('<?= BASE_URL ?>uploads/slips/<?= htmlspecialchars($payment['slip_image']) ?>')" 
                                                    class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all inline-flex items-center justify-center">
                                                <i class="fa-solid fa-receipt text-lg"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-200"><i class="fa-solid fa-minus"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-8 py-10 text-center text-slate-500 font-medium">
                                    ยังไม่มีประวัติการชำระเงิน
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function viewSlip(url) {
            Swal.fire({
                title: 'Transfer Evidence',
                imageUrl: url,
                imageAlt: 'Receipt Slip',
                confirmButtonColor: '#0f172a',
                borderRadius: '2rem'
            });
        }
    </script>
</body>
</html>
