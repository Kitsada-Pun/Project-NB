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
// --- ดึงชื่อผู้ใช้ที่ล็อกอิน ---
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
// --- ดึงข้อมูลประกาศรับงานจากดีไซเนอร์อื่น ---
$job_postings_from_others = [];
$sql_job_postings = "SELECT
                        jp.post_id,
                        jp.title,
                        jp.description,
                        jp.price_range,
                        jp.posted_date,
                        u.first_name,
                        u.last_name,
                        jc.category_name,
                        uf.file_path AS job_image_path
                    FROM job_postings AS jp
                    JOIN users AS u ON jp.designer_id = u.user_id
                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                    WHERE jp.status = 'active'
                    ORDER BY jp.posted_date DESC
                    LIMIT 12";

$result_job_postings = $condb->query($sql_job_postings);
if ($result_job_postings) {
    $job_postings_from_others = $result_job_postings->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("SQL Error (job_postings_from_others): " . $condb->error);
}


$condb->close();

?>
<!DOCTYPE html>
<html lang="th">

<?php include '../includes/header.php'; ?>

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
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../dist/img/cover.png');"></div>
        <div class="text-center text-white p-6 md:p-10 rounded-xl shadow-2xl max-w-4xl relative z-10 mx-4">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extralight mb-4 md:mb-6 leading-tight">พื้นที่ทำงานนักออกแบบ</h1>
            <p class="text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed opacity-90 font-light">จัดการโครงการของคุณ, ค้นหางานใหม่, และนำเสนอผลงานสู่ผู้ว่าจ้าง</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 sm:gap-4 flex-wrap">
                <a href="#available-jobs" class="bg-blue-500 text-white px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 w-full sm:w-auto mb-3 sm:mb-0 hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-300 whitespace-nowrap">
                    <i class="fas fa-search mr-2"></i> หางานใหม่
                </a>
                <a href="create_job_post.php" class="bg-purple-600 text-white px-6 py-3 sm:px-8 sm:py-4 text-base sm:text-lg rounded-lg font-semibold shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 w-full sm:w-auto hover:bg-purple-700 focus:outline-none focus:ring-4 focus:ring-purple-300 whitespace-nowrap">
                    <i class="fas fa-bullhorn mr-2"></i> โพสต์บริการ
                </a>
            </div>
        </div>
    </header>


    <section id="available-jobs" class="py-12 md:py-16 bg-white">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 md:mb-10">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold text-gray-800 mb-4 sm:mb-0 text-center sm:text-left text-gradient">ประกาศรับงานจากดีไซเนอร์อื่น</h2>
                <a href="../job_listings.php?type=postings" class="btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-medium text-sm md:text-base">
                    ดูทั้งหมด <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <?php if (empty($job_postings_from_others)): ?>
                <div class="bg-blue-100 text-blue-700 p-4 rounded-lg text-center">ยังไม่มีประกาศรับงานในขณะนี้</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php foreach ($job_postings_from_others as $job): ?>
                        <div class="card-item flex flex-col">
                            <?php
                                $image_path = str_replace('../', '', $job['job_image_path']);
                                $image_source = (!empty($image_path) && file_exists($image_path)) ? htmlspecialchars($image_path) : '../dist/img/pdpa02.jpg';
                            ?>
                            <a href="../job_detail.php?id=<?= $job['post_id'] ?>&type=posting">
                                <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job['title']) ?>" class="card-image">
                            </a>

                            <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 line-clamp-2"><?= htmlspecialchars($job['title']) ?></h3>
                                    
                                    <p class="text-sm text-gray-600 my-2">
                                        <i class="fas fa-user mr-1 text-gray-400"></i>
                                        <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                    </p>
                                    
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

    

<?php include '../includes/footer.php'; ?>

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