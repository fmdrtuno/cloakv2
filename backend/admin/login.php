<?php
// Bật hiển thị lỗi để gỡ lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Nếu đã đăng nhập, chuyển hướng đến trang admin
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}

require_once '../config/config.php';

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(empty(trim($_POST["username"]))){
        $username_err = "Vui lòng nhập tên người dùng.";
    } else{
        $username = trim($_POST["username"]);
    }

    if(empty(trim($_POST["password"]))){
        $password_err = "Vui lòng nhập mật khẩu của bạn.";
    } else{
        $password = trim($_POST["password"]);
    }

if(empty($username_err) && empty($password_err)){
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";

        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if($stmt->execute()){
                $stmt->store_result();

                if($stmt->num_rows == 1){
                    $stmt->bind_result($id, $db_username, $hashed_password, $role);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            // Mật khẩu chính xác, các biến session đã được bắt đầu ở đầu tệp
                            
                            // Lưu trữ dữ liệu trong các biến session
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $db_username;
                            $_SESSION["role"] = $role;
                            
// Chuyển hướng người dùng đến trang chào mừng
                            header("location: domains.php");
                        } else{
                            $login_err = "Tên người dùng hoặc mật khẩu không hợp lệ.";
                        }
                    }
                } else{
                    $login_err = "Tên người dùng hoặc mật khẩu không hợp lệ.";
                }
            } else{
                $login_err = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập quản trị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .login-form {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <main class="login-form text-center">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <h1 class="h3 mb-3 fw-normal">Đăng nhập quản trị</h1>

            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }        
            ?>

            <div class="form-floating mb-3">
                <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" placeholder="Tên người dùng">
                <label for="username">Tên người dùng</label>
                <div class="invalid-feedback text-start"><?php echo $username_err; ?></div>
            </div>
            <div class="form-floating mb-3">
                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Mật khẩu">
                <label for="password">Mật khẩu</label>
                <div class="invalid-feedback text-start"><?php echo $password_err; ?></div>
            </div>

            <button class="w-100 btn btn-lg btn-primary" type="submit">Đăng nhập</button>
            <p class="mt-5 mb-3 text-muted">&copy; <?php echo date("Y"); ?></p>
        </form>
    </main>
</body>
</html>
