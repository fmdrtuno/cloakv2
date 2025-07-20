<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $domain_id = filter_input(INPUT_POST, 'domain_id', FILTER_VALIDATE_INT);
    $template_id = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);

    if ($domain_id && $template_id) {
        // Check permission
        check_domain_permission($mysqli, $domain_id);

        // Start transaction
        $mysqli->begin_transaction();

        try {
            // 1. Delete existing rules for the domain
            $stmt_delete = $mysqli->prepare("DELETE FROM blacklist WHERE domain_id = ?");
            $stmt_delete->bind_param("i", $domain_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // 2. Get all rules from the template
            $template_rules = [];
            $stmt_get_rules = $mysqli->prepare("SELECT type, value FROM template_rules WHERE template_id = ?");
            $stmt_get_rules->bind_param("i", $template_id);
            $stmt_get_rules->execute();
            $result_rules = $stmt_get_rules->get_result();
            while ($row = $result_rules->fetch_assoc()) {
                $template_rules[] = $row;
            }
            $stmt_get_rules->close();

            // 3. Insert new rules for the domain
            if (!empty($template_rules)) {
                $stmt_insert = $mysqli->prepare("INSERT INTO blacklist (domain_id, type, value) VALUES (?, ?, ?)");
                foreach ($template_rules as $rule) {
                    $stmt_insert->bind_param("iss", $domain_id, $rule['type'], $rule['value']);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }

            // Commit transaction
            $mysqli->commit();

            $_SESSION['message'] = "Đã áp dụng mẫu thành công!";

        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            $_SESSION['message'] = "Lỗi khi áp dụng mẫu: " . $exception->getMessage();
        }
    } else {
        $_SESSION['message'] = "Dữ liệu không hợp lệ.";
    }
}

header("Location: domains.php");
exit;
?>
