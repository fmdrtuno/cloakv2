<?php
require_once 'templates/header.php';
require_once '../config/config.php';

if (!isset($_GET['domain_id']) || !filter_var($_GET['domain_id'], FILTER_VALIDATE_INT)) {
    echo "<div class='alert alert-danger'>Domain ID không hợp lệ.</div>";
    require_once 'templates/footer.php';
    exit;
}
$domain_id = $_GET['domain_id'];
check_domain_permission($mysqli, $domain_id);

// Lấy cài đặt hiện tại
$settings = [];
$stmt_get = $mysqli->prepare("SELECT setting_key, setting_value FROM settings WHERE domain_id = ? AND setting_key LIKE 'honeypot_page_%'");
$stmt_get->bind_param("i", $domain_id);
$stmt_get->execute();
$result = $stmt_get->get_result();
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$stmt_get->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $honeypot_settings = [
        'honeypot_page_title' => $_POST['title'],
        'honeypot_page_content1' => $_POST['content1'],
        'honeypot_page_image1' => $_POST['image1'],
        'honeypot_page_content2' => $_POST['content2'],
        'honeypot_page_image2' => $_POST['image2'],
        'honeypot_page_content3' => $_POST['content3'],
        'honeypot_page_image3' => $_POST['image3'],
    ];

    $stmt_update = $mysqli->prepare("INSERT INTO settings (domain_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($honeypot_settings as $key => $value) {
        $stmt_update->bind_param("iss", $domain_id, $key, $value);
        $stmt_update->execute();
    }
    $stmt_update->close();

    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");

    echo "<div class='alert alert-success'>Đã cập nhật cài đặt trang honeypot.</div>";
    // Tải lại cài đặt sau khi cập nhật
    $settings = $honeypot_settings;
}
?>

<div class="container mt-4">
    <h2>Tùy chỉnh trang Honeypot</h2>
    <p>Thiết kế nội dung sẽ hiển thị khi một bot bị dính bẫy. Trang này sẽ trông giống như một trang thật để đánh lừa bot.</p>
    
    <form action="" method="post">
        <div class="mb-3">
            <label for="title" class="form-label">Tiêu đề trang</label>
            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($settings['honeypot_page_title'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label for="content1" class="form-label">Nội dung 1</label>
            <textarea class="form-control" id="content1" name="content1" rows="3"><?php echo htmlspecialchars($settings['honeypot_page_content1'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="image1" class="form-label">URL Hình ảnh 1</label>
            <input type="text" class="form-control" id="image1" name="image1" value="<?php echo htmlspecialchars($settings['honeypot_page_image1'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label for="content2" class="form-label">Nội dung 2</label>
            <textarea class="form-control" id="content2" name="content2" rows="3"><?php echo htmlspecialchars($settings['honeypot_page_content2'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="image2" class="form-label">URL Hình ảnh 2</label>
            <input type="text" class="form-control" id="image2" name="image2" value="<?php echo htmlspecialchars($settings['honeypot_page_image2'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label for="content3" class="form-label">Nội dung 3</label>
            <textarea class="form-control" id="content3" name="content3" rows="3"><?php echo htmlspecialchars($settings['honeypot_page_content3'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="image3" class="form-label">URL Hình ảnh 3</label>
            <input type="text" class="form-control" id="image3" name="image3" value="<?php echo htmlspecialchars($settings['honeypot_page_image3'] ?? ''); ?>">
        </div>

        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>
