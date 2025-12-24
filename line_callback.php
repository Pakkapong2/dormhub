<?php
session_start();
require 'config/db_connect.php'; 

if (!isset($_GET['code'], $_GET['state'])) {
    die('Missing parameters');
}

$code = $_GET['code'];
$state = $_GET['state'];

if (!isset($_SESSION['line_state']) || $state !== $_SESSION['line_state']) {
    die('Invalid state');
}

$client_id = '2008447819';
$client_secret = '8b06447416799b311b55bf33e4b777c5';
$redirect_uri = 'http://localhost/dormhub/line_callback.php';

// --- 1. แลกเปลี่ยน Code เป็น Access Token ---
$token_url = 'https://api.line.me/oauth2/v2.1/token';
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret
];

$options = [
    'http' => [
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];
$context  = stream_context_create($options);
$response = @file_get_contents($token_url, false, $context);
if (!$response) die('Failed to get access token');

$token = json_decode($response, true);
$access_token = $token['access_token'] ?? null;

// --- 2. ดึงข้อมูลโปรไฟล์จาก LINE ---
$profile_url = 'https://api.line.me/v2/profile';
$opts = [
    'http' => [
        'header' => "Authorization: Bearer $access_token"
    ]
];
$context = stream_context_create($opts);
$profile = json_decode(file_get_contents($profile_url, false, $context), true);

if (!isset($profile['userId'])) die('Failed to get profile');

$line_uid = $profile['userId'];

try {
    // --- 3. ตรวจสอบในฐานข้อมูลว่า Line ID นี้เคยผูกกับใครหรือยัง ---
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$line_uid]);
    $user = $stmt->fetch();

    if ($user) {
        // --- กรณีที่ 1: พบ User/Admin ที่ผูกไว้แล้ว ---
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        
        // แยกหน้า Redirect ตามบทบาท (Role)
        if ($user['role'] === 'admin') {
            // ถ้าเป็น Admin ให้ไปหน้า Dashboard หลังบ้าน
            header('Location: index.php');
        } else {
            // ถ้าเป็นผู้เช่าปกติ ให้ไปหน้าแรก
            header('Location: index.php');
        }
        exit;
        
    } else {
        // --- กรณีที่ 2: LINE นี้ยังไม่เคยผูกกับบัญชีใดๆ ในหอเรา ---
        
        // เช็คว่าตอนนี้มีการ Login ค้างไว้หรือไม่ (ใช้ในกรณีผูกบัญชีครั้งแรก)
        if (isset($_SESSION['user_id'])) {
            $update = $pdo->prepare("UPDATE users SET line_user_id = ?, line_display_name = ?, line_picture_url = ? WHERE user_id = ?");
            $update->execute([$line_uid, $profile['displayName'], $profile['pictureUrl'], $_SESSION['user_id']]);
            
            // ผูกเสร็จแล้วส่งไปหน้าที่ควรจะเป็น
            if ($_SESSION['role'] === 'admin') {
                header('Location: index.php?msg=line_linked');
            } else {
                header('Location: index.php?msg=line_linked');
            }
            exit;
        } else {
            // ยังไม่มีข้อมูลในระบบและยังไม่ได้ล็อกอิน ให้ไปหน้า Login เพื่อยืนยันตัวตนก่อน
            $_SESSION['pending_line_id'] = $line_uid; 
            header('Location: login.php?msg=bind_required');
            exit;
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>