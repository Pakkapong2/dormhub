<?php
session_start();
require 'config/db_connect.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();


    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user']    = $user['username'];
        $_SESSION['name']    = $user['fullname'];
        $_SESSION['role']    = $user['role'];

        if (isset($_SESSION['pending_line_id'])) {
            $update = $pdo->prepare("UPDATE users SET line_user_id = ? WHERE user_id = ?");
            $update->execute([$_SESSION['pending_line_id'], $user['user_id']]);
            unset($_SESSION['pending_line_id']);
        }

        if ($user['role'] === 'admin') {
            header("Location: admin/admin_dashboard.php"); // ชี้เข้าไปในโฟลเดอร์ admin
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}

$client_id = '2008447819';
$redirect_uri = 'http://localhost/dormhub/line_callback.php';
$state = bin2hex(random_bytes(8));
$_SESSION['line_state'] = $state;

$line_login_url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&state={$state}&scope=profile%20openid%20email";
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Anuphan', sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .line-gradient {
            background: linear-gradient(135deg, #06C755 0%, #05a346 100%);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-500 via-emerald-600 to-teal-700 min-h-screen flex items-center justify-center p-4">

    <div class="glass-card shadow-2xl rounded-[2rem] p-8 md:p-12 w-full max-w-[450px]">

        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-3xl mb-4 text-4xl shadow-inner">🏠</div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">DORM<span class="text-green-600">HUB</span></h1>
            <p class="text-slate-500 mt-2 font-light">ระบบจัดการหอพักอัจฉริยะ</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 group-focus-within:text-green-500 transition-colors">
                    <i class="fa-solid fa-user"></i>
                </div>
                <input type="text" name="username" placeholder="ชื่อผู้ใช้" required
                    class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 transition-all text-slate-700">
            </div>

            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 group-focus-within:text-green-500 transition-colors">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <input type="password" name="password" placeholder="รหัสผ่าน" required
                    class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 transition-all text-slate-700">
            </div>

            <button type="submit" name="login"
                class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold hover:bg-slate-800 transform active:scale-[0.98] transition-all shadow-lg">
                เข้าสู่ระบบ
            </button>
        </form>

        <div class="my-8 flex items-center">
            <div class="flex-grow border-t border-slate-200"></div>
            <span class="mx-4 text-xs font-bold text-slate-300 uppercase tracking-widest">หรือ</span>
            <div class="flex-grow border-t border-slate-200"></div>
        </div>

        <a href="<?= $line_login_url ?>"
            class="line-gradient flex items-center justify-center gap-3 w-full py-4 text-white rounded-2xl font-bold hover:opacity-90 transform active:scale-[0.98] transition-all shadow-lg shadow-green-100">
            <i class="fa-brands fa-line text-2xl"></i>
            เข้าสู่ระบบด้วย LINE
        </a>

        <div class="mt-10 text-center text-slate-400 text-[10px] uppercase tracking-widest">
            &copy; 2025 DORMHUB Management System
        </div>
    </div>

</body>

</html>