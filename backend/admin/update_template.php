<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

if (!isset($_POST['domain_id']) || !isset($_POST['template_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing domain_id or template_id.']);
    exit;
}

$domain_id = (int)$_POST['domain_id'];
$template_id = (int)$_POST['template_id'];

// Security check: Ensure the user has permission to modify this domain
if (!check_domain_permission($mysqli, $domain_id)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

// Update the database
$stmt = $mysqli->prepare("UPDATE domains SET template_id = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $template_id, $domain_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare database statement.']);
}

$mysqli->close();
?>
