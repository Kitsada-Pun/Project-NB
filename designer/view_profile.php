<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล (ใช้ mysqli) ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$condb->set_charset("utf8mb4");

// ดึง user_id จาก URL (GET parameter)
$user_id_to_view = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$designer_name = $_SESSION['username'] ?? $_SESSION['full_name'];
$profile_data = null;
$job_postings_for_profile = [];
$loggedInUserName = $_SESSION['full_name'] ?? 'ผู้ใช้งาน';

if ($user_id_to_view > 0) {
    // ดึงข้อมูลโปรไฟล์จากตาราง 'profiles' และ 'users'
    $sql_profile = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone_number, u.username
                    FROM profiles p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.user_id = ?";
    $stmt_profile = $condb->prepare($sql_profile);
    if ($stmt_profile) {
        $stmt_profile->bind_param("i", $user_id_to_view);
        $stmt_profile->execute();
        $result_profile = $stmt_profile->get_result();
        $profile_data = $result_profile->fetch_assoc();
        $stmt_profile->close();
    }

    // ===================================================================== //
    // ====== LOGIC ที่แก้ไข: JOIN ตาราง uploaded_files เพื่อดึง Path รูป ====== //
    // ===================================================================== //
    $sql_job_postings_for_profile = "SELECT
                                        jp.post_id,
                                        jp.title,
                                        jp.description,
                                        jp.price_range,
                                        jp.posted_date,
                                        u.first_name,
                                        u.last_name,
                                        jc.category_name,
                                        uf.file_path AS job_image_path -- ดึง file_path จาก uploaded_files มาตั้งชื่อว่า job_image_path
                                    FROM job_postings AS jp
                                    JOIN users AS u ON jp.designer_id = u.user_id 
                                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id -- JOIN โดยใช้ main_image_id ไปหา file_id
                                    WHERE jp.designer_id = ? AND jp.status = 'active'
                                    ORDER BY jp.posted_date DESC";

    $stmt_job_postings = $condb->prepare($sql_job_postings_for_profile);
    if ($stmt_job_postings) {
        $stmt_job_postings->bind_param("i", $user_id_to_view);
        $stmt_job_postings->execute();
        $result_jobs = $stmt_job_postings->get_result();
        $job_postings_for_profile = $result_jobs->fetch_all(MYSQLI_ASSOC);
        $stmt_job_postings->close();
    }
}

$condb->close();

// กำหนดค่าเริ่มต้นสำหรับข้อมูลที่จะแสดงผล
$display_name = trim(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? '')) ?: ($profile_data['username'] ?? 'ไม่ระบุชื่อ');
$display_email = $profile_data['email'] ?? 'ไม่ระบุอีเมล';
$display_tel = $profile_data['phone_number'] ?? 'ไม่ระบุเบอร์โทรศัพท์';
$display_rating = 'ยังไม่มีคะแนน';
$display_address = $profile_data['address'] ?? 'ไม่ระบุที่อยู่';
$display_company_name = $profile_data['company_name'] ?? 'ไม่ระบุบริษัท';
$display_bio = $profile_data['bio'] ?? 'ยังไม่มีประวัติ';
$display_portfolio_url = $profile_data['portfolio_url'] ?? null;
$display_skills = !empty($profile_data['skills']) ? explode(',', $profile_data['skills']) : [];
$display_profile_pic = !empty($profile_data['profile_picture_url']) ? $profile_data['profile_picture_url'] : '../dist/img/default_profile.png';
$loggedInUserName = ''; // Initialize variable for logged-in user's name

