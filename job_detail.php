<?php
// public/job_detail.php
session_start();
date_default_timezone_set('Asia/Bangkok');

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

$job_data = null;
$error_message = '';
$job_type = '';
$loggedInUserName = '';

// ดึงชื่อผู้ใช้ที่ล็อกอิน
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

// ดึงข้อมูลงานจาก URL
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['type'])) {
    $job_id = (int)$_GET['id'];
    $job_type = $_GET['type'];

    if ($job_type === 'posting') {
        $sql = "SELECT
                    jp.post_id AS id, jp.title, jp.description, jp.price_range, jp.posted_date, jp.status,
                    'job_posting' AS type_display, u.user_id AS owner_id, u.first_name, u.last_name,
                    u.user_type AS owner_type, jc.category_name, uf.file_path AS job_image_path
                FROM job_postings AS jp
                JOIN users AS u ON jp.designer_id = u.user_id
                LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                WHERE jp.post_id = ? AND jp.status = 'active'";
        
        $stmt = $condb->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $job_data = $result->fetch_assoc();
            } else {
                $error_message = "ไม่พบประกาศรับงานนี้ หรือประกาศถูกปิดไปแล้ว";
            }
            $stmt->close();
        }
    }
    // ... (โค้ดสำหรับ job_type 'request' เหมือนเดิม) ...
} else {
    $error_message = "ไม่พบ Job ID หรือประเภทงาน";
}

$condb->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด: <?= $job_data ? htmlspecialchars($job_data['title']) : 'ไม่พบงาน' ?> | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .btn-primary { background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 150, 210, 0.4); }
        .btn-danger { background-color: #ef4444; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background-color: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }
    </style>
</head>
<body class="bg-slate-100 flex flex-col min-h-screen">

    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php">
                <img src="dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                    <a href="designer/view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                    <a href="logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
                <?php else : ?>
                    <a href="login.php" class="font-semibold text-slate-600 hover:text-blue-600 transition-colors">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn-primary text-white px-5 py-2 rounded-lg font-semibold shadow-md">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-4 sm:p-6 lg:p-8">
        <?php if (!empty($error_message)) : ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-6 rounded-lg shadow-md text-center max-w-2xl mx-auto">
                <p class="text-xl font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>เกิดข้อผิดพลาด</p>
                <p class="mt-2 text-lg"><?= htmlspecialchars($error_message) ?></p>
                <a href="index.php" class="mt-4 inline-block btn-primary text-white px-6 py-2 rounded-lg font-semibold shadow-lg">กลับไปหน้าหลัก</a>
            </div>
        <?php elseif ($job_data) : ?>
            <div class="max-w-6xl mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2">

                    <div class="col-span-1">
                        <?php
                        $image_source = 'dist/img/pdpa02.jpg';
                        if ($job_type === 'posting' && !empty($job_data['job_image_path'])) {
                            $db_path = $job_data['job_image_path'];
                            $correct_path = preg_replace('/^\.\.\//', '', $db_path);
                            if (file_exists($correct_path)) {
                                $image_source = htmlspecialchars($correct_path);
                            }
                        }
                        ?>
                        <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job_data['title']) ?>" class="w-full h-96 md:h-full object-cover">
                    </div>

                    <div class="col-span-1 p-8 lg:p-12 flex flex-col justify-between">
                        <div>
                            <div class="mb-6">
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider"><?= htmlspecialchars($job_data['category_name'] ?? 'ไม่ระบุ') ?></span>
                                <h1 class="text-4xl lg:text-5xl font-bold text-slate-900 mt-3 leading-tight"><?= htmlspecialchars($job_data['title']) ?></h1>
                                <p class="mt-2 text-md text-slate-500">
                                    ประกาศโดย:
                                    <a href="designer/view_profile.php?user_id=<?= $job_data['owner_id'] ?>" class="font-semibold text-blue-600 hover:underline">
                                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($job_data['first_name'] . ' ' . $job_data['last_name']) ?>
                                    </a>
                                </p>
                            </div>
                            <div class="mb-8">
                                <h2 class="text-xl font-bold text-slate-800 mb-2 border-b-2 border-blue-200 pb-2">รายละเอียดงาน</h2>
                                <p class="text-slate-600 leading-relaxed text-base"><?= nl2br(htmlspecialchars($job_data['description'])) ?></p>
                            </div>
                            <div class="grid grid-cols-2 gap-6 text-base">
                                <div class="flex items-center text-slate-700">
                                    <i class="fas fa-calendar-day fa-lg text-slate-400 mr-3"></i>
                                    <div>
                                        <span class="block text-sm text-slate-500">ประกาศเมื่อ</span>
                                        <strong class="font-semibold"><?= date('d M Y', strtotime($job_data['posted_date'])) ?></strong>
                                    </div>
                                </div>
                                <?php if ($job_type === 'posting') : ?>
                                <div class="flex items-center text-slate-700">
                                    <i class="fas fa-hand-holding-usd fa-lg text-green-500 mr-3"></i>
                                    <div>
                                        <span class="block text-sm text-slate-500">ช่วงราคา</span>
                                        <strong class="font-semibold"><?= htmlspecialchars($job_data['price_range'] ?? 'สอบถาม') ?></strong>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-10 pt-8 border-t border-slate-200">
                             <div class="flex flex-col sm:flex-row gap-4">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $job_data['owner_id']): ?>
                                    <a href="messages.php?to_user=<?= $job_data['owner_id'] ?>" class="w-full text-center bg-sky-500 hover:bg-sky-600 text-white px-6 py-3 rounded-xl font-bold text-lg shadow-lg transition-colors flex items-center justify-center">
                                        <i class="fas fa-comments mr-2"></i> ส่งข้อความ
                                    </a>
                                <?php endif; ?>
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                     <a href="login.php" class="w-full text-center btn-primary text-white px-6 py-3 rounded-xl font-bold text-lg shadow-lg">เข้าสู่ระบบเพื่อติดต่อ</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-slate-800 text-slate-400 py-6 mt-auto">
        <div class="container mx-auto px-6 text-center">
            <p class="text-sm">&copy; <?= date('Y'); ?> PixelLink. สงวนลิขสิทธิ์.</p>
        </div>
    </footer>

</body>
</html>