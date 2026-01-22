<?php
session_start();
require_once 'config/db_connect.php';

// 1. เช็คสิทธิ์: ต้องล็อกอินและไม่ใช่ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id      = $_SESSION['user_id'];
    $room_id      = $_POST['room_id'];
    $move_in_date = $_POST['move_in_date']; 
    $booking_fee  = $_POST['booking_fee'];  
    
    // ข้อมูลส่วนตัวที่ User ยืนยันมาใหม่จากหน้าจอง
    $fullname     = $_POST['fullname'];
    $phone        = $_POST['phone'];
    
    // 2. จัดการไฟล์สลิป
    $upload_dir = 'uploads/slips/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = $_FILES['slip_image']['name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_name  = "slip_room" . $room_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
    $target    = $upload_dir . $new_name;

    // ตรวจสอบนามสกุลไฟล์
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($file_ext, $allowed_ext)) {
        die("อนุญาตเฉพาะไฟล์รูปภาพ (jpg, png, webp) เท่านั้น");
    }

    if (move_uploaded_file($_FILES['slip_image']['tmp_name'], $target)) {
        try {
            $pdo->beginTransaction();

            // --- 3. อัปเดตข้อมูลผิวเผินในตาราง users ให้แอดมินดูง่าย ---
            // ใช้ชื่อคอลัมน์ fullname และ phone ตามโครงสร้าง SQL ของคุณ
            $updateUser = $pdo->prepare("UPDATE users SET fullname = ?, phone = ? WHERE user_id = ?");
            $updateUser->execute([$fullname, $phone, $user_id]);

            // 4. บันทึกข้อมูลการจอง
            $sql = "INSERT INTO bookings (user_id, room_id, move_in_date, booking_fee, status, slip_image) 
                    VALUES (?, ?, ?, ?, 'pending', ?)";
            $stmt = $pdo->prepare($sql);
            
            // ส่งตัวแปรให้ตรงตามตำแหน่ง (user_id, room_id, move_in_date, booking_fee, slip_image)
            $stmt->execute([$user_id, $room_id, $move_in_date, $booking_fee, $new_name]);

            // 5. อัปเดตสถานะห้องเป็น 'booked' ทันที
            $update_room = $pdo->prepare("UPDATE rooms SET status = 'booked' WHERE room_id = ?");
            $update_room->execute([$room_id]);

            $pdo->commit();
            
            header("Location: view_booking.php?msg=success");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            // หากบันทึก DB ไม่สำเร็จ ให้ลบไฟล์ที่เพิ่งอัปโหลดทิ้งเพื่อไม่ให้หนัก Server
            if(file_exists($target)) unlink($target);
            die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage());
        }
    } else {
        die("ไม่สามารถอัปโหลดไฟล์สลิปได้ โปรดตรวจสอบสิทธิ์การเขียนโฟลเดอร์ (Permission)");
    }
} else {
    header("Location: index.php");
    exit;
}