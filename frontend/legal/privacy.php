<?php
// This file is intentionally left blank.
// Its purpose is to be an endpoint for the honeypot link.
// The presence of a request to this file indicates bot activity.

// Get the config from the main directory
$config_file = __DIR__ . '/../config.ini';
if (!file_exists($config_file)) {
    // If no config, we can't do anything. Silently fail.
    http_response_code(404);
    exit;
}
$config = parse_ini_file($config_file);

$api_base_url = $config['base_url'];
$this_api_key = $config['key'];
$this_domain_name = $config['domain'];

// Call the API to log the honeypot trigger
$api_url = rtrim($api_base_url, '/') . "/api.php";
$post_data = [
    'action' => 'honeypot_triggered',
    'api_key' => $this_api_key,
    'domain_name' => $this_domain_name,
    'ip_address' => $_SERVER['REMOTE_ADDR']
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_exec($ch);
curl_close($ch);

// Redirect to a non-existent page or the homepage to confuse the bot
header("Location: /");
exit;
?>
