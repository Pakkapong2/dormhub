<?php
// config/db_connect.php
$host = 'localhost';
$dbname = 'dorm_payment_system';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // สร้างตัวแปร $pdo ไว้ที่นี่
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
}
// จบไฟล์แค่นี้พอครับ ไม่ต้องมี Query