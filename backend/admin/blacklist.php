<?php
session_start();
require_once '../config/config.php';
require_once 'templates/header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Lấy domain_id từ URL và xác thực nó
$domain_id = filter_input(INPUT_GET, 'domain_id', FILTER_VALIDATE_INT);
check_domain_permission($mysqli, $domain_id);
if (!$domain_id) {
    echo "<div class='alert alert-danger'>Không có domain nào được chọn. Vui lòng quay lại <a href='domains.php'>trang quản lý domain</a>.</div>";
    require_once 'templates/footer.php';
    exit;
}

// Lấy thông tin domain để hiển thị tên
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

// Xử lý thêm mục vào danh sách đen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_blacklist'])) {
    $type = $_POST['type'];
    $value = trim($_POST['value']);
    if (!empty($value)) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO blacklist (domain_id, type, value) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $domain_id, $type, $value);
        $stmt->execute();
        $stmt->close();
        
        $cache = new CacheManager();
        $cache->delete("domain_settings_{$domain_id}");

        header("Location: blacklist.php?domain_id=" . $domain_id);
        exit;
    }
}

// Xử lý xóa mục khỏi danh sách đen
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM blacklist WHERE id = ? AND domain_id = ?");
    $stmt->bind_param("ii", $id, $domain_id);
    $stmt->execute();
    $stmt->close();

    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");

    header("location: blacklist.php?domain_id=" . $domain_id);
    exit;
}

// Lấy danh sách đen hiện tại cho domain đã chọn
$blacklist = [];
$stmt = $mysqli->prepare("SELECT * FROM blacklist WHERE domain_id = ? ORDER BY type, value");
$stmt->bind_param("i", $domain_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $blacklist[] = $row;
}
$stmt->close();

$page_title = 'Quản lý danh sách đen';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?> cho <?php echo htmlspecialchars($current_domain_name); ?></h1>
    <a href="domains.php" class="btn btn-secondary">Quay lại danh sách Domain</a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Thêm vào danh sách đen
            </div>
            <div class="card-body">
                <form action="blacklist.php?domain_id=<?php echo $domain_id; ?>" method="post">
                    <div class="mb-3">
                        <label for="type" class="form-label">Loại</label>
                        <select id="type" name="type" class="form-select">
                            <option value="ip">Địa chỉ IP</option>
                            <option value="ua">User Agent</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="value" class="form-label">Giá trị</label>
                        <input type="text" id="value" name="value" class="form-control" required>
                        <div class="form-text">
                            <b>IP:</b> Nhập IP đơn lẻ, dải CIDR (1.2.3.0/24), hoặc mẫu wildcard (1.2.x.x).<br>
                            <b>User Agent:</b> Nhập một đoạn ký tự. Bất kỳ UA nào chứa đoạn ký tự này sẽ bị chặn.
                        </div>
                    </div>
                    <button type="submit" name="add_blacklist" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Thêm
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Danh sách đen hiện tại
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th scope="col">Loại</th>
                                <th scope="col">Giá trị</th>
                                <th scope="col">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blacklist as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $item['type'] == 'ip' ? 'info' : 'secondary'; ?>">
                                        <?php echo $item['type'] == 'ip' ? 'Địa chỉ IP' : 'User Agent'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['value']); ?></td>
                                <td>
                                    <a href="blacklist.php?domain_id=<?php echo $domain_id; ?>&delete=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa mục này?');">
                                        <i class="bi bi-trash-fill"></i> Xóa
                                    </a>
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

<?php
require_once 'templates/footer.php';
?>
