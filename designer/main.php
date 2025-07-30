<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ และเป็น 'designer'
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
}
$condb->set_charset("utf8mb4");

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$designer_id = $_SESSION['user_id'];
$loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';

// --- (ส่วน PHP สำหรับดึง $assigned_jobs เหมือนเดิม) ---
$assigned_jobs = [];
// ... (คุณสามารถใส่โค้ดดึง $assigned_jobs เดิมของคุณไว้ตรงนี้ได้เลย) ...


// ================================================================================= //
// ========= จุดที่ 1: แก้ไข SQL ของ $available_jobs เพื่อดึง Path รูปภาพ ========= //
// ================================================================================= //
$available_jobs = [];
$sql_available_jobs = "SELECT
                            jp.post_id,
                            jp.title,
                            jp.description,
                            jp.price_range,
                            jp.posted_date,
                            u.first_name,
                            u.last_name,
                            jc.category_name,
                            uf.file_path AS job_image_path -- << เพิ่มบรรทัดนี้เพื่อดึง Path รูป
                        FROM job_postings AS jp
                        JOIN users AS u ON jp.designer_id = u.user_id
                        LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                        LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id -- << เพิ่มบรรทัดนี้เพื่อ JOIN ตาราง
                        WHERE jp.status = 'active' 
                        ORDER BY jp.posted_date DESC
                        LIMIT 12";

