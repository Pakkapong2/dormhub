<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Logic แบ่งหน้า (จำกัด 5 แถว) ---
$limit = 5;
$p_air = isset($_GET['p_air']) ? (int)$_GET['p_air'] : 1;
$p_fan = isset($_GET['p_fan']) ? (int)$_GET['p_fan'] : 1;
$off_air = ($p_air - 1) * $limit;
$off_fan = ($p_fan - 1) * $limit;

// ดึงข้อมูลห้องแอร์ (จำกัด 5)
$air_stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_type = 'air' ORDER BY room_number ASC LIMIT $limit OFFSET $off_air");
$air_stmt->execute();
$air_rooms = $air_stmt->fetchAll();
$total_air = $pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type = 'air'")->fetchColumn();

// ดึงข้อมูลห้องพัดลม (จำกัด 5)
$fan_stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_type = 'fan' ORDER BY room_number ASC LIMIT $limit OFFSET $off_fan");
$fan_stmt->execute();
$fan_rooms = $fan_stmt->fetchAll();
$total_fan = $pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type = 'fan'")->fetchColumn();

// ดึงข้อมูลทั้งหมดสำหรับ Modal (เลื่อนดูได้)
$all_air = $pdo->query("SELECT * FROM rooms WHERE room_type = 'air' ORDER BY room_number ASC")->fetchAll();
$all_fan = $pdo->query("SELECT * FROM rooms WHERE room_type = 'fan' ORDER BY room_number ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_room'])) {
    $room_number = $_POST['room_number'];
    $room_type = $_POST['room_type'] ?? 'fan';
    $base_rent = $_POST['base_rent'] ?? 0;
    $check = $pdo->prepare("SELECT * FROM rooms WHERE room_number = ?");
    $check->execute([$room_number]);
    if ($check->fetch()) {
        header("Location: manage_rooms.php?msg=error&text=เลขห้องนี้มีอยู่ในระบบแล้ว");
    } else {
        $pdo->prepare("INSERT INTO rooms (room_number, room_type, base_rent, status) VALUES (?, ?, ?, 'available')")->execute([$room_number, $room_type, $base_rent]);
        header("Location: manage_rooms.php?msg=added");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการห้องพัก | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        #editModal, #viewAllModal { display: none !important; }
        #editModal.active, #viewAllModal.active { display: flex !important; }
        .modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        
        /* Custom Scrollbar สำหรับ Modal */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="container mx-auto px-4 py-10">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-hotel text-emerald-600"></i> ผังห้องพักแยกประเภท
                </h1>
                <p class="text-slate-500 font-medium">จัดการข้อมูลแยกตามประเภทห้อง แอร์ และ พัดลม</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-xl h-fit sticky top-10">
                <h2 class="text-lg font-bold mb-5 text-slate-700 italic">+ เพิ่มห้อง</h2>
                <form method="POST" action="manage_rooms.php" class="space-y-4">
                    <input type="text" name="room_number" placeholder="เลขห้อง" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-sm outline-none">
                    <select name="room_type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-sm outline-none">
                        <option value="fan">🍃 ห้องพัดลม</option>
                        <option value="air">❄️ ห้องแอร์</option>
                    </select>
                    <input type="number" name="base_rent" placeholder="ราคา" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-sm outline-none">
                    <button type="submit" name="add_room" class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-emerald-700 transition">บันทึก</button>
                </form>
            </div>

            <div class="lg:col-span-3 space-y-10">
                
                <div class="glass-card rounded-[2.5rem] shadow-lg overflow-hidden border border-white">
                    <div class="bg-blue-600 px-8 py-4 flex justify-between items-center text-white">
                        <h3 class="font-bold italic flex items-center gap-2"><i class="fa-solid fa-snowflake"></i> AIR CONDITIONING</h3>
                        <div class="flex items-center gap-2">
                            <button onclick='openAllModal("air")' class="bg-blue-800/50 hover:bg-blue-900 px-3 py-1 rounded-lg text-[10px] font-bold transition">ดูทั้งหมด</button>
                            <div class="flex gap-1">
                                <?php for($i=1; $i <= ceil($total_air/$limit); $i++): ?>
                                    <a href="?p_air=<?= $i ?>&p_fan=<?= $p_fan ?>" class="w-6 h-6 flex items-center justify-center rounded text-[10px] font-bold <?= $i==$p_air ? 'bg-white text-blue-600' : 'bg-blue-500' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($air_rooms as $room): ?>
                            <tr class="hover:bg-blue-50/30 transition duration-300">
                                <td class="px-6 py-4 font-black text-blue-700 text-xl italic"><?= $room['room_number'] ?></td>
                                <td class="px-6 py-4 font-bold text-slate-600 text-sm">฿<?= number_format($room['base_rent']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider <?= $room['status'] == 'available' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400' ?>"><?= $room['status'] == 'available' ? 'ว่าง' : 'ไม่ว่าง' ?></span>
                                </td>
                                <td class="px-6 py-4 flex justify-center gap-3">
                                    <button onclick='openEditModal(<?= json_encode($room) ?>)' class="text-amber-500"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button onclick="confirmDelete(<?= $room['room_id'] ?>, '<?= $room['room_number'] ?>')" class="text-red-400"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="glass-card rounded-[2.5rem] shadow-lg overflow-hidden border border-white">
                    <div class="bg-orange-500 px-8 py-4 flex justify-between items-center text-white">
                        <h3 class="font-bold italic flex items-center gap-2"><i class="fa-solid fa-wind"></i> NATURAL FAN</h3>
                        <div class="flex items-center gap-2">
                            <button onclick='openAllModal("fan")' class="bg-orange-700/50 hover:bg-orange-800 px-3 py-1 rounded-lg text-[10px] font-bold transition">ดูทั้งหมด</button>
                            <div class="flex gap-1">
                                <?php for($i=1; $i <= ceil($total_fan/$limit); $i++): ?>
                                    <a href="?p_air=<?= $p_air ?>&p_fan=<?= $i ?>" class="w-6 h-6 flex items-center justify-center rounded text-[10px] font-bold <?= $i==$p_fan ? 'bg-white text-orange-600' : 'bg-orange-400' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($fan_rooms as $room): ?>
                            <tr class="hover:bg-orange-50/30 transition duration-300">
                                <td class="px-6 py-4 font-black text-orange-700 text-xl italic"><?= $room['room_number'] ?></td>
                                <td class="px-6 py-4 font-bold text-slate-600 text-sm">฿<?= number_format($room['base_rent']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider <?= $room['status'] == 'available' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400' ?>"><?= $room['status'] == 'available' ? 'ว่าง' : 'ไม่ว่าง' ?></span>
                                </td>
                                <td class="px-6 py-4 flex justify-center gap-3">
                                    <button onclick='openEditModal(<?= json_encode($room) ?>)' class="text-amber-500"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button onclick="confirmDelete(<?= $room['room_id'] ?>, '<?= $room['room_number'] ?>')" class="text-red-400"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="viewAllModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl">
            <div id="modalHeader" class="px-8 py-5 text-white font-bold italic flex justify-between items-center shrink-0">
                <span id="modalTitle"></span>
                <button onclick="closeAllModal()" class="text-white/70 hover:text-white text-xl">✕</button>
            </div>
            <div class="overflow-y-auto p-8 custom-scrollbar flex-grow">
                <div id="modalContent"></div>
            </div>
            <div class="p-4 bg-slate-50 border-t flex justify-end shrink-0">
                <button onclick="closeAllModal()" class="px-6 py-2 bg-slate-200 text-slate-600 rounded-xl font-bold text-xs hover:bg-slate-300 transition">ปิด</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] p-10 max-w-md w-full shadow-2xl relative">
            <h2 class="text-2xl font-black mb-8 text-slate-800 italic flex items-center gap-3">
                <i class="fa-solid fa-pen-to-square text-amber-500"></i> EDIT <span id="modal_room_number" class="text-amber-600 underline"></span>
            </h2>
            <form method="POST" action="manage_rooms.php" class="space-y-6">
                <input type="hidden" name="room_id" id="modal_room_id">
                <select name="room_type" id="modal_room_type" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold outline-none cursor-pointer">
                    <option value="fan">ห้องพัดลม</option>
                    <option value="air">ห้องแอร์</option>
                </select>
                <input type="number" name="base_rent" id="modal_base_rent" required class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold outline-none">
                <div class="flex gap-4">
                    <button type="button" onclick="closeModal()" class="flex-1 py-4 bg-slate-100 rounded-2xl font-black uppercase text-xs">Cancel</button>
                    <button type="submit" name="edit_room" class="flex-1 py-4 bg-amber-500 text-white rounded-2xl font-black shadow-lg uppercase text-xs">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const allAirRooms = <?= json_encode($all_air) ?>;
        const allFanRooms = <?= json_encode($all_fan) ?>;

        function openAllModal(type) {
            const modal = document.getElementById('viewAllModal');
            const title = document.getElementById('modalTitle');
            const header = document.getElementById('modalHeader');
            const content = document.getElementById('modalContent');
            const data = (type === 'air') ? allAirRooms : allFanRooms;
            
            title.innerText = (type === 'air') ? "❄️ รายการห้องแอร์ทั้งหมด" : "🍃 รายการห้องพัดลมทั้งหมด";
            header.className = (type === 'air') ? "px-8 py-5 bg-blue-600 text-white font-bold italic flex justify-between items-center shrink-0" : "px-8 py-5 bg-orange-500 text-white font-bold italic flex justify-between items-center shrink-0";
            
            let html = `<table class="w-full text-left border-separate border-spacing-y-2">
                <thead><tr class="text-[10px] text-slate-400 uppercase font-black tracking-widest">
                <th class="px-4 pb-2">ห้อง</th><th class="px-4 pb-2">ราคา</th><th class="px-4 pb-2 text-center">สถานะ</th></tr></thead><tbody>`;
            
            data.forEach(r => {
                html += `<tr class="bg-slate-50/80 hover:bg-slate-100 transition-colors">
                    <td class="px-4 py-3 rounded-l-2xl font-black text-slate-700 text-lg italic">${r.room_number}</td>
                    <td class="px-4 py-3 font-bold text-slate-500">฿${Number(r.base_rent).toLocaleString()}</td>
                    <td class="px-4 py-3 rounded-r-2xl text-center">
                        <span class="text-[10px] font-black uppercase ${r.status === 'available' ? 'text-emerald-500' : 'text-slate-300'}">
                            ● ${r.status === 'available' ? 'ว่าง' : 'ไม่ว่าง'}
                        </span>
                    </td></tr>`;
            });
            html += `</tbody></table>`;
            
            content.innerHTML = html;
            modal.classList.add('active');
        }

        function closeAllModal() { document.getElementById('viewAllModal').classList.remove('active'); }

        function openEditModal(room) {
            document.getElementById('modal_room_id').value = room.room_id;
            document.getElementById('modal_room_number').innerText = room.room_number;
            document.getElementById('modal_room_type').value = room.room_type;
            document.getElementById('modal_base_rent').value = room.base_rent;
            document.getElementById('editModal').classList.add('active');
        }
        function closeModal() { document.getElementById('editModal').classList.remove('active'); }

        function confirmDelete(id, number) {
            Swal.fire({
                title: `ลบห้อง ${number}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ลบเลย',
                customClass: { popup: 'rounded-[2rem]' }
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_rooms.php?delete_id=${id}`; });
        }
    </script>
</body>
</html>