<?php
require_once __DIR__ . '/config/app_config.php'; // Include the new config file
require_once __DIR__ . '/config/db_connect.php'; // Ensure db_connect is included after session starts and BASE_URL is defined

// The session_start() call is now handled in app_config.php
// The auto_base_url calculation is now handled in app_config.php and stored in BASE_URL constant

$sb_user = null;
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    // db_connect.php is already included by app_config.php or is expected to be
    // So we can directly use $pdo
    $stmt_sb = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt_sb->execute([$_SESSION['user_id']]);
    $sb_user = $stmt_sb->fetch(PDO::FETCH_ASSOC);
}

$sb_role      = $sb_user['role'] ?? ($_SESSION['role'] ?? 'guest');
$sb_fullname  = $sb_user['fullname'] ?? ($_SESSION['fullname'] ?? 'Guest'); // Use $_SESSION['fullname'] instead of $_SESSION['name']
$sb_profile   = $sb_user['line_picture_url'] ?? ($_SESSION['picture'] ?? null);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body { font-family: 'Anuphan', sans-serif; background-color: #f8fafc; }
    .sidebar-blur { backdrop-filter: blur(16px); background-color: rgba(22, 163, 74, 0.95); }
    #sidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .nav-link { 
        transition: all 0.2s; 
        border-radius: 1.25rem; 
        margin-bottom: 0.35rem; 
        color: rgba(255,255,255,0.8); 
        text-decoration: none; 
        display: flex; 
        align-items: center; 
        gap: 0.85rem; 
        padding: 0.85rem 1.25rem;
        font-size: 0.95rem;
    }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(4px); color: white; }
    .nav-link.active { 
        background-color: white !important; 
        color: #16a34a !important; 
        font-weight: 700; 
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    @media (max-width: 1024px) { #sidebar.closed { transform: translateX(-100%); } }
</style>

<header class="lg:hidden sidebar-blur text-white p-4 flex justify-between items-center sticky top-0 z-[50] shadow-md">
    <span class="text-xl font-black tracking-tighter uppercase italic">DORM<span class="font-light text-green-200">hub</span></span>
    <button onclick="toggleSidebar()" class="p-2 bg-white/20 rounded-xl"><i class="fa-solid fa-bars-staggered"></i></button>
</header>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-[60] hidden lg:hidden transition-opacity"></div>

<aside id="sidebar" class="fixed top-0 left-0 h-screen w-72 sidebar-blur text-white z-[70] p-6 flex flex-col closed lg:translate-x-0 shadow-2xl">
    
    <div class="mb-8 flex items-center gap-3 px-2">
        <div class="bg-white p-2.5 rounded-2xl shadow-lg text-2xl">🏠</div>
        <h1 class="text-2xl font-black tracking-tighter uppercase italic">DORM<span class="font-light text-green-200 uppercase">hub</span></h1>
    </div>

    <div class="mb-6 px-2">
        <?php if ($is_logged_in): ?>
            <div class="bg-black/10 p-4 rounded-[2rem] border border-white/10 flex items-center gap-4">
                <img src="<?= $sb_profile ?: 'https://ui-avatars.com/api/?background=ffffff&color=16a34a&name='.urlencode($sb_fullname) ?>" 
                     class="w-11 h-11 rounded-2xl object-cover border-2 border-white/20 shadow-sm">
                <div class="overflow-hidden">
                    <p class="text-[9px] text-green-100 font-black uppercase tracking-[0.15em] opacity-80 mb-0.5"><?= htmlspecialchars($sb_role) ?></p>
                    <p class="font-bold truncate text-sm leading-tight"><?= htmlspecialchars($sb_fullname) ?></p>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php" class="nav-link bg-white !text-green-600 font-black justify-center shadow-xl italic uppercase">
                <i class="fa-solid fa-right-to-bracket"></i> Login
            </a>
        <?php endif; ?>
    </div>

    <nav class="flex-1 px-2 overflow-y-auto">
        
        <?php if ($sb_role === 'admin'): ?>
            <p class="text-[10px] text-green-100 font-black uppercase tracking-[0.2em] mb-4 mt-2 opacity-60">Admin Management</p>
            <a href="<?= BASE_URL ?>admin/admin_dashboard.php" class="nav-link <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie w-6"></i> <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>admin/manage_rooms.php" class="nav-link <?= ($current_page == 'manage_rooms.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-door-open w-6"></i> <span>จัดการห้องพัก</span>
            </a>
            <a href="<?= BASE_URL ?>admin/manage_tenants.php" class="nav-link <?= ($current_page == 'manage_tenants.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear w-6"></i> <span>จัดการผู้เช่า</span>
            </a>
            <a href="<?= BASE_URL ?>admin/manage_bookings.php" class="nav-link <?= ($current_page == 'manage_bookings.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-check-to-slot w-6"></i> <span>อนุมัติการจอง</span>
            </a>
            <a href="<?= BASE_URL ?>admin/meter_records.php" class="nav-link <?= ($current_page == 'meter_records.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high w-6"></i> <span>จดมิเตอร์</span>
            </a>
            <a href="<?= BASE_URL ?>admin/manage_bills.php" class="nav-link <?= ($current_page == 'manage_bills.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice-dollar w-6"></i> <span>จัดการบิล</span>
            </a>
            <a href="<?= BASE_URL ?>admin/manage_maintenance.php" class="nav-link <?= ($current_page == 'manage_maintenance.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-wrench w-6"></i> <span>จัดการแจ้งซ่อม</span>
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-sliders w-6"></i> <span>ตั้งค่าระบบ</span>
            </a>

        <?php else: ?>
            <p class="text-[10px] text-green-100 font-black uppercase tracking-[0.2em] mb-4 mt-2 opacity-60">General Menu</p>
            <?php if ($sb_role !== 'user'): ?>
            <a href="<?= BASE_URL ?>index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-house w-6"></i> <span>หน้าแรก / จองห้อง</span>
            </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <a href="<?= BASE_URL ?>view_booking.php" class="nav-link <?= ($current_page == 'view_booking.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left w-6"></i> <span>ติดตามสถานะการจอง</span>
                </a>

                <?php if ($sb_role === 'user'): ?>
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <p class="text-[10px] text-green-100 font-black uppercase tracking-[0.2em] mb-4 opacity-60">Tenant Menu</p>
                        <a href="<?= BASE_URL ?>users/view_bills.php" class="nav-link <?= ($current_page == 'view_bills.php') ? 'active' : '' ?>">
                            <i class="fa-solid fa-receipt w-6"></i> <span>บิลของฉัน</span>
                        </a>
                        <a href="<?= BASE_URL ?>users/payment_history.php" class="nav-link <?= ($current_page == 'payment_history.php') ? 'active' : '' ?>">
                            <i class="fa-solid fa-clock-rotate-left w-6"></i> <span>ประวัติชำระเงิน</span>
                        </a>
                        <a href="<?= BASE_URL ?>users/meter_records.php" class="nav-link <?= ($current_page == 'meter_records.php') ? 'active' : '' ?>">
                            <i class="fa-solid fa-gauge-high w-6"></i> <span>ประวัติมิเตอร์</span>
                        </a>
                        <a href="<?= BASE_URL ?>users/report_repair.php" class="nav-link <?= ($current_page == 'report_repair.php') ? 'active' : '' ?>">
                            <i class="fa-solid fa-wrench w-6"></i> <span>แจ้งซ่อม</span>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
            <div class="mt-auto pt-10">
                <a href="<?= BASE_URL ?>logout.php" class="nav-link !text-red-100 hover:!bg-red-500/20">
                    <i class="fa-solid fa-right-from-bracket w-6"></i> <span class="font-bold italic">Sign Out</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>
</aside>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sb.classList.toggle('closed');
        overlay.classList.toggle('hidden');
    }
</script>