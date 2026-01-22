<?php 
session_start();
require_once 'config/db_connect.php'; 

// กรองข้อมูล (Search)
$search = $_GET['search'] ?? '';
$max_price = $_GET['max_price'] ?? '';

try {
    // ดึงเฉพาะห้องว่าง (Available) ตามโครงสร้างตารางที่คุณมี
    $sql = "SELECT * FROM rooms WHERE status = 'available'";
    $params = [];

    if ($search) {
        $sql .= " AND room_number LIKE ?";
        $params[] = "%$search%";
    }
    if ($max_price) {
        $sql .= " AND base_rent <= ?";
        $params[] = $max_price;
    }
    
    $sql .= " ORDER BY room_number ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <title>DormHub - รายการห้องว่าง</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;700&display=swap');
        body { font-family: 'Anuphan', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">
    
    <?php include 'sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen p-6 md:p-10 transition-all">
        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-10 gap-6">
            <div>
                <h1 class="text-4xl font-black text-slate-800 italic uppercase tracking-tighter">
                    Available <span class="text-blue-600">Rooms</span>
                </h1>
                <p class="text-slate-500 font-medium">ห้องว่างพร้อมเข้าอยู่ (<?= count($rooms) ?> ห้อง)</p>
            </div>

            <form action="index.php" method="GET" class="flex flex-wrap gap-3 w-full xl:w-auto">
                <div class="relative flex-1 md:flex-none">
                    <i class="fa-solid fa-door-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" placeholder="เลขห้อง..." value="<?= htmlspecialchars($search) ?>"
                        class="pl-11 pr-6 py-3 bg-white border-none rounded-2xl shadow-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm w-full md:w-40">
                </div>
                
                <div class="relative flex-1 md:flex-none">
                    <i class="fa-solid fa-tag absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="number" name="max_price" placeholder="ราคาไม่เกิน..." value="<?= htmlspecialchars($max_price) ?>"
                        class="pl-11 pr-6 py-3 bg-white border-none rounded-2xl shadow-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm w-full md:w-40">
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-black text-xs hover:bg-slate-900 transition-all uppercase italic">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Search
                </button>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?= $_SESSION['role'] == 'admin' ? 'admin/manage_bills.php' : 'users/index.php' ?>" 
                       class="bg-slate-900 text-white px-6 py-3 rounded-2xl font-black text-xs flex items-center gap-2 italic uppercase shadow-xl">
                        <i class="fa-solid fa-circle-user"></i> My Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="bg-slate-900 text-white px-6 py-3 rounded-2xl font-black text-xs flex items-center gap-2 italic uppercase shadow-xl">
                        <i class="fa-solid fa-right-to-bracket"></i> Login
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-8">
            <?php foreach ($rooms as $room): ?>
                <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all group overflow-hidden">
                    <div class="h-56 bg-slate-100 relative overflow-hidden text-slate-300">
                        <?php if(!empty($room['room_image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($room['room_image']) ?>" 
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        <?php else: ?>
                            <div class="w-full h-full flex flex-col items-center justify-center italic font-black text-xs">
                                <i class="fa-regular fa-image text-4xl mb-2 opacity-20"></i>
                                NO IMAGE
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute bottom-5 left-5">
                            <span class="bg-emerald-500 text-white text-[10px] font-black uppercase px-4 py-1.5 rounded-full shadow-lg">
                                Available
                            </span>
                        </div>
                    </div>

                    <div class="p-8">
                        <div class="flex justify-between items-start mb-6">
                            <h2 class="text-4xl font-black text-slate-800 italic tracking-tighter">RM <?= htmlspecialchars($room['room_number']); ?></h2>
                            <div class="text-right">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Rent</p>
                                <p class="text-2xl font-black text-blue-600 italic tracking-tighter">฿<?= number_format($room['base_rent']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4 py-4 border-t border-slate-50 mb-6 text-slate-400 text-xs font-bold">
                            <span><i class="fa-solid fa-wifi mr-1"></i> Wifi</span>
                            <span><i class="fa-solid fa-snowflake mr-1"></i> Air</span>
                            <span><i class="fa-solid fa-bath mr-1"></i> Private</span>
                        </div>

                        <a href="room_detail.php?id=<?= $room['room_id']; ?>" 
                           class="block w-full text-center bg-slate-900 text-white py-4 rounded-2xl font-black uppercase italic tracking-widest text-[10px] hover:bg-blue-600 transition-all shadow-xl shadow-slate-100">
                            ดูรายละเอียด & จองห้อง
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(empty($rooms)): ?>
                <div class="col-span-full py-24 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-magnifying-glass text-slate-300 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase italic">ไม่พบห้องที่คุณค้นหา</h3>
                    <p class="text-slate-400 font-medium">ลองเปลี่ยนเงื่อนไขการค้นหาดูอีกครั้งครับ</p>
                    <a href="index.php" class="inline-block mt-6 text-blue-600 font-black uppercase text-xs hover:underline tracking-widest italic">ดูห้องว่างทั้งหมด</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>