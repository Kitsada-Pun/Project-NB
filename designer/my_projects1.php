<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ และเป็น 'designer'
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    // ถ้าไม่ได้ล็อกอินหรือไม่ใช่ designer ให้เปลี่ยนเส้นทางไปหน้า login
    header("Location: ../login.php");
    exit();
}

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

// ดึงข้อมูลผู้ใช้ปัจจุบัน (Designer)
$designer_id = $_SESSION['user_id'];
$designer_name = $_SESSION['username'] ?? $_SESSION['full_name']; // ใช้ full_name ถ้ามี, ไม่งั้นใช้ username

// --- PHP Logic สำหรับดึงงานที่นักออกแบบได้รับมอบหมายทั้งหมด ---
$my_projects = [];
$sql_my_projects = "SELECT
                            jp.post_id,
                            jp.title,
                            jp.description,
                            jp.price_range,
                            jp.posted_date,
                            jp.status AS job_status,
                            u.first_name AS client_first_name,
                            u.last_name AS client_last_name,
                            jc.category_name
                        FROM job_postings AS jp
                        JOIN users AS u ON jp.client_id = u.user_id
                        LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                        WHERE jp.designer_id = ?
                        ORDER BY jp.posted_date DESC";

$stmt_my_projects = $condb->prepare($sql_my_projects);
if ($stmt_my_projects === false) {
    error_log("SQL Prepare Error (my_projects): " . $condb->error);
} else {
    $stmt_my_projects->bind_param("i", $designer_id);
    $stmt_my_projects->execute();
    $result_my_projects = $stmt_my_projects->get_result();
    $my_projects = $result_my_projects->fetch_all(MYSQLI_ASSOC);
    $stmt_my_projects->close();
}

$condb->close();

// จัดกลุ่มโปรเจกต์ตามสถานะ
$grouped_projects = [
    'pending_assignment' => [], // งานที่เสนอราคาไปแล้วแต่ยังไม่ได้รับการมอบหมาย
    'in_progress' => [],        // งานที่กำลังดำเนินการ
    'pending_review' => [],     // งานที่ส่งให้ลูกค้าตรวจสอบแล้ว
    'completed' => [],          // งานที่เสร็จสมบูรณ์
    'cancelled' => [],          // งานที่ถูกยกเลิก
    'all' => $my_projects       // งานทั้งหมด (สำหรับแท็บ "ทั้งหมด")
];

