<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// Check Admin Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Logic: อัปเดตสถานะงานซ่อม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $m_id = $_POST['maintenance_id'];
    $status = $_POST['status'];
    $remark = $_POST['admin_remark'] ?? '';
    
    $fixed_at = ($status == 'fixed') ? date('Y-m-d H:i:s') : NULL;

    $sql = "UPDATE maintenance SET status = ?, admin_remark = ?";
    $params = [$status, $remark];

    if ($fixed_at) {
        $sql .= ", fixed_at = ?";
        $params[] = $fixed_at;
    }

    $sql .= " WHERE maintenance_id = ?";
    $params[] = $m_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: manage_maintenance.php?msg=updated");
    exit();
}

// --- Logic: เพิ่มรายการแจ้งซ่อม (Manual Add) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_ticket'])) {
    $room_id = $_POST['room_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];

    $stmt_user = $pdo->prepare("SELECT user_id FROM users WHERE room_id = ? LIMIT 1");
    $stmt_user->execute([$room_id]);
    $user_id = $stmt_user->fetchColumn();

    if (!$user_id) { $user_id = $_SESSION['user_id'] ?? 1; }

    $stmt = $pdo->prepare("INSERT INTO maintenance (user_id, room_id, title, description, status, reported_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $room_id, $title, $desc]);
    
    header("Location: manage_maintenance.php?msg=added");
    exit();
}

// --- Query ข้อมูล ---
$sql = "SELECT m.*, r.room_number 
        FROM maintenance m 
        LEFT JOIN rooms r ON m.room_id = r.room_id 
        ORDER BY FIELD(m.status, 'pending', 'in_progress', 'fixed', 'cancelled'), m.reported_at DESC";
$tickets = $pdo->query($sql)->fetchAll();
$rooms = $pdo->query("SELECT room_id, room_number FROM rooms ORDER BY room_number ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; background-color: #f8fafc; }
        .modal-blur { backdrop-filter: blur(8px); background-color: rgba(15, 23, 42, 0.8); }
        .brutalist-card { border: 1px solid #e2e8f0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .brutalist-card:hover { transform: translateY(-4px); border-color: #22c55e; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="lg:ml-72 transition-all p-4 md:p-10">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-6">
                <div>
                    <h1 class="text-5xl font-black text-slate-800 tracking-tighter uppercase italic">
                        Maintenance <span class="text-green-600">Tickets</span>
                    </h1>
                    <p class="text-slate-500 font-medium mt-2">ระบบจัดการงานซ่อมบำรุงและติดตามสถานะ</p>
                </div>
                <button onclick="openAddModal()" class="px-8 py-5 bg-slate-900 text-white font-black rounded-[2rem] hover:bg-green-600 transition-all shadow-2xl flex items-center gap-3 uppercase italic tracking-widest text-xs">
                    <i class="fa-solid fa-plus text-green-400"></i> New Repair Job
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <?php 
                $pending_count = count(array_filter($tickets, fn($t) => $t['status'] === 'pending'));
                $progress_count = count(array_filter($tickets, fn($t) => $t['status'] === 'in_progress'));
                ?>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm text-center">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending</p>
                    <p class="text-3xl font-black text-rose-500 italic"><?= $pending_count ?></p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm text-center">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">In Progress</p>
                    <p class="text-3xl font-black text-blue-500 italic"><?= $progress_count ?></p>
                </div>
            </div>

            <div class="bg-white rounded-[3rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-900 px-8 py-6 flex items-center gap-3 text-white relative overflow-hidden">
                    <div class="w-2 h-6 bg-green-500 rounded-full"></div>
                    <h3 class="font-black italic uppercase tracking-widest text-sm">Recent Repair Requests</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50 border-b">
                                <th class="px-8 py-6">Room</th>
                                <th class="px-8 py-6">Issue & Details</th>
                                <th class="px-8 py-6">Status</th>
                                <th class="px-8 py-6">Reported At</th>
                                <th class="px-8 py-6 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($tickets as $t): ?>
                            <tr class="hover:bg-slate-50/80 transition-all group">
                                <td class="px-8 py-7">
                                    <span class="bg-slate-900 text-white px-5 py-2 rounded-2xl font-black text-xl italic shadow-md">
                                        <?= $t['room_number'] ?>
                                    </span>
                                </td>
                                <td class="px-8 py-7">
                                    <p class="font-black text-slate-800 italic uppercase leading-tight"><?= htmlspecialchars($t['title']) ?></p>
                                    <p class="text-slate-400 text-xs font-medium mt-1 truncate max-w-[200px]"><?= htmlspecialchars($t['description']) ?></p>
                                    <?php if(!empty($t['admin_remark'])): ?>
                                        <div class="mt-2 flex items-center gap-2 text-[10px] font-bold text-green-600 bg-green-50 w-fit px-2 py-1 rounded-lg border border-green-100">
                                            <i class="fa-solid fa-comment-dots"></i> <?= htmlspecialchars($t['admin_remark']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-7">
                                    <?php 
                                        $s = [
                                            'pending' => ['bg'=>'bg-rose-50', 'text'=>'text-rose-600', 'dot'=>'bg-rose-500', 'label'=>'PENDING'],
                                            'in_progress' => ['bg'=>'bg-blue-50', 'text'=>'text-blue-600', 'dot'=>'bg-blue-500', 'label'=>'IN PROGRESS'],
                                            'fixed' => ['bg'=>'bg-green-50', 'text'=>'text-green-600', 'dot'=>'bg-green-500', 'label'=>'FIXED'],
                                            'cancelled' => ['bg'=>'bg-slate-100', 'text'=>'text-slate-400', 'dot'=>'bg-slate-400', 'label'=>'CANCELLED'],
                                        ][$t['status']] ?? ['bg'=>'bg-slate-50', 'text'=>'text-slate-500', 'dot'=>'bg-slate-500', 'label'=>'UNKNOWN'];
                                    ?>
                                    <span class="flex items-center gap-2 <?= $s['bg'] ?> <?= $s['text'] ?> px-3 py-1.5 rounded-xl text-[10px] font-black italic border w-fit">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?> animate-pulse"></span>
                                        <?= $s['label'] ?>
                                    </span>
                                </td>
                                <td class="px-8 py-7">
                                    <p class="text-xs font-bold text-slate-500 italic"><?= date('M d, H:i', strtotime($t['reported_at'])) ?></p>
                                </td>
                                <td class="px-8 py-7 text-right">
                                    <button onclick='openEditModal(<?= json_encode($t) ?>)' 
                                            class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white transition-all flex items-center justify-center border border-slate-100 ml-auto">
                                        <i class="fa-solid fa-gear"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="updateModal" class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4 modal-blur">
        <div class="bg-white rounded-[3.5rem] w-full max-w-lg shadow-2xl overflow-hidden border border-white/20">
            <div class="p-10 bg-slate-900 text-white flex justify-between items-center relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-[10px] uppercase tracking-[0.4em] text-green-400 font-black mb-2">Ticket Update</p>
                    <h2 class="text-3xl font-black italic uppercase tracking-tighter">Job #<span id="ticket_id_display"></span></h2>
                </div>
                <button onclick="closeModal('updateModal')" class="w-12 h-12 rounded-2xl bg-white/10 hover:bg-rose-500 transition-all flex items-center justify-center font-black">✕</button>
            </div>
            <form method="POST" class="p-10 space-y-6">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="maintenance_id" id="modal_m_id">
                
                <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Request Info</label>
                    <div id="modal_title" class="font-black text-slate-800 text-xl italic uppercase mt-1"></div>
                    <div id="modal_desc" class="text-slate-500 text-sm font-medium mt-2 leading-relaxed"></div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Work Status</label>
                        <select name="status" id="modal_status" class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black italic text-green-600 outline-none appearance-none focus:ring-4 focus:ring-green-500/10">
                            <option value="pending">🔴 PENDING</option>
                            <option value="in_progress">🔵 IN PROGRESS</option>
                            <option value="fixed">🟢 FIXED / DONE</option>
                            <option value="cancelled">⚪ CANCELLED</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Admin Remark</label>
                        <textarea name="admin_remark" id="modal_remark" rows="2" placeholder="Note for the tenant..." class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold outline-none focus:ring-4 focus:ring-green-500/10"></textarea>
                    </div>
                </div>

                <button type="submit" class="w-full py-6 bg-green-600 text-white rounded-[2.5rem] font-black shadow-2xl shadow-green-200 hover:bg-slate-900 transition-all uppercase tracking-widest italic">
                    Update Progress
                </button>
            </form>
        </div>
    </div>

    <div id="addModal" class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4 modal-blur">
        <div class="bg-white rounded-[3.5rem] w-full max-w-lg shadow-2xl overflow-hidden">
            <div class="p-10 bg-green-600 text-white flex justify-between items-center">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.4em] text-green-100 font-black mb-2">Admin Dashboard</p>
                    <h2 class="text-3xl font-black italic uppercase tracking-tighter text-white">New Repair Job</h2>
                </div>
                <button onclick="closeModal('addModal')" class="w-12 h-12 rounded-2xl bg-white/20 hover:bg-slate-900 transition-all flex items-center justify-center font-black">✕</button>
            </div>
            <form method="POST" class="p-10 space-y-5">
                <input type="hidden" name="add_ticket" value="1">
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Select Room</label>
                    <select name="room_id" required class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black italic outline-none appearance-none">
                        <?php foreach($rooms as $r): ?>
                            <option value="<?= $r['room_id'] ?>">ROOM <?= $r['room_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Job Title</label>
                    <input type="text" name="title" required placeholder="Ex. Air Conditioner issue" class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Description</label>
                    <textarea name="description" rows="3" class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-medium outline-none"></textarea>
                </div>
                <button type="submit" class="w-full py-6 bg-slate-900 text-white rounded-[2.5rem] font-black shadow-xl hover:bg-green-600 transition-all uppercase tracking-widest italic">
                    Create Ticket
                </button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(data) {
            document.getElementById('ticket_id_display').innerText = data.maintenance_id;
            document.getElementById('modal_m_id').value = data.maintenance_id;
            document.getElementById('modal_title').innerText = data.title;
            document.getElementById('modal_desc').innerText = data.description || 'No description provided.';
            document.getElementById('modal_status').value = data.status;
            document.getElementById('modal_remark').value = data.admin_remark || '';
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'updated') Swal.fire({ icon: 'success', title: 'Ticket Updated', text: 'ข้อมูลได้รับการอัปเดตเรียบร้อย', borderRadius: '2.5rem', confirmButtonColor: '#16a34a' });
        if (urlParams.get('msg') === 'added') Swal.fire({ icon: 'success', title: 'Ticket Created', text: 'สร้างรายการแจ้งซ่อมใหม่สำเร็จ', borderRadius: '2.5rem', confirmButtonColor: '#16a34a' });
    </script>
</body>
</html>