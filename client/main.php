<?php
// client/main.php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล (ใช้ mysqli) ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink"; // <--- เปลี่ยนเป็นชื่อฐานข้อมูล 'pixellink'

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$condb->set_charset("utf8mb4");

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ และเป็น 'client'
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    // ถ้าไม่ได้ล็อกอินหรือไม่ใช่ client ให้เปลี่ยนเส้นทางไปหน้า login
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน (Client)
$client_id = $_SESSION['user_id'];
$client_data = [];
$sql_client_data = "SELECT username, first_name, last_name, email, phone_number 
                     FROM users 
                     WHERE user_id = ?";
$stmt_client_data = $condb->prepare($sql_client_data);
if ($stmt_client_data === false) {
    error_log("SQL Prepare Error (client_data): " . $condb->error);
} else {
    $stmt_client_data->bind_param("i", $client_id);
    $stmt_client_data->execute();
    $result_client_data = $stmt_client_data->get_result();
    $client_data = $result_client_data->fetch_assoc();
    $stmt_client_data->close();
}

// PHP Logic สำหรับดึงงานที่ร้องขอจาก Client คนนี้
$my_job_requests = [];
$sql_my_job_requests = "SELECT
                                cjr.request_id,
                                cjr.title,
                                cjr.description,
                                cjr.budget,
                                cjr.deadline,
                                cjr.posted_date,
                                jc.category_name,
                                cjr.status
                            FROM client_job_requests AS cjr
                            LEFT JOIN job_categories AS jc ON cjr.category_id = jc.category_id
                            WHERE cjr.client_id = ?
                            ORDER BY cjr.posted_date DESC";

$stmt_my_job_requests = $condb->prepare($sql_my_job_requests);
if ($stmt_my_job_requests === false) {
    error_log("SQL Prepare Error (my_job_requests): " . $condb->error);
} else {
    $stmt_my_job_requests->bind_param("i", $client_id);
    $stmt_my_job_requests->execute();
    $result_my_job_requests = $stmt_my_job_requests->get_result();
    $my_job_requests = $result_my_job_requests->fetch_all(MYSQLI_ASSOC);
    $stmt_my_job_requests->close();
}


