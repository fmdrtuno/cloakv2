<?php
// Bật hiển thị lỗi để gỡ lỗi (chỉ nên dùng trong môi trường phát triển)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Lấy tên tệp hiện tại để làm nổi bật liên kết đang hoạt động
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Bảng điều khiển quản trị'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            padding: 20px;
            background-color: #343a40;
            color: #fff;
        }
        .sidebar .nav-link {
            color: #adb5bd;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            color: #fff;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3 class="mb-4">Trang quản trị</h3>
        <ul class="nav flex-column">
<li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'domains.php') ? 'active' : ''; ?>" href="domains.php">
                    <i class="bi bi-hdd-stack-fill"></i> Quản lý Domain
                </a>
            </li>
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people-fill"></i> Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'templates.php') ? 'active' : ''; ?>" href="templates.php">
                    <i class="bi bi-file-earmark-text-fill"></i> Quản lý Mẫu Chặn
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'status.php') ? 'active' : ''; ?>" href="status.php">
                    <i class="bi bi-hdd-network-fill"></i> Trạng thái hệ thống
                </a>
            </li>
            <?php endif; ?>
            <!-- Các liên kết khác sẽ được truy cập từ trang quản lý domain -->
<li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>" href="change_password.php">
                    <i class="bi bi-key-fill"></i> Đổi mật khẩu
                </a>
            </li>
            <li class="nav-item mt-auto">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </div>
    <div class="content">
        <div class="container-fluid">
