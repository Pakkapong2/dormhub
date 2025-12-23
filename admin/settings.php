<?php
session_start();
require '../config/db_connect.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น admin เท่านั้น)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

// ถ้ามีการกดบันทึก
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $water = $_POST['water_rate'];
    $electric = $_POST['electric_rate'];

    // เช็คว่ามีข้อมูลเดิมอยู่ไหม
    $check = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();

    if ($check > 0) {
        $stmt = $pdo->prepare("UPDATE settings SET water_rate = ?, electric_rate = ? WHERE setting_id = 1");
        $stmt->execute([$water, $electric]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (water_rate, electric_rate) VALUES (?, ?)");
        $stmt->execute([$water, $electric]);
    }
    $success = "บันทึกราคาต่อหน่วยเรียบร้อยแล้ว!";
}

// ดึงข้อมูลปัจจุบันมาโชว์
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าระบบ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-sm p-8 border border-slate-100">
            <h1 class="text-2xl font-bold mb-6 flex items-center gap-3">
                <i class="fa-solid fa-gears text-blue-500"></i> ตั้งค่าราคาต่อหน่วย
            </h1>

            <?php if(isset($success)): ?>
                <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">ค่าน้ำ (บาท / หน่วย)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">💧</span>
                        <input type="number" step="0.01" name="water_rate" value="<?= $config['water_rate'] ?? 0 ?>" required
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">ค่าไฟ (บาท / หน่วย)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">⚡</span>
                        <input type="number" step="0.01" name="electric_rate" value="<?= $config['electric_rate'] ?? 0 ?>" required
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold hover:bg-slate-800 transition shadow-lg shadow-slate-200">
                    บันทึกข้อมูล
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="../index.php" class="text-slate-400 text-sm hover:underline">กลับหน้าหลัก</a>
            </div>
        </div>
    </div>
</body>
</html>