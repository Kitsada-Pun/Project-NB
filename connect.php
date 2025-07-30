<?php
// connect.php หรือ config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'pixellink'); // ตรวจสอบให้แน่ใจว่าชื่อฐานข้อมูลถูกต้อง

// กำหนด ROOT_PATH
// ตัวอย่าง: ถ้าโปรเจกต์ของคุณอยู่ใน http://localhost/freelance_platform/
// และไฟล์ PHP อยู่ใน http://localhost/freelance_platform/admin/
// ROOT_PATH ควรจะเป็น http://localhost/freelance_platform/
// *** คุณต้องเปลี่ยน 'your_project_folder' เป็นชื่อโฟลเดอร์โปรเจกต์ของคุณจริงๆ ***
// define('ROOT_PATH', 'http://localhost/your_project_folder/'); 

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // บันทึกข้อผิดพลาดสำหรับการดีบัก แต่แสดงข้อความที่เป็นมิตรกับผู้ใช้
    error_log("Database connection failed: " . $conn->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$conn->set_charset("utf8mb4"); // ตั้งค่า charset เป็น utf8mb4 เพื่อรองรับภาษาไทยและอิโมจิได้สมบูรณ์ยิ่งขึ้น
?>