// Fetch logged-in user's name if session is active
if (isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
    if (empty($loggedInUserName)) {
        $user_id = $_SESSION['user_id'];
        $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_user);
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows === 1) {
                $user_info = $result_user->fetch_assoc();
                $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
            }
            $stmt_user->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของ <?= htmlspecialchars($display_name) ?> | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
        }

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .carousel-content {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: calc(100% - 1rem);
            gap: 1.5rem;
            overflow-x: scroll;
            scroll-behavior: smooth;
            scrollbar-width: none;
        }

        .carousel-content::-webkit-scrollbar {
            display: none;
        }

        .card-item {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .carousel-button {
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 0.75rem 0.5rem;
            cursor: pointer;
            z-index: 10;
            border-radius: 9999px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .carousel-button:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }

        .carousel-button.left {
            left: 0;
        }

        .carousel-button.right {
            right: 0;
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

        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
        }

        @media (min-width: 768px) {
            .carousel-content {
                grid-auto-columns: calc(50% - 0.75rem);
            }
        }

        @media (min-width: 1024px) {
            .carousel-content {
                grid-auto-columns: calc(33.333% - 1rem);
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                    <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                    <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
                <?php else : ?>
                    <a href="login.php" class="font-semibold text-slate-600 hover:text-blue-600 transition-colors">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn-primary text-white px-5 py-2 rounded-lg font-semibold shadow-md">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8">
        <?php if (!$profile_data): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded-lg text-center">ไม่พบข้อมูลโปรไฟล์</div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-10 mb-8">
                    <img src="<?= htmlspecialchars($display_profile_pic) ?>" alt="รูปโปรไฟล์" class="w-32 h-32 md:w-40 md:h-40 rounded-full object-cover shadow-lg border-4 border-white">
                    <div class="text-center md:text-left flex-grow">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900"><?= htmlspecialchars($display_name) ?></h1>
                        <p class="text-md text-gray-600 mt-2"><i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($display_email) ?></p>
                        <p class="text-md text-gray-600"><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($display_tel) ?></p>
                        <p class="text-md text-gray-600"><i class="fas fa-building mr-2"></i><?= htmlspecialchars($display_company_name) ?></p>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-gradient mb-4">เกี่ยวกับฉัน</h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($display_bio)) ?></p>
                </div>

                <?php if (!empty($display_skills)): ?>
                    <div class="mb-8">
                        <h2 class="text-2xl font-semibold text-gradient mb-4">ทักษะ</h2>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($display_skills as $skill): ?>
                                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full"><?= htmlspecialchars(trim($skill)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-gradient mb-4">โพสต์ประกาศงานของคุณ</h2>
                    <?php if (empty($job_postings_for_profile)): ?>
                        <div class="bg-blue-100 text-blue-700 px-4 py-3 rounded-lg text-center">ยังไม่มีงานที่ประกาศ</div>
                    <?php else: ?>
                        <div class="carousel-container relative">
                            <button id="prevBtn" class="carousel-button left"><i class="fas fa-chevron-left"></i></button>
                            <div class="carousel-content p-2" id="carouselContent">
                                <?php foreach ($job_postings_for_profile as $job): ?>
                                    <div class="card-item flex flex-col">
                                        <?php
                                        // --- LOGIC การแสดงผลรูปภาพ ---
                                        // ใช้ 'job_image_path' ที่ได้จากการ JOIN
                                        // ตรวจสอบด้วยว่าไฟล์มีอยู่จริงหรือไม่
                                        $image_source = !empty($job['job_image_path']) && file_exists(htmlspecialchars($job['job_image_path']))
                                            ? htmlspecialchars($job['job_image_path'])
                                            : '../dist/img/pdpa02.jpg'; // รูปสำรอง
                                        ?>
                                        <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job['title']) ?>" class="card-image">
                                        <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900 line-clamp-2"><?= htmlspecialchars($job['title']) ?></h3>
                                                <p class="text-sm text-gray-500 mb-2">หมวดหมู่: <?= htmlspecialchars($job['category_name'] ?? 'ไม่ระบุ') ?></p>
                                                <p class="text-sm text-gray-700 line-clamp-3 font-light"><?= htmlspecialchars($job['description']) ?></p>
                                            </div>
                                            <div class="mt-4">
                                                <p class="text-lg font-semibold text-green-700">ราคา: <?= htmlspecialchars($job['price_range'] ?? 'สอบถาม') ?></p>
                                                <p class="text-xs text-gray-500">ประกาศเมื่อ: <?= date('d M Y', strtotime($job['posted_date'])) ?></p>
                                                <a href="../job_detail.php?id=<?= $job['post_id'] ?>&type=posting" class="mt-2 inline-block btn-primary text-white px-4 py-2 rounded-lg font-medium text-sm shadow-lg w-full text-center">ดูรายละเอียด</a>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button id="nextBtn" class="carousel-button right"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-900 text-gray-300 py-8 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm font-light">&copy; <?php echo date('Y'); ?> PixelLink. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const carouselContent = document.getElementById('carouselContent');
            if (!carouselContent) return; // ถ้าไม่มี carousel ก็ไม่ต้องทำอะไรต่อ

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            function updateButtons() {
                const scrollWidth = carouselContent.scrollWidth;
                const clientWidth = carouselContent.clientWidth;
                const scrollLeft = carouselContent.scrollLeft;

                // ถ้าเนื้อหาไม่ล้น ก็ซ่อนปุ่มทั้งสอง
                if (scrollWidth <= clientWidth) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                    return;
                }

                prevBtn.style.display = scrollLeft > 0 ? 'flex' : 'none';
                nextBtn.style.display = scrollLeft < (scrollWidth - clientWidth - 1) ? 'flex' : 'none';
            }

            prevBtn.addEventListener('click', () => {
                const cardWidth = carouselContent.querySelector('.card-item').offsetWidth;
                carouselContent.scrollBy({
                    left: -(cardWidth + 24),
                    behavior: 'smooth'
                }); // 24 คือ gap
            });

            nextBtn.addEventListener('click', () => {
                const cardWidth = carouselContent.querySelector('.card-item').offsetWidth;
                carouselContent.scrollBy({
                    left: cardWidth + 24,
                    behavior: 'smooth'
                });
            });

            // ใช้ Timeout เพื่อให้แน่ใจว่าการ scroll เสร็จสิ้นก่อน update ปุ่ม
            carouselContent.addEventListener('scroll', () => {
                setTimeout(updateButtons, 250);
            });
            window.addEventListener('resize', updateButtons);

            updateButtons(); // เรียกใช้ครั้งแรกตอนโหลดหน้า
        });
    </script>
</body>

</html>