<?php
session_start();

// 1. ล้างค่าใน Session ทั้งหมด
$_SESSION = array();

// 2. ถ้ามีการใช้ Cookie สำหรับ Session ให้ลบทิ้งด้วย
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session
session_destroy();

// 4. ดีดกลับไปหน้าหลัก (index.php)
header("Location: index.php");
exit;
?>