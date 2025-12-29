<?php
session_start();
// ใช้ Absolute Path ในการเชื่อมต่อ DB
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// การจัดการ Pagination
$limit = 5;
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($p - 1) * $limit;

// ดึงข้อมูลห้องพักแบบแบ่งหน้า
$stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_number ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rooms = $stmt->fetchAll();

$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_number ASC")->fetchAll();

// ฟังก์ชันดึงรูปภาพ (ปรับให้รองรับ Path ส่วนกลาง)
function getRoomImage($imageName) {
    $noImageBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";
    
    if (empty($imageName)) return $noImageBase64;

    $filePath = "../uploads/" . $imageName;
    return file_exists(__DIR__ . "/" . $filePath) ? $filePath : $noImageBase64;
}

// ลบห้องพัก
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM rooms WHERE room_id = ?")->execute([$_GET['delete_id']]);
    header("Location: manage_rooms.php?msg=deleted");
    exit();
}

// แก้ไขข้อมูลห้องพัก
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_room'])) {
    $room_id = $_POST['room_id'];
    $base_rent = $_POST['base_rent'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    $amenities = $_POST['amenities'];
    
    $image_name = $_POST['existing_image']; 
    if (!empty($_FILES['room_image']['name'])) {
        $ext = pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION);
        $image_name = "room_" . time() . "_" . uniqid() . "." . $ext;
        
        $target_dir = __DIR__ . "/../uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        move_uploaded_file($_FILES['room_image']['tmp_name'], $target_dir . $image_name);
    }

    $stmt = $pdo->prepare("UPDATE rooms SET base_rent = ?, status = ?, description = ?, amenities = ?, room_image = ? WHERE room_id = ?");
    $stmt->execute([$base_rent, $status, $description, $amenities, $image_name, $room_id]);
    header("Location: manage_rooms.php?p=$p&msg=updated");
    exit();
}

