<?php
$password_hash = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['password'])) {
    $password = $_POST['password'];
    // Tạo hash mật khẩu an toàn
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo Hash Mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .hash-generator {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container hash-generator">
        <div class="card">
            <div class="card-header">
                <h2>Tạo Hash Mật khẩu An toàn</h2>
            </div>
            <div class="card-body">
                <p>Sử dụng biểu mẫu này để tạo một hash mật khẩu an toàn cho tài khoản quản trị của bạn.</p>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mb-3">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nhập Mật khẩu Mới:</label>
                        <input type="text" name="password" id="password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Tạo Hash</button>
                </form>

                <?php if ($password_hash): ?>
                    <div class="alert alert-success">
                        <p><strong>Hash Mật khẩu của bạn là:</strong></p>
                        <p style="word-wrap: break-word;"><?php echo htmlspecialchars($password_hash); ?></p>
                        <hr>
                        <p>Sao chép hash này và chạy câu lệnh SQL sau trong phpMyAdmin để cập nhật mật khẩu cho người dùng 'admin':</p>
                        <code>
                            UPDATE admins SET password = '<?php echo htmlspecialchars($password_hash); ?>' WHERE username = 'admin';
                        </code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
