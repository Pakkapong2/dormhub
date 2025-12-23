<?php
session_start();

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
$redirect_uri = 'http://localhost/project/line_callback.php';

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
$response = file_get_contents($token_url, false, $context);
if (!$response) {
    die('Failed to get access token');
}
$token = json_decode($response, true);
$access_token = $token['access_token'] ?? null;
if (!$access_token) {
    die('No access token returned');
}

$profile_url = 'https://api.line.me/v2/profile';
$opts = [
    'http' => [
        'header' => "Authorization: Bearer $access_token"
    ]
];
$context = stream_context_create($opts);
$profile = json_decode(file_get_contents($profile_url, false, $context), true);

if (!isset($profile['userId'])) {
    die('Failed to get profile');
}

require 'config/db_connect.php'; 

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$profile['userId']]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (fullname, line_user_id, line_display_name, line_picture_url, role) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $profile['displayName'],
            $profile['userId'],
            $profile['displayName'],
            $profile['pictureUrl'] ?? null,
            'user' 
        ]);
        
        $_SESSION['role'] = 'user';
    } else {
        $update = $pdo->prepare("UPDATE users SET line_display_name = ?, line_picture_url = ? WHERE line_user_id = ?");
        $update->execute([$profile['displayName'], $profile['pictureUrl'], $profile['userId']]);
        
        $_SESSION['role'] = $user['role'];
    }
    $_SESSION['user'] = $profile['userId'];
    
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>