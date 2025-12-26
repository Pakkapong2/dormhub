<?php
session_start();
require 'config/db_connect.php';

// --- 1. ตรวจสอบพารามิเตอร์เบื้องต้น ---
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

// --- 2. แลกเปลี่ยน Code เป็น Access Token ---
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

// --- 3. ดึงข้อมูลโปรไฟล์จาก LINE ---
$profile_url = 'https://api.line.me/v2/profile';
$opts = [
    'http' => [
        'header' => "Authorization: Bearer $access_token"
    ]
];
$context = stream_context_create($opts);
$profile_decode = file_get_contents($profile_url, false, $context);
$profile = json_decode($profile_decode, true);

if (!isset($profile['userId'])) die('Failed to get profile');

$line_uid = $profile['userId'];

try {
    // --- 4. ตรวจสอบในฐานข้อมูลว่า Line ID นี้เคยผูกไว้หรือยัง ---
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$line_uid]);
    $user = $stmt->fetch();

    if ($user) {
        // --- [CASE 1] พบ User/Admin ในระบบแล้ว -> ทำการ Login ทันที ---
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user']    = $user['username'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['fullname']; // อิงตามตาราง users

        // ** Senior Fix: แยกทางเดินตาม Role ให้ถูกต้อง **
        if ($user['role'] === 'admin') {
            header("Location: admin/admin_dashboard.php"); // ชี้เข้าไปในโฟลเดอร์ admin
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        // --- [CASE 2] LINE นี้ยังไม่ได้ผูกกับบัญชีใดๆ ---

        if (isset($_SESSION['user_id'])) {
            // ถ้า User ล็อกอินค้างไว้แล้ว (กดผูกจากหน้าโปรไฟล์)
            $update = $pdo->prepare("UPDATE users SET line_user_id = ? WHERE user_id = ?");
            $update->execute([$line_uid, $_SESSION['user_id']]);

            // ตรวจสอบว่าใครเป็นคนผูก แล้วส่งกลับไปหน้าตัวเอง
            if ($_SESSION['role'] === 'admin') {
                header('Location: admin_dashboard.php?msg=line_linked');
            } else {
                header('Location: index.php?msg=line_linked');
            }
            exit;
        } else {
            // ยังไม่มีบัญชีและยังไม่ได้ล็อกอิน -> เก็บ LINE ID ไว้ชั่วคราวแล้วให้ไป Login
            $_SESSION['pending_line_id'] = $line_uid;
            header('Location: login.php?msg=bind_required');
            exit;
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
