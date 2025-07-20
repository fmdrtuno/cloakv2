<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Only admins can manage users
if ($_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-danger'>Bạn không có quyền truy cập trang này.</div>");
}

$page_title = 'Quản lý người dùng';
require_once 'templates/header.php';

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

        if (!empty($username) && !empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO users (username, password, role, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $hashed_password, $role, $parent_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    // Delete user
    elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        if ($user_id != $_SESSION['id']) { // Prevent self-deletion
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: users.php");
    exit;
}

// Fetch all users for the dropdowns and list
$users = [];
$result = $mysqli->query("SELECT u.id, u.username, u.role, p.username as parent_name FROM users u LEFT JOIN users p ON u.parent_id = p.id ORDER BY u.username");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?></h1>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Thêm người dùng mới</div>
            <div class="card-body">
                <form action="users.php" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên người dùng</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Vai trò</label>
                        <select id="role" name="role" class="form-select">
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Quản lý trực tiếp (Parent)</label>
                        <select id="parent_id" name="parent_id" class="form-select">
                            <option value="">-- Không có --</option>
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['role'] === 'manager' || $user['role'] === 'admin'): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Thêm người dùng</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Danh sách người dùng</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Tên người dùng</th>
                                <th>Vai trò</th>
                                <th>Quản lý trực tiếp</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['parent_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="change_user_password.php?user_id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">Đổi mật khẩu</a>
                                        <?php if ($user['id'] != $_SESSION['id']): ?>
                                            <form action="users.php" method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Xóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
