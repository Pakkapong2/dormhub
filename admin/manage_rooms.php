<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// --- Logic แบ่งหน้า ---
$limit = 5;
$p = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($p - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_number ASC LIMIT $limit OFFSET $offset");
$stmt->execute();
$rooms = $stmt->fetchAll();

$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_number ASC")->fetchAll();


function getRoomImage($imageName) {
    $noImageBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";
    
    if (empty($imageName)) {
        return $noImageBase64;
    }

    $filePath = "../uploads/" . $imageName;
    if (!file_exists($filePath)) {
        return $noImageBase64;
    }

    return $filePath;
}

if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM rooms WHERE room_id = ?")->execute([$_GET['delete_id']]);
    header("Location: manage_rooms.php?msg=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_room'])) {
    $room_id = $_POST['room_id'];
    $base_rent = $_POST['base_rent'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    $amenities = $_POST['amenities'];
    
    $image_name = $_POST['existing_image']; 
    if (!empty($_FILES['room_image']['name'])) {
        $ext = pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION);
        $image_name = "room_" . time() . "." . $ext;
        
        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
        move_uploaded_file($_FILES['room_image']['tmp_name'], "../uploads/" . $image_name);
    }

    $stmt = $pdo->prepare("UPDATE rooms SET base_rent = ?, status = ?, description = ?, amenities = ?, room_image = ? WHERE room_id = ?");
    $stmt->execute([$base_rent, $status, $description, $amenities, $image_name, $room_id]);
    header("Location: manage_rooms.php?p=$p&msg=updated");
    exit();
}

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
    <div class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-hotel text-emerald-600"></i> จัดการผังห้องพัก
                </h1>
                <p class="text-slate-500 font-medium ml-10">เพิ่มและจัดการข้อมูลห้องพัก</p>
            </div>
            <a href="../index.php" class="flex items-center justify-center gap-2 px-6 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl hover:bg-slate-50 hover:text-emerald-600 transition-all shadow-sm group">
                <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
                กลับสู่หน้าหลัก
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-xl h-fit sticky top-10">
                <h2 class="text-lg font-bold mb-5 text-slate-700 italic">+ เพิ่มห้องใหม่</h2>
                <form method="POST" class="space-y-4">
                    <input type="text" name="room_number" placeholder="เลขห้อง (เช่น 101)" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-emerald-500">
                    <input type="number" name="base_rent" placeholder="ราคาเช่า" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-emerald-500">
                    <button type="submit" name="add_room" class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-emerald-700 transition shadow-lg">บันทึก</button>
                </form>
            </div>

            <div class="lg:col-span-3">
                <div class="glass-card rounded-[2.5rem] shadow-lg overflow-hidden border border-white">
                    <div class="bg-slate-800 px-8 py-4 flex justify-between items-center text-white">
                        <h3 class="font-bold italic uppercase tracking-widest text-xs">Room Inventory</h3>
                        <button onclick='openAllModal()' class="bg-white/10 hover:bg-white/20 px-4 py-1.5 rounded-full text-[10px] font-bold transition">VIEW ALL GRID</button>
                    </div>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-slate-400 uppercase tracking-widest bg-slate-50/50 border-b">
                                <th class="px-8 py-4">ห้อง / รูปภาพ</th>
                                <th class="px-8 py-4">ราคาค่าเช่า</th>
                                <th class="px-8 py-4 text-center">สถานะ</th>
                                <th class="px-8 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($rooms as $room): ?>
                            <tr class="hover:bg-slate-50/80 transition-all duration-300">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <img src="<?= getRoomImage($room['room_image']) ?>" 
                                             class="w-12 h-12 rounded-xl object-cover shadow-sm bg-slate-200">
                                        <div>
                                            <div class="font-black text-slate-700 text-xl italic leading-none"><?= htmlspecialchars($room['room_number']) ?></div>
                                            <div class="text-[10px] text-slate-400 font-bold mt-1"><?= htmlspecialchars($room['amenities'] ?: 'ไม่มีข้อมูล') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 font-bold text-slate-600">฿<?= number_format($room['base_rent']) ?></td>
                                <td class="px-8 py-5 text-center">
                                    <?php 
                                        $label_css = "bg-emerald-100 text-emerald-600"; $label_text = "ว่าง";
                                        if($room['status'] == 'occupied') { $label_css = "bg-rose-100 text-rose-600"; $label_text = "ไม่ว่าง"; }
                                        if($room['status'] == 'maintenance') { $label_css = "bg-amber-100 text-amber-600"; $label_text = "ซ่อมแซม"; }
                                    ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase inline-block <?= $label_css ?>">
                                        <?= $label_text ?>
                                    </span>
                                </td>
                                <td class="px-8 py-5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick='openEditModal(<?= json_encode($room) ?>)' class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 hover:bg-emerald-500 hover:text-white transition-all"><i class="fa-solid fa-gear"></i></button>
                                        <button onclick="confirmDelete(<?= $room['room_id'] ?>, '<?= htmlspecialchars($room['room_number']) ?>')" class="w-10 h-10 rounded-xl bg-slate-100 text-rose-500 hover:bg-rose-500 hover:text-white transition-all"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="p-4 bg-slate-50 flex justify-center gap-2">
                        <?php for($i=1; $i <= ceil($total_rooms/$limit); $i++): ?>
                            <a href="?p=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold <?= $i==$p ? 'bg-slate-800 text-white' : 'bg-white text-slate-400 border border-slate-200' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl relative">
            <div class="p-8 bg-slate-800 text-white flex justify-between items-center shrink-0">
                <h2 class="text-xl font-black italic">ROOM SETTINGS: <span id="modal_room_number" class="text-emerald-400"></span></h2>
                <button onclick="closeModal()" class="hover:rotate-90 transition-transform">✕</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="flex-grow overflow-y-auto p-8 space-y-6 custom-scrollbar">
                <input type="hidden" name="room_id" id="modal_room_id">
                <input type="hidden" name="edit_room" value="1">
                <input type="hidden" name="existing_image" id="modal_existing_image">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">รูปภาพห้องพัก</label>
                        <div class="w-full h-48 bg-slate-100 rounded-3xl overflow-hidden border-2 border-dashed border-slate-300 flex items-center justify-center relative group">
                            <img id="preview_image" src="" class="absolute inset-0 w-full h-full object-cover">
                            <div class="z-10 bg-white/80 px-4 py-2 rounded-full text-xs font-bold opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">คลิกเพื่อเปลี่ยนรูป</div>
                            <input type="file" name="room_image" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile(this)">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">ราคาค่าเช่า/เดือน</label>
                            <input type="number" name="base_rent" id="modal_base_rent" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">สถานะห้อง</label>
                            <select name="status" id="modal_status" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="available">ว่าง (Available)</option>
                                <option value="occupied">ไม่ว่าง (Occupied)</option>
                                <option value="maintenance">ซ่อมแซม (Maintenance)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">สิ่งอำนวยความสะดวก</label>
                        <input type="text" name="amenities" id="modal_amenities" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">รายละเอียดห้อง</label>
                        <textarea name="description" id="modal_description" rows="3" class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-emerald-500 outline-none"></textarea>
                    </div>
                </div>

                <div class="flex gap-4 pt-4 shrink-0">
                    <button type="submit" class="w-full py-4 bg-emerald-600 text-white rounded-2xl font-black shadow-lg hover:bg-emerald-700 transition uppercase tracking-widest">บันทึกข้อมูลทั้งหมด</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewAllModal" class="modal items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl">
            <div class="px-8 py-6 border-b flex justify-between items-center">
                <h3 class="font-black italic text-xl">DORM FLOOR PLAN</h3>
                <button onclick="closeAllModal()" class="text-slate-400 hover:text-slate-900 text-xl">✕</button>
            </div>
            <div class="overflow-y-auto p-8 flex-grow custom-scrollbar bg-slate-50">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6">
                    <?php foreach($all_rooms as $r): 
                        $box_css = "bg-white border-2 border-emerald-500 shadow-[0_4px_0_0_#10b981]";
                        $text_css = "text-emerald-600";
                        if($r['status'] == 'occupied') { $box_css = "bg-slate-100 border-slate-300 shadow-none grayscale"; $text_css = "text-slate-400"; }
                        if($r['status'] == 'maintenance') { $box_css = "bg-white border-amber-400 shadow-[0_4px_0_0_#fbbf24]"; $text_css = "text-amber-600"; }
                    ?>
                    <div class="p-4 rounded-3xl text-center transition-all <?= $box_css ?>">
                        <img src="<?= getRoomImage($r['room_image']) ?>" 
                             class="w-10 h-10 rounded-full mx-auto mb-2 object-cover border border-slate-200">
                        <div class="text-lg font-black italic <?= $text_css ?>"><?= htmlspecialchars($r['room_number']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const noImageBase64 = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAACACAMAAACe28YnAAAAA1BMVEWzsrK76Y6RAAAAR0lEQVR4nO3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAO8GxYgAAbXv9u4AAAAASUVORK5CYII=";

        // ระบบแจ้งเตือน
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg) {
            let title = '', icon = 'success';
            if (msg === 'added') title = 'เพิ่มห้องสำเร็จ!';
            if (msg === 'updated') title = 'บันทึกข้อมูลสำเร็จ!';
            if (msg === 'deleted') { title = 'ลบห้องพักแล้ว!'; icon = 'info'; }
            if (title) {
                Swal.fire({ title, icon, timer: 1500, showConfirmButton: false });
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }

        function openAllModal() { document.getElementById('viewAllModal').classList.add('active'); }
        function closeAllModal() { document.getElementById('viewAllModal').classList.remove('active'); }

        function openEditModal(room) {
            document.getElementById('modal_room_id').value = room.room_id;
            document.getElementById('modal_room_number').innerText = room.room_number;
            document.getElementById('modal_base_rent').value = room.base_rent;
            document.getElementById('modal_status').value = room.status;
            document.getElementById('modal_amenities').value = room.amenities || '';
            document.getElementById('modal_description').value = room.description || '';
            document.getElementById('modal_existing_image').value = room.room_image || '';
            
            // ใช้ Base64 แทน placeholder URL
            const imgPath = room.room_image ? "../uploads/" + room.room_image : noImageBase64;
            const previewImg = document.getElementById('preview_image');
            previewImg.src = imgPath;
            previewImg.onerror = function() { this.src = noImageBase64; };
            
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
                text: "ข้อมูลห้องนี้จะหายไปจากระบบถาวร",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'ใช่, ลบเลย'
            }).then((result) => { if (result.isConfirmed) window.location.href = `manage_rooms.php?delete_id=${id}`; });
        }
    </script>
</body>
</html>