foreach ($my_projects as $project) {
    if (isset($grouped_projects[$project['job_status']])) {
        $grouped_projects[$project['job_status']][] = $project;
    } else {
        // กรณีสถานะอื่นๆ ที่อาจไม่ตรงกับด้านบน
        $grouped_projects['all'][] = $project; // ยังคงอยู่ในแท็บทั้งหมด
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรเจกต์ของฉัน | Designer Dashboard</title>
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

        .tab-button.active {
            background-color: #0d96d2;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(13, 150, 210, 0.3);
        }

        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button:not(.active):hover {
            background-color: #e0e7ff;
            color: #0a5f97;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0;
            }

            .hero-section h1 {
                font-size: 2.8rem;
            }

            .hero-section p {
                font-size: 1rem;
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
            .tab-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
            .tab-button {
                width: calc(50% - 0.5rem);
                margin-bottom: 0.5rem;
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
            .tab-button {
                width: 100%;
                margin-bottom: 0.5rem;
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
                <span class="text-gray-700 font-medium">สวัสดี, <?= htmlspecialchars($designer_name) ?>!</span>
                <a href="../logout.php"
                    class="btn-secondary px-3 py-1.5 sm:px-5 sm:py-2 rounded-lg font-medium shadow-lg">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <header class="hero-section flex-grow flex items-center justify-center bg-blue-600 text-white py-12" style="background-image: none;">
        <div class="text-center p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl relative z-10 mx-4 bg-white bg-opacity-10">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extralight mb-4 md:mb-6 leading-tight">
                โปรเจกต์ของฉัน
            </h1>
            <p class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 font-light">
                ภาพรวมงานทั้งหมดที่คุณได้รับมอบหมายและสถานะปัจจุบัน
            </p>
            <div class="space-x-0 sm:space-x-4 flex flex-col sm:flex-row justify-center items-center">
                <a href="main.php"
                    class="btn-primary px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-xl hover:scale-105 w-full sm:w-auto mb-3 sm:mb-0">
                    กลับหน้าหลัก <i class="fas fa-home ml-2"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="py-12 md:py-16">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex justify-center mb-8 md:mb-10">
                <div class="tab-buttons bg-white p-2 rounded-xl shadow-lg flex flex-row flex-wrap gap-2 sm:gap-4">
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base active" data-tab="all">
                        <i class="fas fa-list-ul mr-2"></i>ทั้งหมด
                    </button>
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base" data-tab="in_progress">
                        <i class="fas fa-spinner mr-2"></i>กำลังดำเนินการ
                    </button>
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base" data-tab="pending_review">
                        <i class="fas fa-hourglass-half mr-2"></i>รอลูกค้าตรวจสอบ
                    </button>
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base" data-tab="completed">
                        <i class="fas fa-check-circle mr-2"></i>เสร็จสิ้น
                    </button>
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base" data-tab="pending_assignment">
                        <i class="fas fa-clock mr-2"></i>รอมอบหมาย
                    </button>
                    <button class="tab-button px-4 py-2 rounded-lg text-gray-700 text-sm sm:text-base" data-tab="cancelled">
                        <i class="fas fa-times-circle mr-2"></i>ยกเลิก
                    </button>
                </div>
            </div>

            <?php if (empty($my_projects)) : ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative text-center">
                    <span class="block sm:inline">คุณยังไม่มีโปรเจกต์ที่ได้รับมอบหมายในขณะนี้</span>
                </div>
            <?php else : ?>
                <?php foreach ($grouped_projects as $status_key => $projects_list) : ?>
                <div id="<?= $status_key ?>-projects" class="project-list-container grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-12" style="<?= $status_key === 'all' ? '' : 'display: none;' ?>">
                    <?php if (empty($projects_list)) : ?>
                        <div class="bg-indigo-50 border border-indigo-300 text-indigo-700 px-4 py-3 rounded-lg relative text-center col-span-full">
                            <span class="block sm:inline">ไม่มีโปรเจกต์ในสถานะนี้</span>
                        </div>
                    <?php else : ?>
                        <?php foreach ($projects_list as $project) : ?>
                            <div class="card-item animate-card-appear">
                                <img src="https://source.unsplash.com/400x250/?design-project,<?= urlencode($project['category_name'] ?? 'graphic-design') ?>"
                                    alt="โปรเจกต์: <?= htmlspecialchars($project['title']) ?>" class="card-image"
                                    onerror="this.onerror=null;this.src='../dist/img/pdpa02.jpg';">
                                <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                    <div>
                                        <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-1 md:mb-2 line-clamp-2">
                                            <?= htmlspecialchars($project['title']) ?></h3>
                                        <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">ผู้ว่าจ้าง: <span
                                                class="font-medium text-blue-700"><?= htmlspecialchars($project['client_first_name'] . ' ' . $project['client_last_name']) ?></span>
                                        </p>
                                        <p class="text-xs md:text-sm text-gray-500 mb-2 md:mb-4">
                                            <i class="fas fa-tag mr-1 text-blue-500"></i> หมวดหมู่: <span
                                                class="font-normal"><?= htmlspecialchars($project['category_name'] ?? 'ไม่ระบุ') ?></span>
                                        </p>
                                        <p class="text-sm md:text-base text-gray-700 mb-2 md:mb-4 line-clamp-3 font-light">
                                            <?= htmlspecialchars($project['description']) ?></p>
                                    </div>
                                    <div class="mt-2 md:mt-4">
                                        <p class="text-base md:text-lg font-semibold text-green-700 mb-1 md:mb-2">สถานะ:
                                            <span class="text-blue-600"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $project['job_status']))) ?></span>
                                        </p>
                                        <p class="text-xs text-gray-500 mb-2 md:mb-4">มอบหมายเมื่อ: <span
                                                class="font-light"><?= date('d M Y', strtotime($project['posted_date'])) ?></span></p>
                                        <a href="../job_detail.php?id=<?= $project['post_id'] ?>&type=posting"
                                            class="btn-primary px-4 py-2 sm:px-5 sm:py-2 rounded-lg font-medium shadow-lg">
                                            ดูรายละเอียด <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-900 text-gray-300 py-8 mt-auto">
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
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('.tab-button');
            const projectContainers = document.querySelectorAll('.project-list-container');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const targetTab = this.dataset.tab;

                    // Hide all project containers
                    projectContainers.forEach(container => {
                        container.style.display = 'none';
                    });

                    // Show the target project container
                    const targetContainer = document.getElementById(targetTab + '-projects');
                    if (targetContainer) {
                        targetContainer.style.display = 'grid'; // Use grid for project cards
                        // Re-trigger animations for newly visible cards
                        targetContainer.querySelectorAll('.card-item').forEach(card => {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, 50); // Small delay for each card
                        });
                    }
                });
            });

            // Initial animation for 'all' tab when page loads
            const initialCards = document.getElementById('all-projects').querySelectorAll('.card-item');
            initialCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index); // Staggered animation
            });

            // Adjust hero section height on smaller screens if content is short
            const header = document.querySelector('header');
            if (window.innerHeight > document.body.clientHeight) {
                header.style.minHeight = 'calc(100vh - 120px)'; // Adjust based on navbar/footer height
            }
        });
    </script>
</body>

</html>