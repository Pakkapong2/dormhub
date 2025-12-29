<?php
session_start();
require '../config/db_connect.php';

// Check Admin Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Logic: อัปเดตสถานะงานซ่อม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $m_id = $_POST['maintenance_id'];
    $status = $_POST['status'];
    // เช็คว่ามีคอลัมน์ admin_remark หรือไม่ (เผื่อยังไม่ได้เพิ่ม)
    $remark = isset($_POST['admin_remark']) ? $_POST['admin_remark'] : '';
    
    $fixed_at = ($status == 'fixed') ? date('Y-m-d H:i:s') : NULL;

    // หมายเหตุ: ถ้าใน DB ยังไม่ได้เพิ่ม admin_remark โค้ดนี้อาจต้องตัดส่วน admin_remark ออก
    // แนะนำให้รัน SQL ALTER TABLE ก่อนครับ
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

    // 1. ต้องหา user_id ของห้องนั้นก่อน เพราะตาราง maintenance บังคับใส่ user_id
    $stmt_user = $pdo->prepare("SELECT user_id FROM users WHERE room_id = ? LIMIT 1");
    $stmt_user->execute([$room_id]);
    $user_id = $stmt_user->fetchColumn();

    if (!$user_id) {
        // กรณีห้องว่าง ไม่มีคนอยู่ แอดมินแจ้งซ่อมเอง (อาจจะต้องแก้ DB ให้ user_id เป็น NULL ได้)
        // เบื้องต้นถ้าหาไม่เจอ ให้ใส่ user_id ของ admin (id=1) ไปก่อนเพื่อกัน Error
        $user_id = $_SESSION['user_id'] ?? 1; 
    }

    $stmt = $pdo->prepare("INSERT INTO maintenance (user_id, room_id, title, description, status, reported_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $room_id, $title, $desc]);
    
    header("Location: manage_maintenance.php?msg=added");
    exit();
}

// --- Query: ดึงข้อมูลรายการซ่อม ---
// แก้ชื่อ Column ให้ตรงกับ DB: maintenance_id, reported_at
$sql = "SELECT m.*, r.room_number 
        FROM maintenance m 
        LEFT JOIN rooms r ON m.room_id = r.room_id 
        ORDER BY FIELD(m.status, 'pending', 'in_progress', 'fixed', 'cancelled'), m.reported_at DESC";
$tickets = $pdo->query($sql)->fetchAll();

