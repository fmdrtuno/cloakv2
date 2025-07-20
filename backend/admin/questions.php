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

// Xử lý thêm câu hỏi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_question'])) {
    $question = trim($_POST['question']);
    $answer = trim($_POST['answer']);
    $hint = trim($_POST['hint']);
    if (!empty($question) && !empty($answer)) {
        // Create table and column if not exists
        $mysqli->query("CREATE TABLE IF NOT EXISTS `questions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `domain_id` int(11) NOT NULL,
          `question` text NOT NULL,
          `answer` varchar(255) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `domain_id` (`domain_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $mysqli->query("ALTER TABLE `questions` ADD COLUMN IF NOT EXISTS `hint` TEXT;");

        $stmt = $mysqli->prepare("INSERT INTO questions (domain_id, question, answer, hint) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $domain_id, $question, $answer, $hint);
        $stmt->execute();
        $stmt->close();

        $cache = new CacheManager();
        $cache->delete("domain_settings_{$domain_id}");

        header("Location: questions.php?domain_id=" . $domain_id);
        exit;
    }
}

// Xử lý xóa câu hỏi
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM questions WHERE id = ? AND domain_id = ?");
    $stmt->bind_param("ii", $id, $domain_id);
    $stmt->execute();
    $stmt->close();

    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");

    header("location: questions.php?domain_id=" . $domain_id);
    exit;
}

// Lấy danh sách câu hỏi hiện tại cho domain đã chọn
$questions = [];
$result = $mysqli->query("SHOW TABLES LIKE 'questions'");
if ($result->num_rows > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM questions WHERE domain_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
}


$page_title = 'Quản lý câu hỏi xác thực';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?> cho <?php echo htmlspecialchars($current_domain_name); ?></h1>
    <a href="domains.php" class="btn btn-secondary">Quay lại danh sách Domain</a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Thêm câu hỏi mới
            </div>
            <div class="card-body">
                <form action="questions.php?domain_id=<?php echo $domain_id; ?>" method="post">
                    <div class="mb-3">
                        <label for="question" class="form-label">Câu hỏi</label>
                        <input type="text" id="question" name="question" class="form-control" required>
                        <div class="form-text">
                            Ví dụ: "Điền vào chỗ trống: '... ơi, ở lại làm người.'"
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="answer" class="form-label">Câu trả lời</label>
                        <input type="text" id="answer" name="answer" class="form-control" required>
                         <div class="form-text">
                            Ví dụ: "Chí Phèo" (Không phân biệt hoa thường)
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="hint" class="form-label">Gợi ý (tùy chọn)</label>
                        <input type="text" id="hint" name="hint" class="form-control">
                         <div class="form-text">
                            Ví dụ: "Một tác phẩm của Nam Cao"
                        </div>
                    </div>
                    <button type="submit" name="add_question" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Thêm
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Danh sách câu hỏi hiện tại
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th scope="col">Câu hỏi</th>
                                <th scope="col">Câu trả lời</th>
                                <th scope="col">Gợi ý</th>
                                <th scope="col">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['question']); ?></td>
                                <td><?php echo htmlspecialchars($item['answer']); ?></td>
                                <td><?php echo htmlspecialchars($item['hint'] ?? ''); ?></td>
                                <td>
                                    <a href="questions.php?domain_id=<?php echo $domain_id; ?>&delete=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa mục này?');">
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