// เพิ่มห้องใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_room'])) {
    $room_number = $_POST['room_number'];
    $base_rent = $_POST['base_rent'] ?? 0;
    
    $stmt = $pdo->prepare("INSERT INTO rooms (room_number, base_rent, status, room_image) VALUES (?, ?, 'available', '')");
    $stmt->execute([$room_number, $base_rent]);
    
    header("Location: manage_rooms.php?msg=added");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการห้องพัก | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal { display: none !important; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">

    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3 italic uppercase tracking-tighter">
                    <i class="fa-solid fa-hotel text-green-600"></i> Room Inventory
                </h1>
                <p class="text-slate-500 font-medium ml-10">ระบบจัดการผังห้องพักและสถานะปัจจุบัน</p>
            </div>
            <a href="admin_dashboard.php" class="flex items-center gap-2 bg-white border border-slate-200 px-6 py-3 rounded-2xl font-black text-slate-600 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-all shadow-sm group">
                <i class="fa-solid fa-house-chimney transition-transform group-hover:-translate-y-0.5"></i>
                กลับสู่หน้าหลัก
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-1 glass-card p-8 rounded-[2.5rem] shadow-xl h-fit border-none">
                <h2 class="text-xs font-black mb-6 text-slate-400 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> เพิ่มห้องพักใหม่
                </h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 ml-2 uppercase">หมายเลขห้อง</label>
                        <input type="text" name="room_number" placeholder="101, 204..." required class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none focus:ring-2 focus:ring-green-500 transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 ml-2 uppercase">ค่าเช่าพื้นฐาน</label>
                        <input type="number" name="base_rent" placeholder="3500" required class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none focus:ring-2 focus:ring-green-500 transition-all">
                    </div>
                    <button type="submit" name="add_room" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black hover:bg-green-600 transition shadow-lg shadow-slate-200 uppercase tracking-tighter">สร้างห้องใหม่</button>
                </form>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden border border-slate-100">
                    <div class="bg-slate-900 px-10 py-6 flex justify-between items-center text-white">
                        <div class="flex items-center gap-3">
                            <span class="text-green-400"><i class="fa-solid fa-list-ul"></i></span>
                            <h3 class="font-black italic uppercase tracking-tighter text-lg">รายการห้องพักทั้งหมด</h3>
                        </div>
                        <button onclick='openAllModal()' class="bg-white/10 hover:bg-green-500 px-6 py-2 rounded-full text-[10px] font-black transition-all uppercase">Floor Plan View</button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50 border-b">
                                    <th class="px-10 py-5">Room / Detail</th>
                                    <th class="px-8 py-5">Price</th>
                                    <th class="px-8 py-5 text-center">Status</th>
                                    <th class="px-10 py-5 text-right">Settings</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach($rooms as $room): ?>
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="px-10 py-6">
                                        <div class="flex items-center gap-5">
                                            <div class="relative">
                                                <img src="<?= getRoomImage($room['room_image']) ?>" class="w-14 h-14 rounded-2xl object-cover shadow-md bg-slate-100 border border-slate-200">
                                                <div class="absolute -top-2 -left-2 bg-slate-900 text-white w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black italic">R</div>
                                            </div>
                                            <div>
                                                <div class="font-black text-slate-800 text-2xl italic leading-none"><?= htmlspecialchars($room['room_number']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider"><?= htmlspecialchars($room['amenities'] ?: 'ไม่มีข้อมูลอุปกรณ์') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 font-black text-slate-700 text-lg italic">฿<?= number_format($room['base_rent']) ?></td>
                                    <td class="px-8 py-6 text-center">
                                        <?php 
                                            $badge = "bg-green-100 text-green-600 border-green-200"; $text = "ว่าง";
                                            if($room['status'] == 'occupied') { $badge = "bg-slate-100 text-slate-400 border-slate-200"; $text = "ไม่ว่าง"; }
                                            if($room['status'] == 'maintenance') { $badge = "bg-amber-100 text-amber-600 border-amber-200"; $text = "ซ่อมแซม"; }
                                        ?>
                                        <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase border <?= $badge ?>">
                                            <?= $text ?>
                                        </span>
                                    </td>
                                    <td class="px-10 py-6 text-right">
                                        <div class="flex justify-end gap-3">
                                            <button onclick='openEditModal(<?= json_encode($room) ?>)' class="w-11 h-11 rounded-2xl bg-slate-50 text-slate-400 hover:bg-green-500 hover:text-white transition-all shadow-sm flex items-center justify-center border border-slate-100"><i class="fa-solid fa-sliders"></i></button>
                                            <button onclick="confirmDelete(<?= $room['room_id'] ?>, '<?= htmlspecialchars($room['room_number']) ?>')" class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm flex items-center justify-center border border-rose-100"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-center gap-2">
                        <?php for($i=1; $i <= ceil($total_rooms/$limit); $i++): ?>
                            <a href="?p=<?= $i ?>" class="px-4 py-2 rounded-xl text-xs font-black <?= $i==$p ? 'bg-slate-900 text-white' : 'bg-white text-slate-400 border border-slate-200 hover:bg-slate-100' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
            <div class="p-10 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.3em] text-green-400 font-black mb-1">Room Management</p>
                    <h2 class="text-3xl font-black italic">SETTINGS: <span id="modal_room_number_span"></span></h2>
                </div>
                <button onclick="closeModal()" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 transition-all flex items-center justify-center text-xl italic font-black">✕</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="flex-grow overflow-y-auto p-10 space-y-8 custom-scrollbar">
                <input type="hidden" name="room_id" id="modal_room_id">
                <input type="hidden" name="edit_room" value="1">
                <input type="hidden" name="existing_image" id="modal_existing_image">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div class="space-y-4">
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">ROOM PREVIEW</label>
                        <div class="w-full h-56 bg-slate-50 rounded-[2rem] overflow-hidden border-2 border-dashed border-slate-200 flex items-center justify-center relative group">
                            <img id="preview_image" src="" class="absolute inset-0 w-full h-full object-cover">
                            <div class="z-10 bg-slate-900/80 text-white px-6 py-2 rounded-full text-[10px] font-black opacity-0 group-hover:opacity-100 transition-opacity uppercase tracking-widest">Change Photo</div>
                            <input type="file" name="room_image" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile(this)">
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Monthly Rent (฿)</label>
                            <input type="number" name="base_rent" id="modal_base_rent" required class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black text-xl text-slate-700 outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Room Status</label>
                            <select name="status" id="modal_status" class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-black text-slate-700 outline-none focus:ring-2 focus:ring-green-500 appearance-none">
                                <option value="available">ว่าง (AVAILABLE)</option>
                                <option value="occupied">ไม่ว่าง (OCCUPIED)</option>
                                <option value="maintenance">ซ่อมแซม (MAINTENANCE)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Amenities</label>
                        <input type="text" name="amenities" id="modal_amenities" placeholder="แอร์, ตู้เย็น, เครื่องทำน้ำอุ่น..." class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Additional Notes</label>
                        <textarea name="description" id="modal_description" rows="3" class="w-full mt-2 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                </div>

                <button type="submit" class="w-full py-5 bg-green-600 text-white rounded-[2rem] font-black shadow-xl shadow-green-100 hover:bg-slate-900 transition-all uppercase tracking-widest">Update Room Settings</button>
            </form>
        </div>
    </div>

    <div id="viewAllModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[3.5rem] w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl border-none">
            <div class="px-10 py-8 bg-slate-50 border-b flex justify-between items-center">
                <h3 class="font-black italic text-2xl uppercase tracking-tighter text-slate-800">Dorm Floor Plan</h3>
                <button onclick="closeAllModal()" class="w-10 h-10 rounded-full hover:bg-slate-200 transition text-slate-400 italic font-black">✕</button>
            </div>
            <div class="overflow-y-auto p-10 flex-grow custom-scrollbar bg-slate-50/50">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6">
                    <?php foreach($all_rooms as $r): 
                        $status_box = "bg-white border-green-500 text-green-600 shadow-lg shadow-green-100";
                        if($r['status'] == 'occupied') { $status_box = "bg-slate-100 border-slate-200 text-slate-300 grayscale"; }
                        if($r['status'] == 'maintenance') { $status_box = "bg-white border-amber-400 text-amber-500 shadow-lg shadow-amber-50"; }
                    ?>
                    <div class="p-6 rounded-[2.5rem] text-center transition-all border-2 <?= $status_box ?>">
                        <div class="text-3xl font-black italic"><?= htmlspecialchars($r['room_number']) ?></div>
                        <div class="text-[8px] font-black uppercase tracking-[0.2em] mt-1 opacity-60"><?= $r['status'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg) {
            let title = '', icon = 'success';
            if (msg === 'added') title = 'เพิ่มห้องสำเร็จ!';
            if (msg === 'updated') title = 'บันทึกข้อมูลเรียบร้อย!';
            if (msg === 'deleted') { title = 'ลบห้องพักแล้ว!'; icon = 'info'; }
            if (title) {
                Swal.fire({ title, icon, timer: 2000, showConfirmButton: false, borderRadius: '2rem' });
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        function openAllModal() { document.getElementById('viewAllModal').classList.add('active'); }
        function closeAllModal() { document.getElementById('viewAllModal').classList.remove('active'); }

        function openEditModal(room) {
            document.getElementById('modal_room_id').value = room.room_id;
            document.getElementById('modal_room_number_span').innerText = room.room_number;
            document.getElementById('modal_base_rent').value = room.base_rent;
            document.getElementById('modal_status').value = room.status;
            document.getElementById('modal_amenities').value = room.amenities || '';
            document.getElementById('modal_description').value = room.description || '';
            document.getElementById('modal_existing_image').value = room.room_image || '';
            const imgPath = room.room_image ? "../uploads/" + room.room_image : "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";
            document.getElementById('preview_image').src = imgPath;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() { document.getElementById('editModal').classList.remove('active'); }

        function previewFile(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { document.getElementById('preview_image').src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function confirmDelete(id, number) {
            Swal.fire({
                title: `ลบห้อง ${number}?`,
                text: "ข้อมูลห้องและประวัติการเช่าที่เกี่ยวข้องอาจหายไป",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'ยืนยันการลบ',
                cancelButtonText: 'ยกเลิก',
                borderRadius: '2rem'
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_rooms.php?delete_id=${id}`; });
        }
    </script>
</body>
</html>