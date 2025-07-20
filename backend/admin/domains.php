<?php
require_once '../config/config.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Hàm tạo API key ngẫu nhiên
function generate_api_key() {
    return bin2hex(random_bytes(16));
}

// Xử lý thêm domain
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_domain'])) {
    $domain_name = trim($_POST['domain_name']);
    $user_id = $_SESSION['id']; // Lấy user ID từ session
    if (!empty($domain_name)) {
        $api_key = generate_api_key();
        $stmt = $mysqli->prepare("INSERT INTO domains (domain_name, api_key, user_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $domain_name, $api_key, $user_id);
        $stmt->execute();
        $new_domain_id = $stmt->insert_id;
        $stmt->close();

        // Tự động tạo cài đặt mặc định cho domain mới
        $default_settings = [
            'google_gtag_id' => '', 'tiktok_pixel_id' => '', 'meta_pixel_id' => '',
            'button_href' => '#', 'blacklist_href' => '#'
        ];
        $stmt_settings = $mysqli->prepare("INSERT INTO settings (domain_id, setting_key, setting_value) VALUES (?, ?, ?)");
        foreach ($default_settings as $key => $value) {
            $stmt_settings->bind_param("iss", $new_domain_id, $key, $value);
            $stmt_settings->execute();
        }
        $stmt_settings->close();
    }
}

// Xử lý xóa domain
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM domains WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("location: domains.php");
    exit;
}

// Lấy danh sách domain
$domains = [];
$search_term = $_GET['search'] ?? '';
$owner_filter = $_GET['owner'] ?? '';
$stealth_filter = $_GET['stealth_mode'] ?? '';
$params = [];
$types = '';

$sql = "SELECT d.id, d.domain_name, d.api_key, d.stealth_mode, d.redirect_mode, d.honeypot_enabled, d.verification_mode, u.username as owner FROM domains d LEFT JOIN users u ON d.user_id = u.id";

$where_clauses = [];
if ($_SESSION['role'] === 'user') {
    $where_clauses[] = "d.user_id = ?";
    $params[] = $_SESSION['id'];
    $types .= 'i';
} elseif ($_SESSION['role'] === 'manager') {
    $child_user_ids = [$_SESSION['id']];
    $stmt_children = $mysqli->prepare("SELECT id FROM users WHERE parent_id = ?");
    $stmt_children->bind_param("i", $_SESSION['id']);
    $stmt_children->execute();
    $result_children = $stmt_children->get_result();
    while ($child = $result_children->fetch_assoc()) {
        $child_user_ids[] = $child['id'];
    }
    $stmt_children->close();
    
    $placeholders = implode(',', array_fill(0, count($child_user_ids), '?'));
    $where_clauses[] = "d.user_id IN ($placeholders)";
    foreach ($child_user_ids as $child_id) {
        $params[] = $child_id;
    }
    $types .= str_repeat('i', count($child_user_ids));
}

if (!empty($search_term)) {
    $where_clauses[] = "d.domain_name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= 's';
}

if ($_SESSION['role'] === 'admin' && !empty($owner_filter)) {
    $where_clauses[] = "d.user_id = ?";
    $params[] = $owner_filter;
    $types .= 'i';
}

if ($stealth_filter !== '') {
    $where_clauses[] = "d.stealth_mode = ?";
    $params[] = $stealth_filter;
    $types .= 'i';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY d.domain_name";

$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    error_log("Failed to prepare statement: " . $mysqli->error);
    die("<div class='alert alert-danger'>Lỗi truy vấn cơ sở dữ liệu. Vui lòng kiểm tra log lỗi của máy chủ.</div>");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $domains[] = $row;
}
$stmt->close();

$page_title = 'Quản lý Domain';
require_once 'templates/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?></h1>
</div>

<?php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Đi lên một cấp từ /admin để đến thư mục /php_project
$api_path = dirname(dirname($_SERVER['PHP_SELF'])); 
$full_api_url = $protocol . $host . rtrim($api_path, '/') . '/';
?>
<div class="alert alert-success">
    <strong>Đường dẫn API của bạn:</strong> <code><?php echo htmlspecialchars($full_api_url); ?></code>
    <br>
    Sử dụng đường dẫn này và API Key tương ứng để cấu hình cho các trang frontend.
</div>

