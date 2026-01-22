<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 2. Logic: บันทึกการตั้งค่า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $water = $_POST['water_rate'];
    $electric = $_POST['electric_rate'];

    $check = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();

    if ($check > 0) {
        $stmt = $pdo->prepare("UPDATE settings SET water_rate = ?, electric_rate = ? WHERE setting_id = 1");
        $stmt->execute([$water, $electric]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (water_rate, electric_rate) VALUES (?, ?)");
        $stmt->execute([$water, $electric]);
    }
    header("Location: settings.php?msg=success");
    exit();
}

// 3. ดึงค่าปัจจุบัน
$config = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Anuphan', sans-serif; }
        .input-focus:focus { transform: translateY(-2px); transition: all 0.2s; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="lg:ml-72 transition-all">
        <div class="container mx-auto px-4 py-10">
            
            <div class="mb-10">
                <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3 italic uppercase tracking-tighter">
                    <i class="fa-solid fa-sliders text-blue-600"></i> System Settings
                </h1>
                <p class="text-slate-500 font-medium">กำหนดราคาค่าสาธารณูปโภคพื้นฐาน</p>
            </div>

            <div class="max-w-xl">
                <div class="bg-white rounded-[3rem] shadow-xl border border-slate-100 overflow-hidden">
                    <div class="bg-slate-900 px-10 py-6">
                        <h3 class="font-black italic uppercase tracking-tighter text-white text-lg">Utility Rates</h3>
                    </div>
                    
                    <form method="POST" class="p-10 space-y-8">
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-droplet text-blue-500"></i> Water Rate (Per Unit)
                            </label>
                            <div class="relative group">
                                <input type="number" step="0.01" name="water_rate" 
                                    value="<?= $config['water_rate'] ?? 0 ?>" required
                                    class="w-full pl-6 pr-16 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl font-black text-xl italic outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all input-focus">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 font-black text-slate-300 italic uppercase">THB</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-bolt text-amber-500"></i> Electric Rate (Per Unit)
                            </label>
                            <div class="relative group">
                                <input type="number" step="0.01" name="electric_rate" 
                                    value="<?= $config['electric_rate'] ?? 0 ?>" required
                                    class="w-full pl-6 pr-16 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl font-black text-xl italic outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 transition-all input-focus">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 font-black text-slate-300 italic uppercase">THB</span>
                            </div>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black text-lg italic hover:bg-blue-600 shadow-2xl shadow-slate-200 transition-all active:scale-95 uppercase tracking-tighter">
                                Save Configuration
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-8 p-8 bg-blue-50 rounded-[2.5rem] border border-blue-100">
                    <div class="flex gap-4">
                        <i class="fa-solid fa-circle-info text-blue-500 text-xl"></i>
                        <p class="text-sm font-bold text-blue-700 leading-relaxed">
                            <span class="block mb-1 uppercase tracking-wider">คำแนะนำ:</span>
                            ราคาต่อหน่วยนี้จะถูกนำไปคูณกับจำนวนหน่วยที่ใช้จริงในหน้าจดมิเตอร์ เพื่อสร้างยอดเงินในบิลโดยอัตโนมัติ กรุณาตรวจสอบให้ถูกต้องก่อนบันทึก
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // แจ้งเตือนเมื่อบันทึกสำเร็จ
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({
                title: 'SUCCESS!',
                text: 'บันทึกการตั้งค่าราคาต่อหน่วยเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonColor: '#0f172a',
                borderRadius: '2rem'
            });
            // ลบ query string เพื่อไม่ให้ alert ซ้ำเมื่อ refresh
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>