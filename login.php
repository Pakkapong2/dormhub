<?php
session_start();
error_reporting(0);
require 'config/db_connect.php';

$error = '';

// --- 1. จัดการ Login แบบปกติ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manual_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // ล้าง Session เก่าทิ้งก่อนเพื่อความปลอดภัย
        session_regenerate_id();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role']; // เก็บ Role จริงจาก DB

        // --- ระบบ Redirect ตามสิทธิ์จริง ---
        if ($user['role'] === 'admin') {
            $target = "admin/manage_tenants.php";
        } elseif ($user['role'] === 'user') {
            // ถ้าเป็น User แต่แอดมินลืมใส่ห้อง ให้ถือว่าเป็น Viewer ไปก่อนเพื่อความปลอดภัย
            if (empty($user['room_id']) || $user['room_id'] == 0) {
                $_SESSION['role'] = 'viewer';
                $target = "index.php";
            } else {
                $target = "users/index.php";
            }
        } else {
            // สิทธิ์ viewer
            $target = "index.php";
        }

        // จัดการหน้าที่ค้างไว้
        if (isset($_SESSION['redirect_to']) && !empty($_SESSION['redirect_to'])) {
            $target = $_SESSION['redirect_to'];
            unset($_SESSION['redirect_to']);
        }
        
        header("Location: " . $target);
        exit;
    } else {
        $error = "Username หรือ Password ไม่ถูกต้อง";
    }
}

// --- 2. กรณีมี Session ค้างอยู่ (Auto Login) ---
if (isset($_SESSION['user_id']) && !isset($_POST['manual_login'])) {
    $stmt_check = $pdo->prepare("SELECT role, room_id FROM users WHERE user_id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $current_u = $stmt_check->fetch();

    if ($current_u) {
        if ($current_u['role'] === 'admin') {
            $target = "admin/manage_tenants.php";
        } elseif ($current_u['role'] === 'user' && !empty($current_u['room_id'])) {
            $target = "users/index.php";
        } else {
            $target = "index.php";
        }
        
        header("Location: " . $target);
        exit;
    }
}

// --- 3. LINE Config ---
$client_id = '2008447819';
$redirect_uri = 'http://localhost/dormhub/line_callback.php';
$line_login_url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&scope=profile%20openid%20email&state=" . bin2hex(random_bytes(16));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-[450px] rounded-[3rem] shadow-2xl overflow-hidden border border-slate-100">
        <div class="bg-slate-900 p-10 text-center relative overflow-hidden">
            <div class="relative z-10">
                <h1 class="text-4xl font-black italic text-white uppercase tracking-tighter">DORM<span class="text-green-500">HUB</span></h1>
                <p class="text-slate-400 text-[10px] uppercase tracking-[0.3em] mt-2 font-bold">Resident Portal Access</p>
            </div>
        </div>

        <div class="p-10">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="manual_login" value="1">
                <?php if($error): ?>
                    <div class="bg-rose-50 text-rose-500 p-4 rounded-xl text-[11px] font-bold border border-rose-100 italic text-center">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Username</label>
                    <input type="text" name="username" placeholder="Phone or Username" required 
                           class="w-full mt-1.5 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold text-slate-700 outline-none focus:ring-4 focus:ring-green-500/10 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-1">Password</label>
                    <input type="password" name="password" placeholder="••••••••" required 
                           class="w-full mt-1.5 px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold text-slate-700 outline-none focus:ring-4 focus:ring-green-500/10 transition-all">
                </div>
                <button type="submit" class="w-full py-5 bg-slate-900 text-white rounded-2xl font-black italic uppercase text-sm hover:bg-slate-800 transition-all shadow-xl transform active:scale-95">
                    Sign In 
                </button>
            </form>

            <div class="relative my-10 flex items-center">
                <div class="flex-grow border-t border-slate-100"></div>
                <span class="px-4 text-[10px] font-black text-slate-300 uppercase tracking-widest">or quickly with</span>
                <div class="flex-grow border-t border-slate-100"></div>
            </div>

            <a href="<?= $line_login_url ?>" class="flex items-center justify-center gap-3 w-full py-5 bg-[#06C755] text-white rounded-2xl font-black italic uppercase text-sm hover:bg-[#05b34c] transition-all shadow-xl shadow-green-100 transform active:scale-95">
                <i class="fa-brands fa-line text-2xl"></i>
                Login with LINE
            </a>
            <p class="mt-8 text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest">Dormhub Support v1.0</p>
        </div>
    </div>
</body>
</html>