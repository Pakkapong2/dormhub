<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pay_id = $_GET['pay_id'] ?? null;
if (!$pay_id) {
    header('Location: view_bills.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_dir = "../uploads/slips/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["slip_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = "SLIP_" . $pay_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;

    $check = getimagesize($_FILES["slip_image"]["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($_FILES["slip_image"]["tmp_name"], $target_file)) {
            $stmt = $pdo->prepare("UPDATE payments SET slip_image = ?, status = 'waiting' WHERE payment_id = ?");
            $stmt->execute([$new_filename, $pay_id]);
            
            header("Location: view_bills.php?status=success");
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT amount FROM payments WHERE payment_id = ?");
$stmt->execute([$pay_id]);
$payment = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งชำระเงิน | DORMHUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Anuphan', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-md">
        <a href="view_bills.php" class="inline-flex items-center mb-6 text-slate-500 font-bold text-sm">
            <i class="fa-solid fa-chevron-left mr-2"></i> ย้อนกลับ
        </a>

        <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
            <div class="p-8">
                <h1 class="text-2xl font-black text-slate-800 mb-2 italic">Confirm Payment</h1>
                <p class="text-slate-500 text-sm mb-6">กรุณาแนบสลิปโอนเงินเพื่อยืนยันยอด</p>

                <div class="bg-slate-900 rounded-3xl p-6 text-white mb-8">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ยอดเงินที่ต้องโอน</p>
                    <h2 class="text-4xl font-black text-emerald-400">฿<?= number_format($payment['amount'], 2) ?></h2>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="relative">
                        <label class="block mb-2 text-sm font-bold text-slate-700">อัปโหลดรูปสลิป (JPG, PNG)</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-64 border-2 border-slate-200 border-dashed rounded-[2rem] cursor-pointer bg-slate-50 hover:bg-slate-100 transition-all overflow-hidden" id="drop-area">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6" id="preview-placeholder">
                                    <i class="fa-solid fa-image text-4xl text-slate-300 mb-4"></i>
                                    <p class="text-xs text-slate-400 font-bold uppercase tracking-tighter">Click to upload transfer slip</p>
                                </div>
                                <img id="image-preview" class="hidden w-full h-full object-cover">
                                <input type="file" name="slip_image" class="hidden" accept="image/*" required onchange="previewImage(this)">
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black text-lg shadow-xl hover:bg-emerald-600 transition-all active:scale-95 uppercase">
                        ส่งหลักฐานการโอน <i class="fa-solid fa-paper-plane ml-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const placeholder = document.getElementById('preview-placeholder');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>