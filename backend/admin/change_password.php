<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$current_password_err = $new_password_err = $confirm_password_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate current password
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Vui lòng nhập mật khẩu hiện tại.";
    } else {
        $current_password = trim($_POST["current_password"]);
    }

    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Vui lòng nhập mật khẩu mới.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Vui lòng xác nhận mật khẩu mới.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Mật khẩu xác nhận không khớp.";
        }
    }

    // Check input errors before processing
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare a select statement to get the current hashed password
        $sql = "SELECT password FROM users WHERE id = ?";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $_SESSION["id"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Current password is correct, proceed to update the password
                            $sql_update = "UPDATE users SET password = ? WHERE id = ?";
                            
                            if ($stmt_update = $mysqli->prepare($sql_update)) {
                                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt_update->bind_param("si", $param_password, $_SESSION["id"]);
                                
                                if ($stmt_update->execute()) {
                                    $success_message = "Mật khẩu của bạn đã được cập nhật thành công.";
                                } else {
                                    $new_password_err = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
                                }
                                $stmt_update->close();
                            }
                        } else {
                            $current_password_err = "Mật khẩu hiện tại không đúng.";
                        }
                    }
                }
            } else {
                $new_password_err = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
            }
            $stmt->close();
        }
    }
}

$page_title = 'Đổi mật khẩu';
require_once 'templates/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?></h1>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Form đổi mật khẩu
            </div>
            <div class="card-body">
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" id="current_password" class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $current_password_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
