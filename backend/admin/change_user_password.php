<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] !== 'admin') {
    header("location: index.php");
    exit;
}

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("location: users.php");
    exit;
}

// Fetch user info
$stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("location: users.php");
    exit;
}

$new_password_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Vui lòng nhập mật khẩu mới.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $new_password = trim($_POST["new_password"]);
        $param_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt_update = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_update->bind_param("si", $param_password, $user_id);
        
        if ($stmt_update->execute()) {
            $success_message = "Mật khẩu cho người dùng '" . htmlspecialchars($user['username']) . "' đã được cập nhật thành công.";
        } else {
            $new_password_err = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
        }
        $stmt_update->close();
    }
}

$page_title = 'Đổi mật khẩu cho người dùng';
require_once 'templates/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Đổi mật khẩu cho: <?php echo htmlspecialchars($user['username']); ?></h1>
    <a href="users.php" class="btn btn-secondary">Quay lại danh sách người dùng</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Đặt mật khẩu mới
            </div>
            <div class="card-body">
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <form action="change_user_password.php?user_id=<?php echo $user_id; ?>" method="post">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
