<?php
$base_url = "http://localhost/dormhub/"; 

if (!isset($user) && isset($_SESSION['user'])) {
    require_once 'config/db_connect.php';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ? OR username = ?");
    $stmt->execute([$_SESSION['user'], $_SESSION['user']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!isset($user) || !$user) {
    header("Location: {$base_url}login.php");
    exit;
}

$role = $user['role'] ?? 'user';
$profile_img = $user['line_picture_url'] ?? null;
$fullname = $user['fullname'];
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', sans-serif; }
    .nav-blur { backdrop-filter: blur(8px); background-color: rgba(22, 163, 74, 0.9); }
</style>

<nav class="nav-blur text-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-6 py-3 flex justify-between items-center">
        <a href="<?= $base_url ?>index.php" class="flex items-center gap-2 group">
            <div class="bg-white p-2 rounded-lg group-hover:rotate-12 transition-transform">
                <span class="text-xl">🏠</span>
            </div>
            <span class="text-lg font-bold tracking-tight">DORM<span class="font-light text-green-200">HUB</span></span>
        </a>

        <div class="relative">
            <button id="profileButton" 
                    class="flex items-center gap-3 bg-white/10 hover:bg-white/20 p-1.5 pr-4 rounded-full transition-all border border-white/20"
                    onclick="toggleDropdown()">
                
                <?php if ($profile_img): ?>
                    <img src="<?= htmlspecialchars($profile_img) ?>" class="w-8 h-8 rounded-full object-cover border border-white/50">
                <?php else: ?>
                    <div class="w-8 h-8 bg-gradient-to-tr from-green-400 to-green-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                        <?= strtoupper(substr($fullname, 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-left hidden md:block">
                    <p class="text-xs leading-none opacity-70 mb-1">สวัสดี,</p>
                    <p class="text-sm font-semibold leading-none"><?= htmlspecialchars($fullname) ?></p>
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] opacity-50"></i>
            </button>

            <div id="dropdownMenu" class="absolute right-0 mt-3 w-56 rounded-2xl shadow-2xl bg-white ring-1 ring-black/5 hidden overflow-hidden transform origin-top-right transition-all">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">บทบาทผู้ใช้</p>
                    <p class="text-sm font-semibold text-green-600"><?= $role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้เช่าพัก' ?></p>
                </div>
                <div class="py-2 text-gray-600">
                    <?php if ($role === 'admin'): ?>
                        <a href="<?= $base_url ?>admin/manage_rooms.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-green-50 transition">
                            <span class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-sm">🏘️</span>
                            <span class="text-sm font-medium">จัดการห้องพัก</span>
                        </a>
                        <a href="<?= $base_url ?>admin/manage_users.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-green-50 transition">
                            <span class="w-8 h-8 bg-green-50 text-green-600 rounded-lg flex items-center justify-center text-sm">👥</span>
                            <span class="text-sm font-medium">จัดการผู้เช่า</span>
                        </a>
                        <a href="<?= $base_url ?>admin/meter_records.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-green-50 transition border-b border-gray-100">
                            <span class="w-8 h-8 bg-cyan-50 text-cyan-600 rounded-lg flex items-center justify-center text-sm">💧</span>
                            <span class="text-sm font-medium">บันทึกค่าน้ำไฟ</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= $base_url ?>users/view_bills.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-green-50 transition">
                            <span class="w-8 h-8 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center text-sm">🧾</span>
                            <span class="text-sm font-medium">ดูบิลค่าน้ำไฟ</span>
                        </a>
                        <a href="<?= $base_url ?>users/payment_history.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-green-50 transition border-b border-gray-100">
                            <span class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">💳</span>
                            <span class="text-sm font-medium">ประวัติการชำระเงิน</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?= $base_url ?>logout.php" class="flex items-center gap-3 px-4 py-3 hover:bg-red-50 text-red-600 transition mt-1">
                        <span class="w-8 h-8 bg-red-50 text-red-600 rounded-lg flex items-center justify-center text-sm">🚪</span>
                        <span class="text-sm font-bold">ออกจากระบบ</span>
                    </a>
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

    window.addEventListener('click', function(e) {
        const menu = document.getElementById('dropdownMenu');
        const button = document.getElementById('profileButton');
        if (!button.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>