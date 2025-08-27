<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'pixellink');
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // บันทึกข้อผิดพลาดสำหรับการดีบัก แต่แสดงข้อความที่เป็นมิตรกับผู้ใช้
    error_log("Database connection failed: " . $conn->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$conn->set_charset("utf8mb4"); // ตั้งค่า charset เป็น utf8mb4 เพื่อรองรับภาษาไทยและอิโมจิได้สมบูรณ์ยิ่งขึ้น
?>
</div>