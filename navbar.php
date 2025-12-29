<?php
// --- 1. คำนวณ Base URL อัตโนมัติ (แก้ไขใหม่ให้แม่นยำขึ้น) ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// ดึงชื่อโฟลเดอร์โปรเจกต์ (เช่น /dormhub/)
$project_folder = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'))[0];
$auto_base_url = $protocol . "://" . $host . "/" . $project_folder . "/";

// --- 2. ดึงข้อมูล User และจัดการเรื่อง Path ของไฟล์ Config ---
if (!isset($user) && isset($_SESSION['user_id'])) {
    // ใช้ DOCUMENT_ROOT เพื่อเริ่มหาจาก C:/xampp/htdocs/ เสมอ
    $project_name = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'))[0];
    $config_file = $_SERVER['DOCUMENT_ROOT'] . '/' . $project_name . '/config/db_connect.php';
    
    if (file_exists($config_file)) {
        require_once $config_file;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
// ข้อมูลสำหรับแสดงผล
$role = $user['role'] ?? 'user';
$profile_img = $user['line_picture_url'] ?? null;
$fullname = $user['fullname'] ?? ($user['username'] ?? 'Guest');
?>

<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Anuphan', sans-serif; }
    /* ดีไซน์เดิม: สีเขียวแบบ Blur */
    .nav-blur { backdrop-filter: blur(12px); background-color: rgba(22, 163, 74, 0.85); }
    
    /* Animation สำหรับ Dropdown */
    #dropdownMenu { transition: all 0.2s ease-out; transform-origin: top right; }
    #dropdownMenu.hidden { 
        opacity: 0; 
        transform: translateY(-10px) scale(0.95); 
        pointer-events: none; 
        display: block !important; 
    }
    @media print { .no-print { display: none !important; } }
</style>

<nav class="nav-blur text-white shadow-lg sticky top-0 z-[100] border-b border-white/10 no-print">
    <div class="container mx-auto px-4 md:px-6 py-3 flex justify-between items-center">
        
        <a href="<?= $auto_base_url ?>index.php" class="flex items-center gap-2 group">
            <div class="bg-white p-2 rounded-xl group-hover:rotate-12 transition-transform shadow-md">
                <span class="text-xl">🏠</span>
            </div>
            <span class="text-xl font-bold tracking-tighter">DORM<span class="font-light text-green-200 uppercase">hub</span></span>
        </a>

        <div class="relative">
            <button id="profileButton" 
                    class="flex items-center gap-2 md:gap-3 bg-white/10 hover:bg-white/20 p-1 md:p-1.5 md:pr-4 rounded-full transition-all border border-white/20 shadow-inner"
                    onclick="toggleDropdown()">
                
                <?php if ($profile_img): ?>
                    <img src="<?= htmlspecialchars($profile_img) ?>" class="w-8 h-8 rounded-full object-cover border-2 border-white/30 shadow-sm">
                <?php else: ?>
                    <div class="w-8 h-8 bg-gradient-to-tr from-green-300 to-green-600 text-white rounded-full flex items-center justify-center text-sm font-bold shadow-sm">
                        <?= strtoupper(substr($fullname, 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-left hidden md:block">
                    <p class="text-[10px] uppercase opacity-70 font-bold tracking-widest">ยินดีต้อนรับ</p>
                    <p class="text-sm font-semibold leading-tight"><?= htmlspecialchars($fullname) ?></p>
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] opacity-50 ml-1"></i>
            </button>

            <div id="dropdownMenu" class="absolute right-0 mt-3 w-64 rounded-[2rem] shadow-2xl bg-white ring-1 ring-black/5 hidden overflow-hidden z-[110]">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100">
                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-[0.2em]">Account Role</p>
                    <p class="text-sm font-bold <?= $role === 'admin' ? 'text-blue-600' : 'text-green-600' ?>">
                        <?= $role === 'admin' ? '🛡️ ผู้ดูแลระบบ (Admin)' : '🏠 ผู้เช่าพัก (Tenant)' ?>
                    </p>
                </div>
                
                <div class="py-2 text-slate-600">
                    <?php if ($role === 'admin'): ?>
                        <a href="<?= $auto_base_url ?>admin/admin_dashboard.php" class="flex items-center gap-4 px-6 py-3 hover:bg-slate-50 transition group">
                            <i class="fa-solid fa-chart-line text-slate-400 group-hover:text-blue-600"></i>
                            <span class="text-sm font-bold">Dashboard</span>
                        </a>
                        <a href="<?= $auto_base_url ?>admin/manage_rooms.php" class="flex items-center gap-4 px-6 py-3 hover:bg-slate-50 transition group">
                            <i class="fa-solid fa-bed text-slate-400 group-hover:text-blue-600"></i>
                            <span class="text-sm font-bold">จัดการห้องพัก</span>
                        </a>
                        <a href="<?= $auto_base_url ?>admin/manage_bills.php" class="flex items-center gap-4 px-6 py-3 hover:bg-slate-50 transition group">
                            <i class="fa-solid fa-receipt text-slate-400 group-hover:text-blue-600"></i>
                            <span class="text-sm font-bold">บิลและรายได้</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= $auto_base_url ?>index.php" class="flex items-center gap-4 px-6 py-3 hover:bg-slate-50 transition group">
                            <i class="fa-solid fa-receipt text-slate-400 group-hover:text-green-600"></i>
                            <span class="text-sm font-bold">บิลของฉัน</span>
                        </a>
                        <a href="<?= $auto_base_url ?>users/payment_history.php" class="flex items-center gap-4 px-6 py-3 hover:bg-slate-50 transition group">
                            <i class="fa-solid fa-history text-slate-400 group-hover:text-green-600"></i>
                            <span class="text-sm font-bold">ประวัติการจ่ายเงิน</span>
                        </a>
                    <?php endif; ?>
                    
                    <div class="border-t border-slate-100 my-2 px-6 pt-2">
                        <a href="<?= $auto_base_url ?>logout.php" class="flex items-center gap-4 py-3 text-red-500 hover:text-red-700 transition group">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span class="text-sm font-black uppercase tracking-tighter">ออกจากระบบ</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    function toggleDropdown() {
        const menu = document.getElementById('dropdownMenu');
        menu.classList.toggle('hidden');
    }

    // ปิด dropdown เมื่อคลิกที่อื่น
    window.addEventListener('click', function(e) {
        const menu = document.getElementById('dropdownMenu');
        const button = document.getElementById('profileButton');
        if (menu && button && !button.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>