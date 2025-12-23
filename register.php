<?php
session_start();
require 'config/db_connect.php';

if (isset($_POST['register'])) {
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "ชื่อผู้ใช้นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fullname, $username, $passwordHash, 'user']);

            $_SESSION['user'] = $username;
            header("Location: index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างบัญชีใหม่ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-500 via-emerald-600 to-teal-700 min-h-screen flex items-center justify-center p-6">

    <div class="glass-card shadow-2xl rounded-[2.5rem] p-8 md:p-12 w-full max-w-lg transition-all">
        
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-2xl mb-4 text-3xl shadow-inner">
                📝
            </div>
            <h1 class="text-3xl font-bold text-slate-800">สร้างบัญชีผู้เช่า</h1>
            <p class="text-slate-500 mt-2">เข้าร่วมระบบจัดการหอพัก DORMHUB</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="grid grid-cols-1 gap-5">
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-green-500 transition-colors">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <input type="text" name="fullname" placeholder="ชื่อ-นามสกุล" required 
                    class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition-all">
            </div>

            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-green-500 transition-colors">
                    <i class="fa-solid fa-circle-user"></i>
                </div>
                <input type="text" name="username" placeholder="ชื่อผู้ใช้ (Username)" required 
                    class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition-all">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-green-500 transition-colors">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <input type="password" name="password" placeholder="รหัสผ่าน" required 
                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition-all">
                </div>

                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-green-500 transition-colors">
                        <i class="fa-solid fa-shield-check"></i>
                    </div>
                    <input type="password" name="confirm_password" placeholder="ยืนยันรหัส" required 
                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition-all">
                </div>
            </div>

            <button type="submit" name="register" 
                class="w-full mt-4 py-4 bg-green-600 text-white rounded-2xl font-bold text-lg hover:bg-green-700 transform active:scale-[0.98] transition-all shadow-xl shadow-green-100">
                สมัครสมาชิกตอนนี้
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <p class="text-slate-500">
                มีบัญชีผู้ใช้อยู่แล้ว? 
                <a href="login.php" class="text-green-600 font-bold hover:text-green-700 transition underline underline-offset-4">กลับไปเข้าสู่ระบบ</a>
            </p>
        </div>

    </div>

</body>
</html>