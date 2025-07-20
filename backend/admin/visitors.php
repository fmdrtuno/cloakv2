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

// Xử lý yêu cầu chặn
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = '';
    $value = '';

    if (isset($_POST['block_ip'])) {
        $type = 'ip';
        $value = $_POST['block_ip'];
    } elseif (isset($_POST['block_ua'])) {
        $type = 'ua';
        $value = $_POST['block_ua'];
    }

    if (!empty($type) && !empty($value)) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO blacklist (domain_id, type, value) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $domain_id, $type, $value);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Đã thêm '" . htmlspecialchars($value) . "' vào danh sách đen thành công.";
        } else {
            $_SESSION['message'] = "Lỗi khi thêm vào danh sách đen.";
        }
        $stmt->close();
        // Chuyển hướng để tránh gửi lại biểu mẫu và áp dụng trang hiện tại
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        header("Location: visitors.php?domain_id=" . $domain_id . "&page=" . $page);
        exit;
    }
}

// Lấy thông báo từ session
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Hàm lấy mã quốc gia từ IP, có sử dụng cache từ DB
function get_country_code_from_ip($ip, $mysqli) {
    static $runtime_cache = []; // Cache trong một lần chạy script
    if (isset($runtime_cache[$ip])) {
        return $runtime_cache[$ip];
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return null;
    }

    // 1. Kiểm tra cache trong DB trước
    $stmt = $mysqli->prepare("SELECT country_code FROM ip_country_cache WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $runtime_cache[$ip] = $row['country_code'];
        return $row['country_code'];
    }
    $stmt->close();

    // 2. Nếu không có trong cache, gọi API
    $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $runtime_cache[$ip] = null;
        return null;
    }
    
    $data = json_decode($response, true);
    $country_code = ($data && $data['status'] == 'success') ? $data['countryCode'] : null;

    // 3. Lưu kết quả vào DB cache (nếu có)
    if ($country_code) {
        $stmt_insert = $mysqli->prepare("INSERT INTO ip_country_cache (ip_address, country_code) VALUES (?, ?)");
        $stmt_insert->bind_param("ss", $ip, $country_code);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $runtime_cache[$ip] = $country_code;
    return $country_code;
}

// Lấy các tham số lọc
$search_ip = $_GET['search_ip'] ?? '';
$search_ua = $_GET['search_ua'] ?? '';
$filter_country = $_GET['filter_country'] ?? '';
$filter_bot = $_GET['filter_bot'] ?? '';

// Cài đặt phân trang
$records_per_page = 50;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu truy vấn SQL với các điều kiện lọc
$params = [];
$types = '';
$where_clauses = [];
$joins = '';

// Luôn lọc theo domain_id
$where_clauses[] = "v.domain_id = ?";
$params[] = $domain_id;
$types .= 'i';

if (!empty($search_ip)) {
    $where_clauses[] = "v.ip_address LIKE ?";
    $params[] = "%" . $search_ip . "%";
    $types .= 's';
}

if (!empty($search_ua)) {
    $where_clauses[] = "v.user_agent LIKE ?";
    $params[] = "%" . $search_ua . "%";
    $types .= 's';
}

if (!empty($filter_country)) {
    $joins = " JOIN ip_country_cache c ON v.ip_address = c.ip_address";
    $where_clauses[] = "c.country_code = ?";
    $params[] = $filter_country;
    $types .= 's';
}

if ($filter_bot !== '') {
    $where_clauses[] = "v.is_bot = ?";
    $params[] = $filter_bot;
    $types .= 'i';
}

$where_sql = " WHERE " . implode(' AND ', $where_clauses);

// Lấy tổng số bản ghi để tính toán số trang
$total_records_sql = "SELECT COUNT(v.id) FROM visitors v" . $joins . $where_sql;
$total_records_stmt = $mysqli->prepare($total_records_sql);
$total_records_stmt->bind_param($types, ...$params);
$total_records_stmt->execute();
$total_records_result = $total_records_stmt->get_result();
$total_records = $total_records_result->fetch_row()[0];
$total_records_stmt->close();
$total_pages = ceil($total_records / $records_per_page);

// Lấy danh sách khách truy cập cho trang hiện tại
$visitors = [];
// Lấy IP của các bot đã được xác định cho domain này
$bot_ips_stmt = $mysqli->prepare("SELECT DISTINCT ip_address FROM visitors WHERE domain_id = ? AND is_bot = 1");
$bot_ips_stmt->bind_param("i", $domain_id);
$bot_ips_stmt->execute();
$bot_ips_result = $bot_ips_stmt->get_result();
$bot_ips = [];
while ($row = $bot_ips_result->fetch_assoc()) {
    $bot_ips[] = $row['ip_address'];
}
$bot_ips_stmt->close();

