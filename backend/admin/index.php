<?php
require_once '../config/config.php';
require_once 'templates/header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$domain_id = filter_input(INPUT_GET, 'domain_id', FILTER_VALIDATE_INT);
check_domain_permission($mysqli, $domain_id);
if (!$domain_id) {
    echo "<div class='alert alert-danger'>Không có domain nào được chọn. Vui lòng quay lại <a href='domains.php'>trang quản lý domain</a>.</div>";
    require_once 'templates/footer.php';
    exit;
}

$stmt_domain = $mysqli->prepare("SELECT domain_name FROM domains WHERE id = ?");
$stmt_domain->bind_param("i", $domain_id);
$stmt_domain->execute();
$result_domain = $stmt_domain->get_result();
$domain_info = $result_domain->fetch_assoc();
$stmt_domain->close();
if (!$domain_info) {
    echo "<div class='alert alert-danger'>Domain không hợp lệ.</div>";
    require_once 'templates/footer.php';
    exit;
}
$current_domain_name = $domain_info['domain_name'];

function update_setting($mysqli, $domain_id, $key, $value) {
    $stmt = $mysqli->prepare("INSERT INTO settings (domain_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("isss", $domain_id, $key, $value, $value);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['upload_errors'] = [];
    // Xử lý các trường văn bản
    $text_settings = [
        'google_gtag_id', 'tiktok_pixel_id', 'meta_pixel_id', 
        'button_href', 'blacklist_href', 'blacklist_page_title',
        'blacklist_page_content1', 'blacklist_page_content2', 'blacklist_page_content3',
        'verification_title', 'verification_lifetime',
        'contact_name', 'contact_address', 'contact_email', 'contact_phone'
    ];
    foreach ($text_settings as $key) {
        if (isset($_POST[$key])) {
            update_setting($mysqli, $domain_id, $key, $_POST[$key]);
        }
    }

    // Xử lý tải lên hình ảnh
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $_SESSION['upload_errors'][] = "Lỗi nghiêm trọng: Không thể tạo thư mục uploads. Vui lòng kiểm tra quyền ghi của thư mục `php_project`.";
        }
    }

    for ($i = 1; $i <= 3; $i++) {
        $image_key = 'blacklist_page_image' . $i;
        $remove_key = 'remove_image' . $i;

        // Xử lý xóa ảnh
        if (isset($_POST[$remove_key])) {
            $stmt_get = $mysqli->prepare("SELECT setting_value FROM settings WHERE domain_id = ? AND setting_key = ?");
            $stmt_get->bind_param("is", $domain_id, $image_key);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            if ($row = $result_get->fetch_assoc()) {
                if (!empty($row['setting_value']) && file_exists($upload_dir . $row['setting_value'])) {
                    unlink($upload_dir . $row['setting_value']);
                }
            }
            $stmt_get->close();
            update_setting($mysqli, $domain_id, $image_key, '');
        }

        // Xử lý tải lên ảnh mới
        if (isset($_FILES[$image_key]) && $_FILES[$image_key]['error'] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$image_key]["tmp_name"];
            $name = basename($_FILES[$image_key]["name"]);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $ext;
            
if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $base_path = dirname(dirname($_SERVER['PHP_SELF']));
                $full_url = $protocol . $host . $base_path . '/uploads/' . $new_filename;
                update_setting($mysqli, $domain_id, $image_key, $full_url);
            } else {
                $_SESSION['upload_errors'][] = "Lỗi khi tải lên hình ảnh {$i}. Không thể di chuyển tệp. Vui lòng kiểm tra quyền ghi của thư mục `php_project/uploads`.";
            }
        }
    }

    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");

    header("Location: index.php?domain_id=" . $domain_id);
    exit;
}

