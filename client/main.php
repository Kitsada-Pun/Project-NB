<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

// 2. เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

// 3. ดึงข้อมูลที่จำเป็นสำหรับหน้านี้
// ดึงข้อมูลหมวดหมู่ยอดนิยม
$categories = [];
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY RAND() LIMIT 6";
$result_cat = $condb->query($sql_categories);
if ($result_cat) {
    $categories = $result_cat->fetch_all(MYSQLI_ASSOC);
}

// ดึงข้อมูลประกาศรับงานแนะนำ (Featured Job Postings)
$job_postings = [];
// --- SQL EDIT: Added jp.description and jp.posted_date ---
$sql_job_postings = "SELECT
                        jp.post_id, jp.title, jp.description, jp.price_range, jp.posted_date,
                        u.first_name, u.last_name,
                        jc.category_name,
                        uf.file_path AS job_image_path
                    FROM job_postings AS jp
                    JOIN users AS u ON jp.designer_id = u.user_id
                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                    WHERE jp.status = 'active'
                    ORDER BY jp.posted_date DESC
                    LIMIT 8";

$result_jobs = $condb->query($sql_job_postings);
if ($result_jobs) {
    $job_postings = $result_jobs->fetch_all(MYSQLI_ASSOC);
}

// 4. เรียกใช้ Header
include '../includes/header.php';
?>

<main class="flex-grow">
    
    <div class="relative bg-gradient-to-br from-blue-600 to-indigo-700 pt-20 pb-28">
        <div class="container mx-auto px-4 text-center text-white">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight">ค้นหาฟรีแลนซ์มืออาชีพ</h1>
            <p class="mt-4 text-lg text-indigo-100 max-w-2xl mx-auto">เปลี่ยนไอเดียของคุณให้เป็นจริง กับนักออกแบบกว่าพันคนบน PixelLink</p>
            
            <form action="../job_listings.php" method="GET" class="mt-8 max-w-2xl mx-auto">
                <input type="hidden" name="type" value="postings">
                <div class="relative">
                    <input type="search" name="search" placeholder="คุณกำลังมองหางานดีไซน์ประเภทไหน? (เช่น 'ออกแบบโลโก้', 'วาดภาพประกอบ')" class="w-full p-4 pr-12 text-gray-900 rounded-full shadow-lg focus:ring-4 focus:ring-blue-300 focus:outline-none border-0">
                    <button type="submit" class="absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 hover:text-blue-600">
                        <i class="fas fa-search fa-lg"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="container mx-auto px-4 md:px-6 -mt-20">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300">
                <div class="bg-blue-100 text-blue-600 p-4 rounded-full"><i class="fas fa-tasks fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">จัดการโปรเจกต์</h3>
                    <a href="#" class="text-sm text-blue-600 hover:underline">ดูโปรเจกต์ทั้งหมดของคุณ &rarr;</a>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300">
                <div class="bg-green-100 text-green-600 p-4 rounded-full"><i class="fas fa-comments fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">กล่องข้อความ</h3>
                    <a href="#" class="text-sm text-blue-600 hover:underline">ดูข้อความที่ยังไม่ได้อ่าน &rarr;</a>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4 hover:shadow-xl transition-shadow duration-300">
                <div class="bg-purple-100 text-purple-600 p-4 rounded-full"><i class="fas fa-user-edit fa-lg"></i></div>
                <div>
                    <h3 class="font-bold text-gray-800">โปรไฟล์ของคุณ</h3>
                    <a href="edit_profile.php" class="text-sm text-blue-600 hover:underline">แก้ไขข้อมูลส่วนตัว &rarr;</a>
                </div>
            </div>
        </div>

        <section id="categories" class="mb-16">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">หมวดหมู่ยอดนิยม</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php 
                $icons = ['fas fa-palette', 'fas fa-vector-square', 'fas fa-desktop', 'fas fa-camera-retro', 'fas fa-pen-nib', 'fas fa-bullhorn'];
                $i = 0;
                foreach ($categories as $cat): ?>
                    <a href="../job_listings.php?type=postings&category=<?= $cat['category_id'] ?>" class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 text-center">
                        <i class="<?= $icons[$i++] ?? 'fas fa-star' ?> fa-2x text-blue-500 mb-2"></i>
                        <span class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($cat['category_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="featured-services" class="pb-16">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">บริการแนะนำ</h2>
            <?php if (empty($job_postings)): ?>
                <div class="bg-white p-6 rounded-lg text-center text-gray-500">ยังไม่มีบริการแนะนำในขณะนี้</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    <?php foreach ($job_postings as $job): ?>
                        <div class="card-item flex flex-col">
                            <?php
                                $image_source = '../dist/img/pdpa02.jpg';
                                if (!empty($job['job_image_path'])) {
                                    $correct_path = str_replace('../', '', $job['job_image_path']);
                                    if (file_exists($correct_path)) {
                                        $image_source = htmlspecialchars($correct_path);
                                    }
                                }
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
        </section>
    </div>
</main>

<?php
// --- เรียกใช้ Footer ---
$condb->close();
include '../includes/footer.php'; 
?>