$visitors_sql = "SELECT v.* FROM visitors v" . $joins . $where_sql . " ORDER BY v.visit_time DESC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($visitors_sql);

$fetch_params = array_merge($params, [$records_per_page, $offset]);
$fetch_types = $types . 'ii';
$stmt->bind_param($fetch_types, ...$fetch_params);

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Nếu IP của khách truy cập nằm trong danh sách IP của bot, đánh dấu là bot
    if (in_array($row['ip_address'], $bot_ips)) {
        $row['is_bot'] = 1;
    }
    $visitors[] = $row;
}
$stmt->close();

// Xây dựng chuỗi query cho phân trang
$query_params = ['domain_id' => $domain_id];
if (!empty($search_ip)) $query_params['search_ip'] = $search_ip;
if (!empty($search_ua)) $query_params['search_ua'] = $search_ua;
if (!empty($filter_country)) $query_params['filter_country'] = $filter_country;
if ($filter_bot !== '') $query_params['filter_bot'] = $filter_bot;
$pagination_query_string = http_build_query($query_params);

// Lấy IP đáng ngờ
$suspicious_ips_stmt = $mysqli->prepare("
    SELECT v.ip_address, COUNT(DISTINCT v.user_agent) as ua_count, COUNT(v.id) as visit_count
    FROM visitors v
    LEFT JOIN blacklist b ON v.domain_id = b.domain_id AND v.ip_address = b.value AND b.type = 'ip'
    WHERE v.domain_id = ? AND b.id IS NULL
    GROUP BY v.ip_address
    HAVING ua_count >= 2 AND visit_count >= 2
    ORDER BY visit_count DESC
    LIMIT 10
");
$suspicious_ips_stmt->bind_param("i", $domain_id);
$suspicious_ips_stmt->execute();
$suspicious_ips_result = $suspicious_ips_stmt->get_result();
$suspicious_ips = [];
while ($row = $suspicious_ips_result->fetch_assoc()) {
    $suspicious_ips[] = $row;
}
$suspicious_ips_stmt->close();

// Lấy danh sách mẫu chặn (chỉ admin)
$templates = [];
if ($_SESSION['role'] === 'admin') {
    $templates_result = $mysqli->query("SELECT id, name FROM blocking_templates ORDER BY name");
    while ($template = $templates_result->fetch_assoc()) {
        $templates[] = $template;
    }
}

$page_title = 'Nhật ký khách truy cập';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?> cho <?php echo htmlspecialchars($current_domain_name); ?></h1>
    <a href="domains.php" class="btn btn-secondary">Quay lại danh sách Domain</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<?php if (!empty($suspicious_ips)): ?>
<div class="card mb-4">
    <div class="card-header bg-warning">
        <i class="bi bi-exclamation-triangle-fill"></i> Gợi ý chặn IP
    </div>
    <div class="card-body">
        <p>Các địa chỉ IP sau đây đã truy cập nhiều lần với các User Agent khác nhau và chưa bị chặn. Bạn nên xem xét chặn chúng.</p>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Địa chỉ IP</th>
                        <th>Số lượt truy cập</th>
                        <th>Số User Agent khác nhau</th>
                        <th style="width: 40%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suspicious_ips as $ip_info): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ip_info['ip_address']); ?></td>
                        <td><?php echo $ip_info['visit_count']; ?></td>
                        <td><?php echo $ip_info['ua_count']; ?></td>
                        <td>
                            <form action="visitors.php?domain_id=<?php echo $domain_id; ?>" method="post" class="d-inline-block me-1">
                                <input type="hidden" name="block_ip" value="<?php echo htmlspecialchars($ip_info['ip_address']); ?>">
                                <button type="submit" class="btn btn-warning btn-sm" title="Chặn IP này cho riêng domain này"><i class="bi bi-shield-slash-fill"></i> Chặn IP</button>
                            </form>
                            <?php if ($_SESSION['role'] === 'admin' && !empty($templates)): ?>
                            <form action="add_to_template.php" method="post" class="d-inline-block">
                                <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
                                <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($ip_info['ip_address']); ?>">
                                <div class="input-group input-group-sm">
                                    <select name="template_id" class="form-select form-select-sm">
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-danger btn-sm" title="Thêm IP vào mẫu và áp dụng cho tất cả domain dùng mẫu này"><i class="bi bi-journal-plus"></i> Thêm vào mẫu</button>
                                </div>
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
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Bộ lọc tìm kiếm</div>
    <div class="card-body">
        <form action="visitors.php" method="get" class="row g-3 align-items-center">
            <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
            <div class="col-md-3">
                <label for="search_ip" class="form-label">Địa chỉ IP</label>
                <input class="form-control" type="search" placeholder="Tìm theo IP..." name="search_ip" value="<?php echo htmlspecialchars($_GET['search_ip'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="search_ua" class="form-label">User Agent</label>
                <input class="form-control" type="search" placeholder="Tìm theo User Agent..." name="search_ua" value="<?php echo htmlspecialchars($_GET['search_ua'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="filter_country" class="form-label">Quốc gia</label>
                <select name="filter_country" id="filter_country" class="form-select">
                    <option value="">Tất cả quốc gia</option>
                    <?php
                    // Lấy danh sách các quốc gia duy nhất từ các lượt truy cập của domain này
                    $country_stmt = $mysqli->prepare("SELECT DISTINCT c.country_code FROM visitors v JOIN ip_country_cache c ON v.ip_address = c.ip_address WHERE v.domain_id = ? AND c.country_code IS NOT NULL ORDER BY c.country_code");
                    $country_stmt->bind_param("i", $domain_id);
                    $country_stmt->execute();
                    $country_result = $country_stmt->get_result();
                    while ($country = $country_result->fetch_assoc()) {
                        $selected = (isset($_GET['filter_country']) && $_GET['filter_country'] == $country['country_code']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($country['country_code']) . "' {$selected}>" . htmlspecialchars($country['country_code']) . "</option>";
                    }
                    $country_stmt->close();
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filter_bot" class="form-label">Loại</label>
                <select name="filter_bot" id="filter_bot" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="1" <?php echo (isset($_GET['filter_bot']) && $_GET['filter_bot'] === '1') ? 'selected' : ''; ?>>Chỉ Bot</option>
                    <option value="0" <?php echo (isset($_GET['filter_bot']) && $_GET['filter_bot'] === '0') ? 'selected' : ''; ?>>Chỉ người dùng</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-self-end">
                <button class="btn btn-primary w-100" type="submit">Lọc</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Danh sách khách truy cập gần đây</span>
        <span class="badge bg-info"><?php echo "Tổng số: " . $total_records; ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th scope="col">Địa chỉ IP</th>
                        <th scope="col">Quốc gia</th>
                        <th scope="col">Loại</th>
                        <th scope="col">User Agent</th>
                        <th scope="col">Thời gian truy cập</th>
                        <th scope="col">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitors as $visitor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($visitor['ip_address']); ?></td>
                        <td>
                            <?php
                                $country_code = get_country_code_from_ip($visitor['ip_address'], $mysqli);
                                if ($country_code) {
                                    echo "<img src=\"https://flagcdn.com/16x12/" . strtolower($country_code) . ".png\" alt=\"" . $country_code . "\"> ";
                                    echo htmlspecialchars($country_code);
                                } else { echo 'N/A'; }
                            ?>
                        </td>
                        <td>
                            <?php if ($visitor['is_bot']): ?>
                                <span class="badge bg-danger">Bot</span>
                            <?php else: ?>
                                <span class="badge bg-success">Người dùng</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 400px; overflow-wrap: break-word;"><?php echo htmlspecialchars($visitor['user_agent']); ?></td>
                        <td><?php echo $visitor['visit_time']; ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <form action="visitors.php?domain_id=<?php echo $domain_id; ?>" method="post" class="d-inline">
                                    <input type="hidden" name="block_ip" value="<?php echo htmlspecialchars($visitor['ip_address']); ?>">
                                    <button type="submit" class="btn btn-warning btn-sm" title="Chặn IP này"><i class="bi bi-geo-alt-fill"></i> Chặn IP</button>
                                </form>
                                <form action="visitors.php?domain_id=<?php echo $domain_id; ?>" method="post" class="d-inline ms-1">
                                    <input type="hidden" name="block_ua" value="<?php echo htmlspecialchars($visitor['user_agent']); ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Chặn User Agent này"><i class="bi bi-display-fill"></i> Chặn UA</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="?<?php echo $pagination_query_string . '&page=' . ($page - 1); ?>">Trước</a>
                </li>
                <li class="page-item active"><span class="page-link">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></span></li>
                <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                    <a class="page-link" href="?<?php echo $pagination_query_string . '&page=' . ($page + 1); ?>">Sau</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
