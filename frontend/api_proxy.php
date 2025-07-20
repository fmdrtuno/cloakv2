<?php
/**
 * API Proxy (Secure & Refactored)
 *
 * This proxy securely forwards verification requests to the backend API.
 * It is responsible for adding the secret API key, which should never be
 * exposed to the client-side.
 */

header('Content-Type: application/json');

// --- SESSION MANAGEMENT ---
// This must be consistent across all user-facing entry points.
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

// --- CONFIGURATION ---
$config_file = __DIR__ . '/config.ini';
if (!file_exists($config_file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error: config.ini not found.']);
    exit;
}
$config = parse_ini_file($config_file);
if (empty($config['base_url']) || empty($config['key']) || empty($config['domain'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error: config.ini is incomplete.']);
    exit;
}

$api_base_url = $config['base_url'];
$api_key = $config['key']; // The secret API key.
$this_domain_name = preg_replace('/^www\./', '', (parse_url($config['domain'], PHP_URL_HOST) ?? $config['domain']));

// --- INPUT VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'check_verification') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Action not permitted.']);
    exit;
}

// --- PREPARE SECURE DATA FOR BACKEND ---
// The proxy adds the secret API key. The client does not need to know it.
$forward_data = [
    'action' => 'check_verification',
    'domain_name' => $this_domain_name,
    'api_key' => $api_key, // Add the secret key here.
    'answer' => $_POST['answer'] ?? ''
];

// --- FORWARD REQUEST TO BACKEND API using cURL (more robust) ---
$real_api_url = rtrim($api_base_url, '/') . '/api.php';
$post_data_query = http_build_query($forward_data);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $real_api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true); // We need headers to get the status code

// Forward the user's session cookie to the backend API.
if (isset($_COOKIE[session_name()])) {
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
}

$response_with_headers = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

// --- RELAY RESPONSE TO CLIENT ---
if ($response_with_headers === false) {
    http_response_code(502); // Bad Gateway
    echo json_encode(['success' => false, 'error' => 'Could not connect to the backend service via cURL.']);
} else {
    $response_body = substr($response_with_headers, $header_size);
    $data = json_decode($response_body, true);

    // If the backend confirmed success, update the session in the proxy's context.
    if ($data && isset($data['success']) && $data['success']) {
        $_SESSION["verification_passed_{$this_domain_name}"] = time();
    }

    // Forward the original status code and response from the backend.
    http_response_code($http_code);
    echo $response_body;
}
?>
