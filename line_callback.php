<?php
require_once __DIR__ . '/config/app_config.php'; // Includes session_start()
require_once __DIR__ . '/config/db_connect.php'; 

// error_reporting(0); // Removed for better error visibility during development

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code) die('Access Denied');

// ถอดรหัสหน้าที่จะกลับไป
$decoded_state = json_decode(base64_decode($state), true);
$target_after_login = !empty($decoded_state['redirect_to']) ? $decoded_state['redirect_to'] : "index.php";

// 1. แลก Access Token
$token_url = 'https://api.line.me/oauth2/v2.1/token';
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => BASE_URL . 'line_callback.php', // Use BASE_URL
    'client_id' => '2008447819',
    'client_secret' => '8b06447416799b311b55bf33e4b777c5'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$res = json_decode(curl_exec($ch), true);

if (!isset($res['access_token'])) {
    die('Login Failed: ' . ($res['error_description'] ?? 'Unknown Error'));
}

// 2. ดึงโปรไฟล์จาก LINE
$access_token = $res['access_token'];
$ch = curl_init('https://api.line.me/v2/profile');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
$profile = json_decode(curl_exec($ch), true);

$line_uid     = $profile['userId'];
$display_name = $profile['displayName'];
$picture_url  = $profile['pictureUrl'] ?? '';

// 3. ตรวจสอบ User ในฐานข้อมูล
$stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
$stmt->execute([$line_uid]);
$user = $stmt->fetch();

if (!$user) {
    // กรณีเป็นสมาชิกใหม่: บันทึกเป็น 'viewer'
    $stmt = $pdo->prepare("INSERT INTO users (username, fullname, line_user_id, line_picture_url, role) VALUES (?, ?, ?, ?, 'viewer')");
    $stmt->execute([$line_uid, $display_name, $line_uid, $picture_url]);
    
    // ดึงข้อมูลกลับมาอีกครั้ง
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$line_uid]);
    $user = $stmt->fetch();
}

// 4. ตั้งค่า Session (ใช้ session_regenerate_id เพื่อความปลอดภัย)
session_regenerate_id();
$_SESSION['user_id']  = $user['user_id'];
$_SESSION['fullname'] = $user['fullname'];
$_SESSION['picture']  = $user['line_picture_url'];
$_SESSION['role']     = $user['role']; // สำคัญมาก: ต้องเก็บ role จริงจาก DB ลง Session

// 5. ระบบดีดหน้า (Redirect Logic) 
// ใช้ Full Path เพื่อป้องกัน Browser หลงทาง
// $base_url = "http://localhost/dormhub/"; // Replaced with BASE_URL constant

if ($user['role'] === 'admin') {
    header("Location: " . BASE_URL . "admin/manage_tenants.php");
} elseif ($user['role'] === 'user') {
    // ถ้าเป็น user แต่ไม่มีห้องพัก ให้ถือว่าเป็น viewer ก่อน
    if (empty($user['room_id']) || $user['room_id'] == 0) {
        $_SESSION['role'] = 'viewer';
        header("Location: " . BASE_URL . "index.php");
    } else {
        header("Location: " . BASE_URL . "users/index.php");
    }
} else {
    // viewer
    header("Location: " . BASE_URL . "index.php");
}
exit;