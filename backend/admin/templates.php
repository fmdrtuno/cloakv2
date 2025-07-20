<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-danger'>Bạn không có quyền truy cập trang này.</div>");
}

$page_title = 'Quản lý Mẫu Chặn';
require_once 'templates/header.php';

// Handle POST and GET requests
$selected_template_id = $_GET['template_id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new template
    if (isset($_POST['add_template'])) {
        $template_name = trim($_POST['template_name']);
        if (!empty($template_name)) {
            $stmt = $mysqli->prepare("INSERT INTO blocking_templates (name) VALUES (?)");
            $stmt->bind_param("s", $template_name);
            $stmt->execute();
            $selected_template_id = $mysqli->insert_id;
            $stmt->close();
        }
    }
    // Delete template
    elseif (isset($_POST['delete_template'])) {
        $template_id = $_POST['template_id'];
        $stmt = $mysqli->prepare("DELETE FROM blocking_templates WHERE id = ?");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $stmt->close();
        header("Location: templates.php");
        exit;
    }
    // Add rule to template
    elseif (isset($_POST['add_rule'])) {
        $template_id = $_POST['template_id'];
        $type = $_POST['type'];
        $value = trim($_POST['value']);
        if (!empty($value)) {
            $stmt = $mysqli->prepare("INSERT INTO template_rules (template_id, type, value) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $template_id, $type, $value);
            $stmt->execute();
            $stmt->close();
        }
        $selected_template_id = $template_id;
    }
    // Delete rule from template
    elseif (isset($_POST['delete_rule'])) {
        $rule_id = $_POST['rule_id'];
        $template_id = $_POST['template_id'];
        $stmt = $mysqli->prepare("DELETE FROM template_rules WHERE id = ?");
        $stmt->bind_param("i", $rule_id);
        $stmt->execute();
        $stmt->close();
        $selected_template_id = $template_id;
    }
    header("Location: templates.php?template_id=" . $selected_template_id);
    exit;
}

// Fetch all templates
$templates = [];
$result = $mysqli->query("SELECT * FROM blocking_templates ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

// Fetch rules for the selected template
$selected_template = null;
$rules = [];
if ($selected_template_id) {
    $stmt = $mysqli->prepare("SELECT * FROM blocking_templates WHERE id = ?");
    $stmt->bind_param("i", $selected_template_id);
    $stmt->execute();
    $result_template = $stmt->get_result();
    $selected_template = $result_template->fetch_assoc();
    $stmt->close();

    if ($selected_template) {
        $stmt_rules = $mysqli->prepare("SELECT * FROM template_rules WHERE template_id = ? ORDER BY type, value");
        $stmt_rules->bind_param("i", $selected_template_id);
        $stmt_rules->execute();
        $result_rules = $stmt_rules->get_result();
        while ($row = $result_rules->fetch_assoc()) {
            $rules[] = $row;
        }
        $stmt_rules->close();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?></h1>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Danh sách mẫu</div>
            <div class="list-group list-group-flush">
                <?php foreach ($templates as $template): ?>
                    <a href="templates.php?template_id=<?php echo $template['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_template_id == $template['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($template['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="card-body">
                <form action="templates.php" method="post">
                    <div class="input-group">
                        <input type="text" name="template_name" class="form-control" placeholder="Tên mẫu mới" required>
                        <button class="btn btn-primary" type="submit" name="add_template">Thêm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($selected_template): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Quy tắc cho mẫu: <?php echo htmlspecialchars($selected_template['name']); ?></span>
                    <form action="templates.php" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mẫu này và tất cả các quy tắc của nó?');">
                        <input type="hidden" name="template_id" value="<?php echo $selected_template['id']; ?>">
                        <button type="submit" name="delete_template" class="btn btn-danger btn-sm">Xóa mẫu</button>
                    </form>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Loại</th>
                                <th>Giá trị</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rules)): ?>
                                <tr><td colspan="3" class="text-center">Chưa có quy tắc nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td><?php echo $rule['type'] == 'ip' ? 'IP' : 'UA'; ?></td>
                                        <td><?php echo htmlspecialchars($rule['value']); ?></td>
                                        <td>
                                            <form action="templates.php" method="post">
                                                <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                <input type="hidden" name="template_id" value="<?php echo $selected_template['id']; ?>">
                                                <button type="submit" name="delete_rule" class="btn btn-outline-danger btn-sm">Xóa</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <form action="templates.php" method="post" class="row g-3">
                        <input type="hidden" name="template_id" value="<?php echo $selected_template['id']; ?>">
                        <div class="col-md-4">
                            <select name="type" class="form-select">
                                <option value="ip">IP</option>
                                <option value="ua">User Agent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="value" class="form-control" placeholder="Giá trị" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add_rule" class="btn btn-success w-100">Thêm</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Vui lòng chọn một mẫu từ danh sách bên trái hoặc tạo một mẫu mới.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
