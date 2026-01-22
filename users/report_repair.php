<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if the user is logged in and has the 'user' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's room_id for submitting repair tickets
$stmt_room = $pdo->prepare("SELECT room_id FROM users WHERE user_id = ?");
$stmt_room->execute([$user_id]);
$user_room_id = $stmt_room->fetchColumn();

if (!$user_room_id) {
    // User has no room assigned, they shouldn't be here to report repair for a room
    // This scenario should ideally be prevented by role/room checks earlier, but good to have a fallback.
    header("Location: " . BASE_URL . "users/index.php?error=no_room");
    exit;
}

// --- Logic: Handle new repair ticket submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_repair'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $repair_image = null;

    // Handle image upload
    if (isset($_FILES['repair_image']) && $_FILES['repair_image']['error'] == 0) {
        $target_dir = __DIR__ . "/../uploads/maintenance/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES["repair_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = "repair_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["repair_image"]["tmp_name"], $target_file)) {
            $repair_image = $new_filename;
        } else {
            // Handle upload error (optional)
            // echo "Error uploading image.";
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO maintenance (user_id, room_id, title, description, repair_image, status, reported_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $user_room_id, $title, $description, $repair_image]);
        header("Location: " . BASE_URL . "users/report_repair.php?msg=success");
        exit;
    } catch (PDOException $e) {
        // Handle DB error
        die("Error submitting repair ticket: " . $e->getMessage());
    }
}

// --- Fetch user's past repair tickets ---
try {
    $stmt = $pdo->prepare("
        SELECT m.*, r.room_number
        FROM maintenance m
        JOIN rooms r ON m.room_id = r.room_id
        WHERE m.user_id = ?
        ORDER BY m.reported_at DESC
    ");
    $stmt->execute([$user_id]);
    $repair_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching repair tickets: " . $e->getMessage());
}

$page_title = "แจ้งซ่อม";
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
            <i class="fa-solid fa-wrench text-blue-600"></i> <?= htmlspecialchars($page_title) ?>
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- New Repair Request Form -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
                <h2 class="text-xl font-black text-slate-800 italic uppercase mb-6">แจ้งซ่อมใหม่</h2>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="submit_repair" value="1">
                    <div>
                        <label for="title" class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">หัวข้อปัญหา</label>
                        <input type="text" id="title" name="title" required placeholder="เช่น แอร์ไม่เย็น, ท่อน้ำรั่ว"
                               class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold outline-none focus:ring-4 focus:ring-blue-500/10 transition-all">
                    </div>
                    <div>
                        <label for="description" class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">รายละเอียดปัญหา</label>
                        <textarea id="description" name="description" rows="4" placeholder="โปรดอธิบายปัญหาให้ชัดเจนที่สุด"
                                  class="w-full mt-2 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold outline-none focus:ring-4 focus:ring-blue-500/10 transition-all"></textarea>
                    </div>
                    <div>
                        <label for="repair_image" class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">รูปภาพประกอบ (ถ้ามี)</label>
                        <input type="file" id="repair_image" name="repair_image" accept="image/*"
                               class="w-full mt-2 bg-slate-50 border border-slate-100 rounded-2xl p-4 font-bold outline-none focus:ring-4 focus:ring-blue-500/10 transition-all">
                    </div>
                    <button type="submit" class="w-full py-5 bg-blue-600 text-white rounded-[2rem] font-black shadow-xl hover:bg-slate-900 transition-all uppercase tracking-widest italic">
                        ส่งแจ้งซ่อม <i class="fa-solid fa-paper-plane ml-2"></i>
                    </button>
                </form>
            </div>

            <!-- User's Past Repair Tickets -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
                <h2 class="text-xl font-black text-slate-800 italic uppercase mb-6">ประวัติการแจ้งซ่อมของคุณ</h2>
                <?php if (count($repair_tickets) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($repair_tickets as $ticket): ?>
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-bold text-slate-800"><?= htmlspecialchars($ticket['title']) ?></h3>
                                    <?php
                                        $s = [
                                            'pending' => ['bg'=>'bg-rose-50', 'text'=>'text-rose-600', 'label'=>'รอดำเนินการ'],
                                            'in_progress' => ['bg'=>'bg-blue-50', 'text'=>'text-blue-600', 'label'=>'กำลังดำเนินการ'],
                                            'fixed' => ['bg'=>'bg-green-50', 'text'=>'text-green-600', 'label'=>'ซ่อมเสร็จแล้ว'],
                                        ][$ticket['status']] ?? ['bg'=>'bg-slate-100', 'text'=>'text-slate-500', 'label'=>'ไม่ทราบสถานะ'];
                                    ?>
                                    <span class="<?= $s['bg'] ?> <?= $s['text'] ?> px-3 py-1 rounded-full text-[9px] font-black uppercase">
                                        <?= $s['label'] ?>
                                    </span>
                                </div>
                                <p class="text-slate-500 text-sm mb-2"><?= htmlspecialchars($ticket['description']) ?></p>
                                <?php if ($ticket['repair_image']): ?>
                                    <button onclick="Swal.fire({title: 'รูปภาพปัญหา', imageUrl: '<?= BASE_URL ?>uploads/maintenance/<?= htmlspecialchars($ticket['repair_image']) ?>', imageAlt: 'Repair Image', confirmButtonColor: '#0f172a', borderRadius: '2rem'})" class="text-[10px] text-blue-500 font-bold uppercase hover:underline">
                                        ดูรูปภาพ <i class="fa-solid fa-image ml-1"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($ticket['admin_note']): ?>
                                    <p class="text-[10px] font-bold text-green-600 bg-green-50 w-fit px-2 py-1 rounded-lg border border-green-100 mt-2">
                                        หมายเหตุแอดมิน: <?= htmlspecialchars($ticket['admin_note']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-[10px] text-slate-400 mt-2">แจ้งเมื่อ: <?= date('d M Y H:i', strtotime($ticket['reported_at'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-slate-500 font-medium py-10">
                        คุณยังไม่มีรายการแจ้งซ่อม
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({
                title: 'ส่งแจ้งซ่อมสำเร็จ!',
                text: 'ระบบได้รับเรื่องแจ้งซ่อมของคุณแล้ว',
                icon: 'success',
                confirmButtonColor: '#0f172a',
                borderRadius: '2rem'
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (urlParams.get('error') === 'no_room') {
             Swal.fire({
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่พบห้องพักของคุณ ไม่สามารถแจ้งซ่อมได้',
                icon: 'error',
                confirmButtonColor: '#0f172a',
                borderRadius: '2rem'
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>