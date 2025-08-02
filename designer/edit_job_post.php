<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- ตรวจสอบการล็อกอินและสิทธิ์ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) { die("Connection Failed: " . $condb->connect_error); }
$condb->set_charset("utf8mb4");

$designer_id = $_SESSION['user_id'];
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$job_data = null;
$error_message = '';
$success_message = '';

// --- 1. ดึงข้อมูลโพสต์เดิมมาแสดง ---
if ($post_id > 0) {
    $sql_fetch = "SELECT * FROM job_postings WHERE post_id = ? AND designer_id = ?";
    $stmt_fetch = $condb->prepare($sql_fetch);
    $stmt_fetch->bind_param("ii", $post_id, $designer_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $job_data = $result->fetch_assoc();
    } else {
        $error_message = "ไม่พบโพสต์ที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์ในการแก้ไขโพสต์นี้";
    }
    $stmt_fetch->close();
} else {
    $error_message = "ID ของโพสต์ไม่ถูกต้อง";
}

// --- 2. ดึงหมวดหมู่งานทั้งหมด ---
$categories = [];
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY category_name";
$result_categories = $condb->query($sql_categories);
if ($result_categories) {
    $categories = $result_categories->fetch_all(MYSQLI_ASSOC);
}


// --- 3. Logic การอัปเดตข้อมูลเมื่อมีการส่งฟอร์ม ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id'])) {
    $condb->begin_transaction();
    try {
        $post_id_update = (int)$_POST['post_id'];
        $title = $condb->real_escape_string($_POST['title']);
        $description = $condb->real_escape_string($_POST['description']);
        $price_range = $condb->real_escape_string($_POST['price_range']);
        $category_id = (int)$_POST['category'];

        // --- จัดการรูปภาพ (ถ้ามีการอัปโหลดใหม่) ---
        $main_image_id = $job_data['main_image_id']; // ใช้ ID เดิมเป็นค่าเริ่มต้น
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
            $file_info = $_FILES['main_image'];
            // (ใส่ Logic การตรวจสอบไฟล์และอัปโหลดไฟล์เหมือนในหน้า create_job_post.php)
            // ...
            
            // สมมติว่าอัปโหลดสำเร็จและได้ $new_file_id
            // $main_image_id = $new_file_id; 
        }

        // --- อัปเดตข้อมูลลง DB ---
        $sql_update = "UPDATE job_postings SET title = ?, description = ?, category_id = ?, price_range = ?, main_image_id = ? WHERE post_id = ? AND designer_id = ?";
        $stmt_update = $condb->prepare($sql_update);
        $stmt_update->bind_param("ssisiii", $title, $description, $category_id, $price_range, $main_image_id, $post_id_update, $designer_id);

        if (!$stmt_update->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $stmt_update->error);
        }
        $stmt_update->close();
        
        $condb->commit();
        $_SESSION['success_message'] = 'อัปเดตโพสต์ของคุณสำเร็จแล้ว!';
        header("Location: view_profile.php?user_id=" . $designer_id);
        exit();

    } catch (Exception $e) {
        $condb->rollback();
        $error_message = $e->getMessage();
    }
}

$condb->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโพสต์งาน | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-slate-100">

    <main class="container mx-auto px-4 py-12">
        <div class="mx-auto max-w-2xl">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">เกิดข้อผิดพลาด</p>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif ($job_data): ?>
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h1 class="text-3xl font-bold text-center text-slate-800 mb-6">แก้ไขโพสต์งาน</h1>
                    <form action="edit_job_post.php?id=<?= $post_id ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="post_id" value="<?= $job_data['post_id'] ?>">
                        
                        <div>
                            <label for="title" class="block font-semibold text-slate-700">ชื่องาน/บริการ:</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($job_data['title']) ?>" class="mt-1 block w-full px-4 py-2 border border-slate-300 rounded-lg shadow-sm" required>
                        </div>
                        
                        <div>
                            <label for="description" class="block font-semibold text-slate-700">รายละเอียด:</label>
                            <textarea id="description" name="description" rows="5" class="mt-1 block w-full px-4 py-2 border border-slate-300 rounded-lg shadow-sm" required><?= htmlspecialchars($job_data['description']) ?></textarea>
                        </div>

                        <div>
                            <label for="category" class="block font-semibold text-slate-700">หมวดหมู่:</label>
                            <select id="category" name="category" class="mt-1 block w-full px-4 py-2 border border-slate-300 rounded-lg shadow-sm" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($job_data['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="price_range" class="block font-semibold text-slate-700">ช่วงราคา:</label>
                            <input type="text" id="price_range" name="price_range" value="<?= htmlspecialchars($job_data['price_range']) ?>" class="mt-1 block w-full px-4 py-2 border border-slate-300 rounded-lg shadow-sm" required>
                        </div>

                        <div>
                            <label for="main_image" class="block font-semibold text-slate-700">เปลี่ยนภาพประกอบ (ถ้าต้องการ):</label>
                            <input type="file" id="main_image" name="main_image" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>

                        <div class="flex justify-end space-x-4 pt-4">
                            <a href="view_profile.php?user_id=<?= $designer_id ?>" class="bg-slate-200 text-slate-800 px-6 py-2 rounded-lg font-semibold">ยกเลิก</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow-md">บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>