// Lấy cài đặt hiện tại
$settings = [];
$stmt = $mysqli->prepare("SELECT setting_key, setting_value FROM settings WHERE domain_id = ?");
$stmt->bind_param("i", $domain_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$stmt->close();



$page_title = 'Cài đặt chung';
?>

<?php
// Hiển thị lỗi tải lên nếu có
if (!empty($_SESSION['upload_errors'])) {
    foreach ($_SESSION['upload_errors'] as $error) {
        echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
    }
    unset($_SESSION['upload_errors']);
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?> cho <?php echo htmlspecialchars($current_domain_name); ?></h1>
    <a href="domains.php" class="btn btn-secondary">Quay lại danh sách Domain</a>
</div>

<form action="index.php?domain_id=<?php echo $domain_id; ?>" method="post" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Cài đặt Tracking & Links</div>
                <div class="card-body">
                    <div class="mb-3"><label for="google_gtag_id" class="form-label">ID Google GTAG</label><input type="text" id="google_gtag_id" name="google_gtag_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_gtag_id'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="tiktok_pixel_id" class="form-label">ID TikTok Pixel</label><input type="text" id="tiktok_pixel_id" name="tiktok_pixel_id" class="form-control" value="<?php echo htmlspecialchars($settings['tiktok_pixel_id'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="meta_pixel_id" class="form-label">ID Meta Pixel</label><input type="text" id="meta_pixel_id" name="meta_pixel_id" class="form-control" value="<?php echo htmlspecialchars($settings['meta_pixel_id'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="button_href" class="form-label">Liên kết nút (Mặc định)</label><input type="text" id="button_href" name="button_href" class="form-control" value="<?php echo htmlspecialchars($settings['button_href'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="blacklist_href" class="form-label">Liên kết nút (Danh sách đen)</label><input type="text" id="blacklist_href" name="blacklist_href" class="form-control" value="<?php echo htmlspecialchars($settings['blacklist_href'] ?? ''); ?>"></div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Cài đặt Xác thực</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="verification_title" class="form-label">Tiêu đề Pop-up Xác thực</label>
                        <input type="text" id="verification_title" name="verification_title" class="form-control" value="<?php echo htmlspecialchars($settings['verification_title'] ?? 'Xác nhận bạn là người Việt Nam'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="verification_lifetime" class="form-label">Thời gian lưu session (giây)</label>
                        <input type="number" id="verification_lifetime" name="verification_lifetime" class="form-control" value="<?php echo htmlspecialchars($settings['verification_lifetime'] ?? '10'); ?>">
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Thông tin liên hệ (Footer)</div>
                <div class="card-body">
                    <div class="mb-3"><label for="contact_name" class="form-label">Tên công ty/cá nhân</label><input type="text" id="contact_name" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($settings['contact_name'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="contact_address" class="form-label">Địa chỉ</label><input type="text" id="contact_address" name="contact_address" class="form-control" value="<?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="contact_email" class="form-label">Email</label><input type="email" id="contact_email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="contact_phone" class="form-label">Số điện thoại</label><input type="tel" id="contact_phone" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Nội dung trang Blacklist</div>
                <div class="card-body">
                    <div class="mb-3"><label for="blacklist_page_title" class="form-label">Tiêu đề trang Blacklist</label><input type="text" id="blacklist_page_title" name="blacklist_page_title" class="form-control" value="<?php echo htmlspecialchars($settings['blacklist_page_title'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="blacklist_page_content1" class="form-label">Nội dung 1</label><textarea id="blacklist_page_content1" name="blacklist_page_content1" class="form-control" rows="3"><?php echo htmlspecialchars($settings['blacklist_page_content1'] ?? ''); ?></textarea></div>
<div class="mb-3"><label for="blacklist_page_image1" class="form-label">Hình ảnh 1</label><input type="file" id="blacklist_page_image1" name="blacklist_page_image1" class="form-control">
                        <?php if (!empty($settings['blacklist_page_image1'])): ?>
                        <div class="mt-2">
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image1']); ?>" height="50">
                            <label class="ms-2"><input type="checkbox" name="remove_image1"> Xóa ảnh</label>
                            <input type="text" class="form-control mt-1" value="<?php echo htmlspecialchars($settings['blacklist_page_image1']); ?>" readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="mb-3"><label for="blacklist_page_content2" class="form-label">Nội dung 2</label><textarea id="blacklist_page_content2" name="blacklist_page_content2" class="form-control" rows="3"><?php echo htmlspecialchars($settings['blacklist_page_content2'] ?? ''); ?></textarea></div>
<div class="mb-3"><label for="blacklist_page_image2" class="form-label">Hình ảnh 2</label><input type="file" id="blacklist_page_image2" name="blacklist_page_image2" class="form-control">
                        <?php if (!empty($settings['blacklist_page_image2'])): ?>
                        <div class="mt-2">
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image2']); ?>" height="50">
                            <label class="ms-2"><input type="checkbox" name="remove_image2"> Xóa ảnh</label>
                            <input type="text" class="form-control mt-1" value="<?php echo htmlspecialchars($settings['blacklist_page_image2']); ?>" readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="mb-3"><label for="blacklist_page_content3" class="form-label">Nội dung 3</label><textarea id="blacklist_page_content3" name="blacklist_page_content3" class="form-control" rows="3"><?php echo htmlspecialchars($settings['blacklist_page_content3'] ?? ''); ?></textarea></div>
<div class="mb-3"><label for="blacklist_page_image3" class="form-label">Hình ảnh 3</label><input type="file" id="blacklist_page_image3" name="blacklist_page_image3" class="form-control">
                        <?php if (!empty($settings['blacklist_page_image3'])): ?>
                        <div class="mt-2">
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image3']); ?>" height="50">
                            <label class="ms-2"><input type="checkbox" name="remove_image3"> Xóa ảnh</label>
                            <input type="text" class="form-control mt-1" value="<?php echo htmlspecialchars($settings['blacklist_page_image3']); ?>" readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary w-100">Lưu tất cả thay đổi</button>
    </div>
</form>

<?php
require_once 'templates/footer.php';
?>