$result_available_jobs = $condb->query($sql_available_jobs);
if ($result_available_jobs) {
    $available_jobs = $result_available_jobs->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("SQL Error (available_jobs): " . $condb->error);
}

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
$condb->close();

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Designer | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
        * { font-family: 'Kanit', sans-serif; font-style: normal; font-weight: 400; }
        body { background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%); color: #2c3e50; overflow-x: hidden; }
        .navbar { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
        .btn-primary { background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3); }
        .btn-primary:hover { background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5); }
        .btn-danger { background-color: #ef4444; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background-color: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4); }
        .btn-secondary { background-color: #6c757d; color: white; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2); }
        .btn-secondary:hover { background-color: #5a6268; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4); }
        .text-gradient { background: linear-gradient(45deg, #0a5f97, #0d96d2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .pixellink-logo { font-weight: 700; font-size: 2.25rem; background: linear-gradient(45deg, #0a5f97, #0d96d2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .pixellink-logo b { color: #0d96d2; }
        .card-item { background: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); transition: all 0.3s ease; flex-shrink: 0; }
        .card-item:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        .card-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; border-top-left-radius: 1rem; border-top-right-radius: 1rem; }
        .feature-icon { color: #0d96d2; transition: transform 0.3s ease; }
        .fade-in-slide-up { opacity: 0; transform: translateY(20px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-slide-up.is-visible { opacity: 1; transform: translateY(0); }

    @media (max-width: 768px) {
        .hero-section { padding: 6rem 0; }
        .hero-section h1 { font-size: 2.8rem; }
        .hero-section p { font-size: 1rem; }
        .hero-section .space-x-0 { flex-direction: column; gap: 1rem; }
        .hero-section .btn-primary, .hero-section .btn-secondary { width: 90%; max-width: none; font-size: 0.9rem; padding: 0.75rem 1.25rem; }
        .pixellink-logo { font-size: 1.6rem; }
        .navbar .px-5 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .navbar .py-2 { padding-top: 0.3rem; padding-bottom: 0.3rem; }
        h2 { font-size: 1.8rem; }
        .card-item { border-radius: 0.75rem; padding: 1rem; }
        .card-image { height: 160px; }
        .sm\:grid-cols-2 { grid-template-columns: 1fr; }
        .flex-col.sm\:flex-row>*:not(:last-child) { margin-bottom: 1rem; }
        .md\:mb-0 { margin-bottom: 1rem; }
        .footer-links { flex-direction: column; gap: 0.5rem; }
    }
        
    @media (max-width: 480px) {
        .hero-section h1 { font-size: 2.2rem; }
        .hero-section p { font-size: 0.875rem; }
        .pixellink-logo { font-size: 1.4rem; }
        h2 { font-size: 1.5rem; }
        .container { padding-left: 1rem; padding-right: 1rem; }
        .px-6 { padding-left: 1rem; padding-right: 1rem; }
        .p-10 { padding: 1.5rem; }
        .card-item { padding: 0.75rem; }
        .card-image { height: 120px; }
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
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <header class="hero-section flex-grow flex items-center justify-center text-white py-16 relative overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../dist/img/cover.png');">
        </div>

        <div class="text-center text-white p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl animate-fade-in relative z-10 mx-4">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extralight mb-4 md:mb-6 text-gradient-light drop-shadow-lg leading-tight">
                พื้นที่ทำงานนักออกแบบ
            </h1>
            <p class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 font-light">
                จัดการโครงการของคุณ, ค้นหางานใหม่, และนำเสนอผลงานสู่ผู้ว่าจ้าง
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 sm:gap-4 flex-wrap">
                <a href="#assigned-jobs" class="
                    bg-emerald-500 text-white
                    px-6 py-3 sm:px-8 sm:py-4
                    text-base sm:text-lg rounded-lg font-semibold
                    shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300
                    w-full sm:w-auto mb-3 sm:mb-0
                    hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-300
                    whitespace-nowrap
                ">
                    <i class="fas fa-tasks mr-2"></i> งานที่ได้รับมอบหมาย
                </a>
                <a href="#available-jobs" class="
                    bg-blue-500 text-white
                    px-6 py-3 sm:px-8 sm:py-4
                    text-base sm:text-lg rounded-lg font-semibold
                    shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300
                    w-full sm:w-auto mb-3 sm:mb-0
                    hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-300
                    whitespace-nowrap
                ">
                    <i class="fas fa-search mr-2"></i> หางานใหม่
                </a>
                <a href="my_projects.php" class="
                    bg-gray-200 text-gray-800
                    px-6 py-3 sm:px-8 sm:py-4
                    text-base sm:text-lg rounded-lg font-semibold
                    shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300
                    w-full sm:w-auto mb-3 sm:mb-0
                    hover:bg-gray-300 focus:outline-none focus:ring-4 focus:ring-gray-300
                    whitespace-nowrap
                ">
                    <i class="fas fa-folder-open mr-2"></i> โปรเจกต์ของฉัน
                </a>
                <a href="post_portfolio.php" class="
                    bg-yellow-500 text-white
                    px-6 py-3 sm:px-8 sm:py-4
                    text-base sm:text-lg rounded-lg font-semibold
                    shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300
                    w-full sm:w-auto mb-3 sm:mb-0
                    hover:bg-yellow-600 focus:outline-none focus:ring-4 focus:ring-yellow-300
                    whitespace-nowrap
                ">
                    <i class="fas fa-upload mr-2"></i> แชร์ผลงานของคุณ
                </a>
                <a href="create_job_post.php" class="
                    bg-purple-600 text-white
                    px-6 py-3 sm:px-8 sm:py-4
                    text-base sm:text-lg rounded-lg font-semibold
                    shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300
                    w-full sm:w-auto
                    hover:bg-purple-700 focus:outline-none focus:ring-4 focus:ring-purple-300
                    whitespace-nowrap
                ">
                    <i class="fas fa-bullhorn mr-2"></i> โพสต์บริการ
                </a>
            </div>
        </div>
    </header>

    <section id="assigned-jobs" class="py-12 md:py-16 bg-gradient-to-br from-blue-50 to-gray-50">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2
                    class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">
                    งานที่ได้รับมอบหมาย
                </h2>
                <a href="job_listings.php?type=assigned"
                    class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    ดูทั้งหมด <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <?php if (empty($assigned_jobs)): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative text-center">
                    <span class="block sm:inline">ยังไม่มีงานที่ได้รับมอบหมายในขณะนี้</span>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php foreach ($assigned_jobs as $job): ?>
                        <div class="card-item animate-card-appear">
                            <img src="https://source.unsplash.com/400x250/?design-project,<?= urlencode($job['category_name'] ?? 'graphic-design') ?>"
                                alt="งานที่ได้รับมอบหมาย: <?= htmlspecialchars($job['title']) ?>" class="card-image"
                                onerror="this.onerror=null;this.src='../dist/img/pdpa02.jpg';">
                            <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-1 md:mb-2 line-clamp-2">
                                        <?= htmlspecialchars($job['title']) ?>
                                    </h3>
                                    <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">ผู้ว่าจ้าง: <span
                                            class="font-medium text-blue-700"><?= htmlspecialchars($job['client_first_name'] . ' ' . $job['client_last_name']) ?></span>
                                    </p>
                                    <p class="text-xs md:text-sm text-gray-500 mb-2 md:mb-4">
                                        <i class="fas fa-tag mr-1 text-blue-500"></i> หมวดหมู่: <span
                                            class="font-normal"><?= htmlspecialchars($job['category_name'] ?? 'ไม่ระบุ') ?></span>
                                    </p>
                                    <p class="text-sm md:text-base text-gray-700 mb-2 md:mb-4 line-clamp-3 font-light">
                                        <?= htmlspecialchars($job['description']) ?>
                                    </p>
                                </div>
                                <div class="mt-2 md:mt-4">
                                    <p class="text-base md:text-lg font-semibold text-green-700 mb-1 md:mb-2">สถานะ:
                                        <span
                                            class="text-blue-600"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $job['job_status']))) ?></span>
                                    </p>
                                    <p class="text-xs text-gray-500 mb-2 md:mb-4">มอบหมายเมื่อ: <span
                                            class="font-light"><?= date('d M Y', strtotime($job['posted_date'])) ?></span></p>
                                    <a href="../job_detail.php?id=<?= $job['post_id'] ?>&type=posting"
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

    <section id="available-jobs" class="py-12 md:py-16 bg-white">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2
                    class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">
                    งานที่เปิดรับ (สำหรับคุณ)
                </h2>
                <a href="../job_listings.php?type=postings"
                    class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    ดูทั้งหมด <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <?php if (empty($available_jobs)): ?>
                <div class="bg-blue-100 text-blue-700 p-4 rounded-lg text-center">ยังไม่มีงานที่เปิดรับในขณะนี้</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php foreach ($available_jobs as $job): ?>
                        <div class="card-item flex flex-col">
                            
                            <?php
                            $image_source = !empty($job['job_image_path']) && file_exists(htmlspecialchars($job['job_image_path']))
                                ? htmlspecialchars($job['job_image_path'])
                                : '../dist/img/pdpa02.jpg'; // <-- รูปสำรอง
                            ?>
                            <a href="../job_detail.php?id=<?= $job['post_id'] ?>&type=posting">
                                <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job['title']) ?>" class="card-image">
                            </a>

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
            <?php endif; ?>
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
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Animate header content on load
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