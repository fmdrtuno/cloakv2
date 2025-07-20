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
    $ip_address = filter_input(INPUT_POST, 'ip_address', FILTER_VALIDATE_IP);

    if ($domain_id && $template_id && $ip_address) {
        // Optional: Check if user has permission for this domain
        check_domain_permission($mysqli, $domain_id);

        // Add the IP to the template rules
        $stmt = $mysqli->prepare("INSERT IGNORE INTO template_rules (template_id, type, value) VALUES (?, 'ip', ?)");
        $stmt->bind_param("is", $template_id, $ip_address);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Đã thêm IP '" . htmlspecialchars($ip_address) . "' vào mẫu chặn thành công.";
        } else {
            $_SESSION['message'] = "Lỗi khi thêm IP vào mẫu chặn.";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Dữ liệu không hợp lệ để thêm vào mẫu.";
    }
}

// Redirect back to the visitors page
$redirect_url = "visitors.php?domain_id=" . ($domain_id ?: '');
header("Location: " . $redirect_url);
exit;
?>
