<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$domain_id = filter_input(INPUT_POST, 'domain_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

if ($domain_id === false || $status === false || ($status !== 0 && $status !== 1)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Check if the user has permission for this domain
if (!check_domain_permission($mysqli, $domain_id)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE domains SET redirect_mode = ? WHERE id = ?");
$stmt->bind_param("ii", $status, $domain_id);

if ($stmt->execute()) {
    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$stmt->close();
$mysqli->close();
?>