<div class="row">
<div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                Thêm Domain Mới
            </div>
            <div class="card-body">
                <form action="domains.php" method="post">
                    <div class="mb-3">
                        <label for="domain_name" class="form-label">Tên Domain</label>
                        <input type="text" id="domain_name" name="domain_name" class="form-control" placeholder="ví dụ: mywebsite.com" required>
                    </div>
                    <button type="submit" name="add_domain" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Thêm Domain
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Áp dụng Mẫu Chặn</div>
            <div class="card-body">
                <form action="apply_template.php" method="post">
                    <div class="mb-3">
                        <label for="apply_domain_id" class="form-label">Chọn Domain</label>
                        <select name="domain_id" id="apply_domain_id" class="form-select" required>
                            <option value="">-- Chọn Domain --</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['domain_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="apply_template_id" class="form-label">Chọn Mẫu</label>
                        <select name="template_id" id="apply_template_id" class="form-select" required>
                            <option value="">-- Chọn Mẫu --</option>
                            <?php
                            $templates_result = $mysqli->query("SELECT id, name FROM blocking_templates ORDER BY name");
                            if ($templates_result) {
                                while ($template = $templates_result->fetch_assoc()) {
                                    echo "<option value='{$template['id']}'>" . htmlspecialchars($template['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
<button type="submit" class="btn btn-primary">Áp dụng Mẫu</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                Bộ lọc tìm kiếm
            </div>
            <div class="card-body">
                <form action="domains.php" method="get" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Tên domain</label>
                        <input class="form-control" type="search" placeholder="Tìm theo tên domain..." name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="col-md-3">
                        <label for="owner" class="form-label">Người tạo</label>
                        <select name="owner" id="owner" class="form-select">
                            <option value="">Tất cả người dùng</option>
                            <?php
                            $users_result = $mysqli->query("SELECT id, username FROM users ORDER BY username");
                            if ($users_result) {
                                while ($user = $users_result->fetch_assoc()) {
                                    $selected = (isset($_GET['owner']) && $_GET['owner'] == $user['id']) ? 'selected' : '';
                                    echo "<option value='{$user['id']}' {$selected}>" . htmlspecialchars($user['username']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label for="stealth_mode" class="form-label">Stealth Mode</label>
                        <select name="stealth_mode" id="stealth_mode" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="1" <?php echo (isset($_GET['stealth_mode']) && $_GET['stealth_mode'] === '1') ? 'selected' : ''; ?>>Đang bật</option>
                            <option value="0" <?php echo (isset($_GET['stealth_mode']) && $_GET['stealth_mode'] === '0') ? 'selected' : ''; ?>>Đang tắt</option>
                        </select>
                    </div>
<div class="col-md-2 d-flex align-self-end">
                        <button class="btn btn-primary w-100" type="submit">Lọc</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Danh sách Domain
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th scope="col">Tên Domain</th>
                                <th scope="col">Stealth Mode</th>
                                <th scope="col">Redirect Mode</th>
                                <th scope="col">Honeypot</th>
                                <th scope="col">Chế độ xác thực</th>
                                <th scope="col">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">API Key: <code><?php echo htmlspecialchars($domain['api_key']); ?></code></small>
<?php if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') && !empty($domain['owner'])): ?>
                                        <br><small class="text-info">Người tạo: <?php echo htmlspecialchars($domain['owner']); ?></small>
                                    <?php endif; ?>
                                </td>
<td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input stealth-toggle" type="checkbox" role="switch" id="stealthSwitch<?php echo $domain['id']; ?>" data-domain-id="<?php echo $domain['id']; ?>" <?php echo $domain['stealth_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="stealthSwitch<?php echo $domain['id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input redirect-toggle" type="checkbox" role="switch" id="redirectSwitch<?php echo $domain['id']; ?>" data-domain-id="<?php echo $domain['id']; ?>" <?php echo $domain['redirect_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="redirectSwitch<?php echo $domain['id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input honeypot-toggle" type="checkbox" role="switch" id="honeypotSwitch<?php echo $domain['id']; ?>" data-domain-id="<?php echo $domain['id']; ?>" <?php echo $domain['honeypot_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="honeypotSwitch<?php echo $domain['id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-select verification-mode-select" data-domain-id="<?php echo $domain['id']; ?>">
                                        <option value="0" <?php echo ($domain['verification_mode'] == 0) ? 'selected' : ''; ?>>Tắt</option>
                                        <option value="1" <?php echo ($domain['verification_mode'] == 1) ? 'selected' : ''; ?>>Chỉ Pop-up</option>
                                        <option value="2" <?php echo ($domain['verification_mode'] == 2) ? 'selected' : ''; ?>>Hiện nội dung</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="btn-group" role="group" aria-label="Domain Actions">
                                        <a href="index.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Cài đặt chung">
                                            <i class="bi bi-gear-fill fs-5"></i>
                                        </a>
                                        <a href="blacklist.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Danh sách đen">
                                            <i class="bi bi-shield-slash-fill fs-5"></i>
                                        </a>
                                        <a href="countries.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Quản lý quốc gia">
                                            <i class="bi bi-globe fs-5"></i>
                                        </a>
                                        <a href="visitors.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Nhật ký truy cập">
                                            <i class="bi bi-people-fill fs-5"></i>
                                        </a>
                                        <a href="honeypot.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Cấu hình Honeypot">
                                            <i class="bi bi-incognito fs-5"></i>
                                        </a>
                                        <a href="questions.php?domain_id=<?php echo $domain['id']; ?>" class="btn btn-outline-secondary" title="Câu hỏi xác thực">
                                            <i class="bi bi-patch-question-fill fs-5"></i>
                                        </a>
                                        <a href="domains.php?delete=<?php echo $domain['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('CẢNH BÁO: Xóa domain này sẽ xóa TẤT CẢ dữ liệu liên quan. Bạn có chắc chắn?');" title="Xóa Domain">
                                            <i class="bi bi-trash-fill fs-5"></i>
                                        </a>
                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Use a dynamically generated base URL to ensure requests are sent to the correct path.
    const adminApiBaseUrl = '<?php echo rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/'; ?>';

    function createToggleHandler(selector, url, errorMessage) {
        const switches = document.querySelectorAll(selector);
        switches.forEach(s => {
            s.addEventListener('change', function () {
                const domainId = this.dataset.domainId;
                const enabled = this.checked;

                // Prepend the base URL to the endpoint.
                fetch(adminApiBaseUrl + url, {
                    method: 'POST',
                    credentials: 'same-origin', // Ensures session cookies are sent.
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `domain_id=${domainId}&status=${enabled ? 1 : 0}`
                })
                .then(response => {
                    if (!response.ok) {
                        // Log detailed error for debugging.
                        console.error(`HTTP error! Status: ${response.status}`, response);
                        throw new Error('Server responded with an error.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        // Display specific error from backend if available.
                        alert(data.error || errorMessage);
                        this.checked = !enabled;
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('Đã xảy ra lỗi kết nối. Vui lòng kiểm tra console để biết thêm chi tiết.');
                    this.checked = !enabled;
                });
            });
        });
    }

    createToggleHandler('.stealth-toggle', 'toggle_stealth_mode.php', 'Lỗi: Không thể cập nhật trạng thái Stealth Mode.');
    createToggleHandler('.redirect-toggle', 'toggle_redirect_mode.php', 'Lỗi: Không thể cập nhật trạng thái Redirect Mode.');
    createToggleHandler('.honeypot-toggle', 'toggle_honeypot_mode.php', 'Lỗi: Không thể cập nhật trạng thái Honeypot Mode.');
    
    const selects = document.querySelectorAll('.verification-mode-select');
    selects.forEach(s => {
        s.addEventListener('change', function () {
            const domainId = this.dataset.domainId;
            const mode = this.value;

            // Prepend the base URL to the endpoint.
            fetch(adminApiBaseUrl + 'toggle_verification_mode.php', {
                method: 'POST',
                credentials: 'same-origin', // Ensures session cookies are sent.
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `domain_id=${domainId}&status=${mode}`
            })
            .then(response => {
                if (!response.ok) {
                    console.error(`HTTP error! Status: ${response.status}`, response);
                    throw new Error('Server responded with an error.');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    alert(data.error || 'Lỗi: Không thể cập nhật chế độ xác thực.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Đã xảy ra lỗi kết nối. Vui lòng kiểm tra console để biết thêm chi tiết.');
            });
        });
    });
});
</script>
<?php
require_once 'templates/footer.php';
?>
