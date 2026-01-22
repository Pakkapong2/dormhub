<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. Logic การจัดการข้อมูล (Pagination & CRUD)
$limit = 5;
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($p - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_number ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rooms = $stmt->fetchAll();

$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_number ASC")->fetchAll();

function getRoomImage($imageName) {
    $noImageBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";
    if (empty($imageName)) return $noImageBase64;
    $filePath = "../uploads/" . $imageName;
    return file_exists(__DIR__ . "/../uploads/" . $imageName) ? $filePath : $noImageBase64;
}

// --- ลบห้อง ---
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM rooms WHERE room_id = ?")->execute([$_GET['delete_id']]);
    header("Location: manage_rooms.php?msg=deleted");
    exit();
}

// --- แก้ไขห้อง (ปรับปรุงให้รองรับสถานะ booked) ---
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

// --- เพิ่มห้อง ---
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
    <title>จัดการห้องพัก | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .modal { display: none !important; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="lg:ml-72 transition-all">
        <div class="container mx-auto px-4 py-10">
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3 italic uppercase tracking-tighter">
                        <i class="fa-solid fa-hotel text-green-600"></i> Room Inventory
                    </h1>
                    <p class="text-slate-500 font-medium">จัดการสถานะห้องพักและข้อมูลพื้นฐาน</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <div class="lg:col-span-1 bg-white p-8 rounded-[2.5rem] shadow-xl h-fit border border-slate-100">
                    <h2 class="text-xs font-black mb-6 text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> New Room
                    </h2>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="room_number" placeholder="เลขห้อง" required class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none focus:ring-2 focus:ring-green-500">
                        <input type="number" name="base_rent" placeholder="ค่าเช่ารายเดือน" required class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl font-black outline-none focus:ring-2 focus:ring-green-500">
                        <button type="submit" name="add_room" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black hover:bg-green-600 transition shadow-lg uppercase italic text-sm">Create Room</button>
                    </form>
                </div>

                <div class="lg:col-span-3">
                    <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden border border-slate-100">
                        <div class="bg-slate-900 px-10 py-6 flex justify-between items-center text-white">
                            <h3 class="font-black italic uppercase tracking-tighter text-lg">Room List</h3>
                            <button onclick="openAllModal()" class="bg-white/10 hover:bg-green-500 px-6 py-2 rounded-full text-[10px] font-black transition-all uppercase tracking-widest">Floor Plan View</button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="text-[10px] text-slate-400 uppercase tracking-widest bg-slate-50 border-b">
                                    <tr>
                                        <th class="px-10 py-5">Room Details</th>
                                        <th class="px-8 py-5">Monthly Rent</th>
                                        <th class="px-8 py-5 text-center">Current Status</th>
                                        <th class="px-10 py-5 text-right">Operations</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php foreach($rooms as $room): ?>
                                    <tr class="hover:bg-slate-50 transition-all group">
                                        <td class="px-10 py-6">
                                            <div class="flex items-center gap-4">
                                                <img src="<?= getRoomImage($room['room_image']) ?>" class="w-14 h-14 rounded-2xl object-cover shadow-sm border border-slate-200 group-hover:scale-110 transition-transform">
                                                <div class="font-black text-slate-800 text-2xl italic tracking-tighter"><?= $room['room_number'] ?></div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 font-black text-slate-700 italic text-lg">฿<?= number_format($room['base_rent']) ?></td>
                                        <td class="px-8 py-6 text-center">
                                            <?php 
                                                $s = $room['status'];
                                                // กำหนดสีตามสถานะ (เพิ่มสี indigo สำหรับ booked)
                                                $color = ($s == 'available') ? 'green' : 
                                                         (($s == 'booked') ? 'indigo' : 
                                                         (($s == 'occupied') ? 'blue' : 'amber'));
                                            ?>
                                            <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase border border-<?= $color ?>-200 bg-<?= $color ?>-50 text-<?= $color ?>-600 shadow-sm">
                                                <?= $s ?>
                                            </span>
                                        </td>
                                        <td class="px-10 py-6 text-right flex justify-end gap-2">
                                            <button onclick='openEditModal(<?= json_encode($room) ?>)' class="w-11 h-11 rounded-2xl bg-slate-50 text-slate-400 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center border border-slate-100 shadow-sm"><i class="fa-solid fa-pen-to-square"></i></button>
                                            <button onclick="confirmDelete(<?= $room['room_id'] ?>, '<?= $room['room_number'] ?>')" class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-400 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center border border-rose-100 shadow-sm"><i class="fa-solid fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[3.5rem] w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl border border-white/20">
            <div class="p-8 bg-slate-900 text-white flex justify-between items-center relative">
                <h2 class="text-2xl font-black italic uppercase tracking-tight">Modify Room: <span id="modal_room_number_span" class="text-green-400"></span></h2>
                <button onclick="closeModal()" class="w-10 h-10 rounded-full bg-white/10 hover:bg-rose-500 transition-all flex items-center justify-center font-black">✕</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="overflow-y-auto p-10 space-y-6 custom-scrollbar bg-slate-50/30">
                <input type="hidden" name="room_id" id="modal_room_id">
                <input type="hidden" name="edit_room" value="1">
                <input type="hidden" name="existing_image" id="modal_existing_image">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Room Visual</label>
                        <div class="relative h-48 bg-white rounded-[2rem] overflow-hidden border-2 border-dashed border-slate-200 flex items-center justify-center group shadow-inner">
                            <img id="preview_image" class="absolute inset-0 w-full h-full object-cover">
                            <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i class="fa-solid fa-camera text-white text-3xl"></i>
                            </div>
                            <input type="file" name="room_image" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile(this)">
                        </div>
                    </div>
                    <div class="space-y-5">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Base Price (THB)</label>
                            <input type="number" name="base_rent" id="modal_base_rent" class="w-full mt-2 px-6 py-4 bg-white border border-slate-100 rounded-2xl font-black text-slate-700 outline-none focus:ring-4 focus:ring-green-500/10 transition-all italic">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Room Status</label>
                            <select name="status" id="modal_status" class="w-full mt-2 px-6 py-4 bg-white border border-slate-100 rounded-2xl font-black text-slate-700 outline-none focus:ring-4 focus:ring-green-500/10 transition-all appearance-none italic">
                                <option value="available" class="text-green-600">Available</option>
                                <option value="booked" class="text-indigo-600">Booked (ติดจอง)</option>
                                <option value="occupied" class="text-blue-600">Occupied</option>
                                <option value="maintenance" class="text-amber-600">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Facilities / Amenities</label>
                    <input type="text" name="amenities" id="modal_amenities" placeholder="e.g. WiFi, Aircon, Bed" class="w-full px-6 py-4 bg-white border border-slate-100 rounded-2xl font-bold outline-none focus:ring-4 focus:ring-green-500/10 transition-all">
                </div>
                
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Room Description</label>
                    <textarea name="description" id="modal_description" rows="3" class="w-full px-6 py-4 bg-white border border-slate-100 rounded-2xl font-bold outline-none focus:ring-4 focus:ring-green-500/10 transition-all"></textarea>
                </div>
                
                <button type="submit" class="w-full py-5 bg-green-600 text-white rounded-[2rem] font-black hover:bg-slate-900 transition-all shadow-2xl shadow-green-200 uppercase tracking-widest italic">Apply Changes</button>
            </form>
        </div>
    </div>

    <div id="viewAllModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[3.5rem] w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl border border-white/20">
            <div class="px-10 py-8 bg-slate-900 text-white border-b flex justify-between items-center">
                <h3 class="font-black italic text-2xl uppercase tracking-tighter">DORM <span class="text-green-400">FLOOR PLAN</span></h3>
                <button onclick="closeAllModal()" class="w-10 h-10 rounded-full bg-white/10 hover:bg-rose-500 transition-all flex items-center justify-center font-black">✕</button>
            </div>
            <div class="overflow-y-auto p-12 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6 bg-slate-50">
                <?php foreach($all_rooms as $r): ?>
                <?php 
                    $color = ($r['status'] == 'available') ? 'green' : 
                             (($r['status'] == 'booked') ? 'indigo' : 
                             (($r['status'] == 'occupied') ? 'blue' : 'slate'));
                ?>
                <div class="p-8 rounded-[2.5rem] text-center border-2 transition-all hover:scale-105 bg-white shadow-sm
                    <?= "border-$color-500 text-$color-600 shadow-$color-100" ?>">
                    <div class="text-3xl font-black italic tracking-tighter"><?= $r['room_number'] ?></div>
                    <div class="text-[9px] font-black uppercase mt-1 opacity-60 tracking-widest"><?= $r['status'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(room) {
            document.getElementById('modal_room_id').value = room.room_id;
            document.getElementById('modal_room_number_span').innerText = room.room_number;
            document.getElementById('modal_base_rent').value = room.base_rent;
            document.getElementById('modal_status').value = room.status;
            document.getElementById('modal_amenities').value = room.amenities || '';
            document.getElementById('modal_description').value = room.description || '';
            document.getElementById('modal_existing_image').value = room.room_image || '';
            document.getElementById('preview_image').src = room.room_image ? "../uploads/" + room.room_image : "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";
            document.getElementById('editModal').classList.add('active');
        }
        function closeModal() { document.getElementById('editModal').classList.remove('active'); }
        function openAllModal() { document.getElementById('viewAllModal').classList.add('active'); }
        function closeAllModal() { document.getElementById('viewAllModal').classList.remove('active'); }
        
        function previewFile(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { document.getElementById('preview_image').src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function confirmDelete(id, number) {
            Swal.fire({
                title: `<span class="italic font-black uppercase">Delete Room ${number}?</span>`,
                text: "ข้อมูลห้องและประวัติทั้งหมดจะหายไปถาวร",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                cancelButtonColor: '#0f172a',
                confirmButtonText: 'Confirm Delete',
                cancelButtonText: 'Cancel',
                borderRadius: '2.5rem'
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_rooms.php?delete_id=${id}`; });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            Swal.fire({ 
                title: 'Operation Successful!', 
                icon: 'success', 
                timer: 2000, 
                showConfirmButton: false, 
                borderRadius: '2rem',
                confirmButtonColor: '#16a34a' 
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>