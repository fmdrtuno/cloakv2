<?php
/**
 * Global Configuration and Session Management
 *
 * This file is included in all PHP scripts and handles the entire application's
 * configuration and session initialization. By centralizing session management here,
 * we ensure that all parts of the application operate on the same session.
 */

// --- GLOBAL SESSION MANAGEMENT ---
// Conditionally start session unless explicitly told not to.
// This allows the public API to be stateless while the admin panel remains stateful.
if (!defined('NO_SESSION_START')) {
    if (session_status() == PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0, // Session lasts until the browser is closed.
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '', // Use the current host.
            'secure' => isset($_SERVER['HTTPS']), // Use secure cookies if on HTTPS.
            'httponly' => true, // Prevent client-side script access.
            'samesite' => 'Lax' // CSRF protection.
        ]);
        session_start();
    }
}

// --- Environment Configuration ---

// --- Error Reporting ---
// Set to 'development' to display errors, 'production' to hide them.
define('APP_ENV', 'production');

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// --- Cache Manager ---
// This must be included before it's used in other scripts.
require_once __DIR__ . '/../cache/CacheManager.php';

// --- Constants Definition ---
// --- DATABASE SETTINGS ---
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'tuno_cloakv2');
define('DB_PASSWORD', 'Tuno510'); // IMPORTANT: SET YOUR DATABASE PASSWORD HERE
define('DB_NAME', 'tuno_cloakv2');

// --- REDIS CACHE SETTINGS ---
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

// --- Database Connection ---
$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    // In production, log error instead of displaying it
    if (APP_ENV === 'development') {
        die("DATABASE CONNECTION ERROR: " . $mysqli->connect_error);
    } else {
        // In a real production environment, you would log this error to a file
        header('HTTP/1.1 500 Internal Server Error');
        die("A critical database error occurred.");
    }
}

// Set character set to utf8mb4 for full Unicode support
$mysqli->set_charset("utf8mb4");

// --- Initial Table Setup ---
// This ensures the application can run without manual DB setup.
function setup_initial_tables($mysqli) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password` varchar(255) NOT NULL,
          `role` enum('admin','manager','user') NOT NULL DEFAULT 'user',
          `parent_id` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`),
          KEY `parent_id` (`parent_id`),
          CONSTRAINT `users_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `domains` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `domain_name` varchar(255) NOT NULL,
          `api_key` varchar(255) NOT NULL,
          `stealth_mode` tinyint(1) NOT NULL DEFAULT 0,
          `redirect_mode` tinyint(1) NOT NULL DEFAULT 0,
          `honeypot_enabled` tinyint(1) NOT NULL DEFAULT 0,
          `verification_mode` int(11) NOT NULL DEFAULT 0,
          `template_id` int(11) NOT NULL DEFAULT 1,
          PRIMARY KEY (`id`),
          UNIQUE KEY `domain_name` (`domain_name`),
          KEY `user_id` (`user_id`),
          CONSTRAINT `domains_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS `blocking_templates` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `template_rules` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `template_id` int(11) NOT NULL,
          `type` enum('ip','ua') NOT NULL,
          `value` varchar(255) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `template_id` (`template_id`),
          CONSTRAINT `template_rules_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `blocking_templates` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `visitors` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `domain_id` int(11) NOT NULL,
          `ip_address` varchar(45) NOT NULL,
          `user_agent` text NOT NULL,
          `visit_time` timestamp NOT NULL DEFAULT current_timestamp(),
          `is_bot` tinyint(1) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `domain_id` (`domain_id`),
          CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            die("TABLE CREATION ERROR: " . $mysqli->error);
        }
    }
}

// Run setup only if needed (e.g., check for a specific table)
if ($mysqli->query("SHOW TABLES LIKE 'users'")->num_rows == 0) {
    setup_initial_tables($mysqli);
}


// --- Security and Permission Functions ---

/**
 * Checks if the logged-in user has permission to access a specific domain.
 * This function is critical for the admin panel's security.
 *
 * @param mysqli $mysqli The database connection object.
 * @param int $domain_id The ID of the domain to check.
 * @return bool True if access is granted, false otherwise.
 */
function check_domain_permission($mysqli, $domain_id) {
    // Session is now started globally from config.php
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'], $_SESSION['role'])) {
        return false; // Not logged in
    }

    $user_id = $_SESSION['id'];
    $user_role = $_SESSION['role'];

    if ($user_role === 'admin') {
        return true; // Admin has universal access
    }

    // Get the owner of the domain
    $stmt = $mysqli->prepare("SELECT user_id FROM domains WHERE id = ?");
    if(!$stmt) return false;
    $stmt->bind_param("i", $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $domain = $result->fetch_assoc();
    $stmt->close();

    if (!$domain) {
        return false; // Domain does not exist
    }
    $owner_id = $domain['user_id'];

    // 'user' can only access their own domains
    if ($user_role === 'user') {
        return $owner_id == $user_id;
    }

    // 'manager' can access their own domains and the domains of users they manage
    if ($user_role === 'manager') {
        if ($owner_id == $user_id) {
            return true; // Access to own domain
        }

        // Check if the domain owner is a "child" of the manager
        $stmt_child = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND parent_id = ?");
        if(!$stmt_child) return false;
        $stmt_child->bind_param("ii", $owner_id, $user_id);
        $stmt_child->execute();
        $is_child = $stmt_child->get_result()->num_rows > 0;
        $stmt_child->close();
        
        return $is_child;
    }

    return false; // Default to deny
}
?>