$condb->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelLink | Client Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
        * {
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400;
        }

        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%);
            color: #2c3e50;
            overflow-x: hidden;
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4);
        }

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .text-gradient-light {
            background: linear-gradient(45deg, #87ceeb, #add8e6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .pixellink-logo {
            font-weight: 700;
            font-size: 2.25rem;
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo b {
            color: #0d96d2;
        }

        .card-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .hero-section {
            background-image: url('../dist/img/cover.png');
            background-size: cover;
            background-position: center;
            position: relative;
            z-index: 1;
            padding: 8rem 0;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .feature-icon {
            color: #0d96d2;
            transition: transform 0.3s ease;
        }

        .card-item:hover .feature-icon {
            transform: translateY(-3px);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: 6rem 0;
            }

            .hero-section h1 {
                font-size: 2.8rem;
            }

            .hero-section p {
                font-size: 1rem;
            }

            .hero-section .space-x-0 {
                flex-direction: column;
                gap: 1rem;
            }

            .hero-section .btn-primary,
            .hero-section .btn-secondary {
                width: 90%;
                max-width: none;
                font-size: 0.9rem;
                padding: 0.75rem 1.25rem;
            }

            .pixellink-logo {
                font-size: 1.6rem;
            }

            .navbar .px-5 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .navbar .py-2 {
                padding-top: 0.3rem;
                padding-bottom: 0.3rem;
            }

            h2 {
                font-size: 1.8rem;
            }

            .card-item {
                border-radius: 0.75rem;
                padding: 1rem;
            }

            .card-image {
                height: 160px;
            }

            .sm\:grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .flex-col.sm\:flex-row {
                flex-direction: column;
            }

            .flex-col.sm\:flex-row>*:not(:last-child) {
                margin-bottom: 1rem;
            }

            .md\:mb-0 {
                margin-bottom: 1rem;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero-section h1 {
                font-size: 2.2rem;
            }

            .hero-section p {
                font-size: 0.875rem;
            }

            .pixellink-logo {
                font-size: 1.4rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .p-10 {
                padding: 1.5rem;
            }

            .card-item {
                padding: 0.75rem;
            }

            .card-image {
                height: 120px;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

    <nav class="navbar p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php" class="transition duration-300 hover:opacity-80">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12">
            </a>
            <div class="space-x-2 sm:space-x-4 flex items-center">
                <span class="text-gray-700 font-medium">สวัสดี, <?= htmlspecialchars($client_data['first_name'] ?? 'Client') ?>!</span>
                <a href="../logout.php"
                    class="btn-secondary px-3 py-1.5 sm:px-5 sm:py-2 rounded-lg font-medium shadow-lg">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <header class="hero-section flex-grow flex items-center justify-center">
        <div
            class="text-center text-white p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl animate-fade-in relative z-10 mx-4">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extralight mb-4 md:mb-6 text-gradient-light drop-shadow-lg leading-tight">
                ยินดีต้อนรับสู่แดชบอร์ดลูกค้า</h1>
            <p class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 drop-shadow-md font-light">
                จัดการคำของานของคุณ ค้นหานักออกแบบ และติดตามโครงการของคุณได้อย่างง่ายดาย</p>
            <div class="space-x-0 sm:space-x-4 flex flex-col sm:flex-row justify-center items-center">
                <a href="#my-job-requests"
                    class="btn-primary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto mb-3 sm:mb-0">
                    ดูคำของานของฉัน <i class="fas fa-briefcase ml-2"></i>
                </a>
                <a href="new_job_request.php"
                    class="btn-secondary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto">
                    สร้างคำของานใหม่ <i class="fas fa-plus-circle ml-2"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- <section id="user-profile" class="py-12 md:py-16 bg-white">
        <div class="container mx-auto px-4 md:px-6">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-8 md:mb-10 text-center text-gradient">
                ข้อมูลโปรไฟล์</h2>
            <div class="card-item max-w-2xl mx-auto p-6 md:p-8 flex flex-col items-center">
                <img src="<?= htmlspecialchars($client_data['profile_picture_url'] ?? '../dist/img/default-avatar.png') ?>" 
                     alt="Profile Picture" 
                     class="w-32 h-32 rounded-full object-cover mb-6 border-4 border-blue-200 shadow-md">
                <div class="text-center">
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">
                        <?= htmlspecialchars($client_data['first_name'] . ' ' . $client_data['last_name'] ?? 'ไม่ระบุชื่อ') ?>
                    </h3>
                    <p class="text-base md:text-lg text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-blue-500"></i> Username: <?= htmlspecialchars($client_data['username'] ?? 'ไม่ระบุ') ?>
                    </p>
                    <p class="text-base md:text-lg text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2 text-blue-500"></i> Email: <?= htmlspecialchars($client_data['email'] ?? 'ไม่ระบุ') ?>
                    </p>
                    <p class="text-base md:text-lg text-gray-700 mb-4">
                        <i class="fas fa-phone mr-2 text-blue-500"></i> Phone: <?= htmlspecialchars($client_data['phone_number'] ?? 'ไม่ระบุ') ?>
                    </p>
                    <a href="edit_profile.php" class="btn-primary px-4 py-2 rounded-lg font-medium shadow-lg">
                        แก้ไขโปรไฟล์ <i class="fas fa-edit ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </section> -->

    <section id="my-job-requests" class="py-12 md:py-16 bg-gradient-to-br from-blue-50 to-gray-50">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">
                    คำของานของคุณ</h2>
                <a href="new_job_request.php"
                    class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    สร้างคำของานใหม่ <i class="fas fa-plus-circle ml-2"></i>
                </a>
            </div>

            <?php if (empty($my_job_requests)) : ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative text-center">
                <span class="block sm:inline">คุณยังไม่มีคำของานในขณะนี้</span>
            </div>
            <?php else : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php foreach ($my_job_requests as $request) : ?>
                <div class="card-item animate-card-appear">
                    <img src="https://source.unsplash.com/400x250/?design-project,<?= urlencode($request['category_name'] ?? 'creative-work') ?>"
                        alt="งานที่ร้องขอ: <?= htmlspecialchars($request['title']) ?>" class="card-image"
                        onerror="this.onerror=null;this.src='../dist/img/pdpa02.jpg';">
                    <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                        <div>
                            <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-1 md:mb-2 line-clamp-2">
                                <?= htmlspecialchars($request['title']) ?></h3>
                            <p class="text-xs md:text-sm text-gray-500 mb-2 md:mb-4">
                                <i class="fas fa-tag mr-1 text-blue-500"></i> หมวดหมู่: <span
                                    class="font-normal"><?= htmlspecialchars($request['category_name'] ?? 'ไม่ระบุ') ?></span>
                            </p>
                            <p class="text-sm md:text-base text-gray-700 mb-2 md:mb-4 line-clamp-3 font-light">
                                <?= htmlspecialchars($request['description']) ?></p>
                        </div>
                        <div class="mt-2 md:mt-4">
                            <p class="text-base md:text-lg font-semibold text-purple-700 mb-1 md:mb-2">งบประมาณ:
                                <?= htmlspecialchars($request['budget'] ?? 'ไม่ระบุ') ?></p>
                            <?php if (!empty($request['deadline'])) : ?>
                            <p class="text-xs text-gray-500 mb-2">กำหนดส่ง: <span
                                    class="font-light"><?= date('d M Y', strtotime($request['deadline'])) ?></span></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mb-2 md:mb-4">สถานะ: <span
                                    class="font-medium text-green-600"><?= htmlspecialchars(ucfirst($request['status'])) ?></span></p>
                            <p class="text-xs text-gray-500 mb-2 md:mb-4">ประกาศเมื่อ: <span
                                    class="font-light"><?= date('d M Y', strtotime($request['posted_date'])) ?></span>
                            </p>
                            <a href="request_detail.php?id=<?= $request['request_id'] ?>"
                                class="btn-primary px-4 py-2 sm:px-5 sm:py-2 rounded-lg font-medium shadow-lg">
                                ดูรายละเอียด <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="features" class="py-12 md:py-16 bg-gradient-to-br from-gray-50 to-blue-50">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-semibold mb-8 md:mb-12 text-gradient">PixelLink:
                พันธมิตรทางธุรกิจของคุณ</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-10">
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-search fa-3x feature-icon mb-4 md:mb-6"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">ค้นหานักออกแบบที่เหมาะสม
                    </h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">
                        เข้าถึงเครือข่ายนักออกแบบผู้เชี่ยวชาญจากหลากหลายสาขา พร้อมโปรไฟล์และผลงานที่เชื่อถือได้
                        เพื่อคัดเลือกบุคลากรที่ตรงกับความต้องการของคุณ</p>
                </div>
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-lightbulb fa-3x feature-icon mb-4 md:mb-6" style="color: #0d96d2;"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">สร้างสรรค์นวัตกรรมดีไซน์
                    </h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">
                        แพลตฟอร์มที่สนับสนุนการทำงานร่วมกันอย่างมีประสิทธิภาพระหว่างผู้ว่าจ้างและนักออกแบบ
                        เพื่อสร้างสรรค์ผลงานที่โดดเด่นและตอบโจทย์ธุรกิจ</p>
                </div>
                <div class="card-item p-6 md:p-8 flex flex-col items-center">
                    <i class="fas fa-handshake fa-3x feature-icon mb-4 md:mb-6" style="color: #28a745;"></i>
                    <h3 class="text-xl md:text-2xl font-semibold mb-2 md:mb-4 text-gray-800">ความร่วมมือที่โปร่งใส</h3>
                    <p class="text-sm md:text-base text-gray-600 font-light">ระบบจัดการโครงการ, การสื่อสาร,
                        และการชำระเงินที่ครบวงจร เพื่อความราบรื่นและโปร่งใสในทุกขั้นตอนของกระบวนการทำงาน</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 md:py-16 bg-gradient-to-r from-blue-700 to-indigo-800 text-white text-center">
        <div class="container mx-auto px-4 md:px-6">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4 md:mb-6 drop-shadow-lg leading-tight">
                ยกระดับโครงการของคุณ<br>ด้วย PixelLink วันนี้</h2>
            <p class="text-lg sm:text-xl md:text-2xl mb-6 md:mb-8 opacity-95 drop-shadow-md font-light">
                ไม่ว่าคุณจะเป็นองค์กรที่กำลังมองหานักออกแบบ หรือนักออกแบบมืออาชีพที่กำลังแสวงหาโอกาสใหม่ๆ</p>
            <div class="space-x-0 sm:space-x-4 flex flex-col sm:flex-row justify-center items-center">
                <a href="new_job_request.php"
                    class="btn-primary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto mb-3 sm:mb-0">สร้างคำของานใหม่</a>
                <a href="#my-job-requests"
                    class="px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold bg-white text-blue-700 shadow-xl hover:bg-gray-100 hover:scale-105 transition duration-300 w-full sm:w-auto">ดูคำของานของฉัน</a>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-gray-300 py-8">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <a href="main.php"
                    class="text-2xl sm:text-3xl font-bold pixellink-logo mb-4 md:mb-0 transition duration-300 hover:opacity-80">Pixel<b>Link</b></a>
                <div class="flex flex-wrap justify-center space-x-2 md:space-x-6 text-sm md:text-base footer-links">
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เกี่ยวกับเรา</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">ติดต่อเรา</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เงื่อนไขการใช้งาน</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">นโยบายความเป็นส่วนตัว</a>
                </div>
            </div>
            <hr class="border-gray-700 my-6">
            <p class="text-xs md:text-sm font-light">&copy; <?php echo date('Y'); ?> PixelLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Optional: JavaScript for smooth scrolling to sections
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Optional: Hero section animation (fade in)
        document.addEventListener('DOMContentLoaded', () => {
            const heroContent = document.querySelector('.animate-fade-in');
            heroContent.style.opacity = '0';
            setTimeout(() => {
                heroContent.style.transition = 'opacity 1s ease-out';
                heroContent.style.opacity = '1';
            }, 100);

            // Optional: Animate cards on scroll
            const cards = document.querySelectorAll('.animate-card-appear');
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            entry.target.style.transition =
                                'opacity 0.6s ease-out, transform 0.6s ease-out';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 200); // Slight delay for each card
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            cards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>

</html>