// ดึงห้องพักสำหรับ Dropdown
$rooms = $pdo->query("SELECT room_id, room_number FROM rooms ORDER BY room_number ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการแจ้งซ่อม | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal { display: none !important; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="container mx-auto px-4 py-10">
        
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-screwdriver-wrench text-orange-500"></i> รายการแจ้งซ่อม
                </h1>
                <p class="text-slate-500 font-medium ml-10">จัดการคำร้องและสถานะการซ่อมบำรุง</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openAddModal()" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-2xl hover:bg-orange-600 transition shadow-lg shadow-orange-200">
                    <i class="fa-solid fa-plus mr-2"></i> เปิดงานซ่อมใหม่
                </button>
                <a href="admin_dashboard.php" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl hover:bg-slate-50 transition shadow-sm">
                    กลับหน้าหลัก
                </a>
            </div>
        </div>

        <div class="glass-card rounded-[2.5rem] shadow-xl overflow-hidden border border-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-800 text-white">
                        <tr class="text-[11px] uppercase tracking-widest">
                            <th class="px-6 py-5">ห้อง</th>
                            <th class="px-6 py-5">ปัญหาที่แจ้ง</th>
                            <th class="px-6 py-5">วันที่แจ้ง</th>
                            <th class="px-6 py-5 text-center">สถานะ</th>
                            <th class="px-6 py-5 text-center">ช่าง/หมายเหตุ</th>
                            <th class="px-6 py-5 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 font-medium">ไม่มีรายการแจ้งซ่อมในขณะนี้ (เยี่ยมมาก! 🎉)</td></tr>
                        <?php endif; ?>

                        <?php foreach($tickets as $t): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-5 font-black text-slate-700 text-lg">
                                <?= $t['room_number'] ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($t['title']) ?></div>
                                <div class="text-sm text-slate-500 line-clamp-1"><?= htmlspecialchars($t['description']) ?></div>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-500">
                                <i class="fa-regular fa-clock mr-1"></i> <?= date('d/m/y H:i', strtotime($t['reported_at'])) ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php 
                                    $statusConfig = [
                                        'pending' => ['bg'=>'bg-red-100', 'text'=>'text-red-600', 'label'=>'รอดำเนินการ', 'icon'=>'fa-circle-exclamation'],
                                        'in_progress' => ['bg'=>'bg-blue-100', 'text'=>'text-blue-600', 'label'=>'กำลังซ่อม', 'icon'=>'fa-screwdriver'],
                                        'fixed' => ['bg'=>'bg-emerald-100', 'text'=>'text-emerald-600', 'label'=>'ซ่อมเสร็จแล้ว', 'icon'=>'fa-check-circle'],
                                        'cancelled' => ['bg'=>'bg-slate-100', 'text'=>'text-slate-500', 'label'=>'ยกเลิก', 'icon'=>'fa-ban'],
                                    ];
                                    // Fallback ถ้าสถานะไม่ตรง (เช่น cancelled ยังไม่มีใน DB)
                                    $statusKey = isset($statusConfig[$t['status']]) ? $t['status'] : 'pending';
                                    $s = $statusConfig[$statusKey];
                                ?>
                                <span class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wide flex items-center justify-center gap-1 w-fit mx-auto <?= $s['bg'] ?> <?= $s['text'] ?>">
                                    <i class="fa-solid <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-center text-sm">
                                <?php 
                                    // เช็คว่ามี key admin_remark หรือไม่
                                    $remark = isset($t['admin_remark']) ? $t['admin_remark'] : '';
                                ?>
                                <?php if($remark): ?>
                                    <span class="text-slate-600 bg-slate-100 px-2 py-1 rounded border border-slate-200 inline-block max-w-[150px] truncate" title="<?= htmlspecialchars($remark) ?>">
                                        <?= htmlspecialchars($remark) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button onclick='openEditModal(<?= json_encode($t) ?>)' 
                                        class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-800 hover:text-white px-4 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> อัปเดตงาน
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="updateModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl relative overflow-hidden">
            <div class="bg-slate-800 p-6 flex justify-between items-center text-white">
                <h3 class="font-bold text-lg italic">UPDATE TICKET #<span id="ticket_id_display"></span></h3>
                <button onclick="closeModal('updateModal')" class="text-slate-400 hover:text-white transition">✕</button>
            </div>
            <form method="POST" class="p-8 space-y-4">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="maintenance_id" id="modal_m_id">
                
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ปัญหา</label>
                    <div id="modal_title" class="font-bold text-slate-800 text-lg mb-1"></div>
                    <div id="modal_desc" class="text-slate-500 text-sm bg-slate-50 p-3 rounded-xl border border-slate-100"></div>
                </div>

                <hr class="border-slate-100">

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">สถานะงานซ่อม</label>
                    <select name="status" id="modal_status" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="pending">🔴 รอดำเนินการ (Pending)</option>
                        <option value="in_progress">🔵 กำลังดำเนินการ (In Progress)</option>
                        <option value="fixed">🟢 ซ่อมเสร็จแล้ว (Fixed)</option>
                        <option value="cancelled">⚪ ยกเลิก (Cancelled)</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">หมายเหตุ (Admin Note)</label>
                    <textarea name="admin_remark" id="modal_remark" rows="2" placeholder="เช่น ช่างจะเข้าวันจันทร์, ซ่อมเสร็จแล้วเปลี่ยนก๊อกน้ำใหม่" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                </div>

                <button type="submit" class="w-full py-3 bg-slate-900 text-white rounded-xl font-bold hover:bg-orange-600 transition shadow-lg mt-2">
                    บันทึกการเปลี่ยนแปลง
                </button>
            </form>
        </div>
    </div>

    <div id="addModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl relative overflow-hidden">
            <div class="bg-orange-500 p-6 flex justify-between items-center text-white">
                <h3 class="font-bold text-lg italic">NEW TICKET (Admin)</h3>
                <button onclick="closeModal('addModal')" class="text-white/70 hover:text-white transition">✕</button>
            </div>
            <form method="POST" class="p-8 space-y-4">
                <input type="hidden" name="add_ticket" value="1">
                
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เลือกห้อง</label>
                    <select name="room_id" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none">
                        <?php foreach($rooms as $r): ?>
                            <option value="<?= $r['room_id'] ?>">ห้อง <?= $r['room_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">หัวข้อปัญหา</label>
                    <input type="text" name="title" required placeholder="เช่น แอร์น้ำหยด, ไฟห้องน้ำดับ" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รายละเอียดเพิ่มเติม</label>
                    <textarea name="description" rows="3" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none"></textarea>
                </div>

                <button type="submit" class="w-full py-3 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 transition shadow-lg mt-2">
                    สร้างรายการแจ้งซ่อม
                </button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(data) {
            // ใช้ maintenance_id แทน id
            document.getElementById('ticket_id_display').innerText = data.maintenance_id;
            document.getElementById('modal_m_id').value = data.maintenance_id;
            document.getElementById('modal_title').innerText = data.title;
            document.getElementById('modal_desc').innerText = data.description || '-';
            document.getElementById('modal_status').value = data.status;
            
            // เช็คว่ามี admin_remark หรือไม่
            document.getElementById('modal_remark').value = data.admin_remark || '';
            
            document.getElementById('updateModal').classList.add('active');
        }

        function openAddModal() { document.getElementById('addModal').classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'updated') Swal.fire({ icon: 'success', title: 'อัปเดตงานสำเร็จ!', timer: 1500, showConfirmButton: false });
        if (urlParams.get('msg') === 'added') Swal.fire({ icon: 'success', title: 'สร้างรายการสำเร็จ!', timer: 1500, showConfirmButton: false });
    </script>
</body>
</html>