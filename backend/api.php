<?php
define('NO_SESSION_START', true);
require_once 'config/config.php';
require_once __DIR__ . '/cache/CacheManager.php';

header('Content-Type: application/json');

// Basic security check: Ensure API key and domain are provided
$api_key = $_REQUEST['api_key'] ?? '';
$domain_name = $_REQUEST['domain_name'] ?? '';
$action = $_REQUEST['action'] ?? '';

if (empty($api_key) || empty($domain_name)) {
    echo json_encode(['error' => 'API key and domain name are required.']);
    exit;
}

// Authenticate the domain and API key
$stmt = $mysqli->prepare("SELECT id, stealth_mode, redirect_mode, honeypot_enabled, verification_mode FROM domains WHERE domain_name = ? AND api_key = ?");
$stmt->bind_param("ss", $domain_name, $api_key);
$stmt->execute();
$result = $stmt->get_result();
$domain = $result->fetch_assoc();
$stmt->close();

if (!$domain) {
    echo json_encode(['error' => 'Invalid domain name or API key.']);
    exit;
}

$domain_id = $domain['id'];

// Process the requested action
switch ($action) {
    case 'get_page_data':
        $cache = new CacheManager();
        $cache_key = "domain_settings_{$domain_id}";
        $cached_data = $cache->get($cache_key);

        if ($cached_data) {
            echo json_encode($cached_data);
            break;
        }

        $settings = [];
        $stmt_settings = $mysqli->prepare("SELECT setting_key, setting_value FROM settings WHERE domain_id = ?");
        $stmt_settings->bind_param("i", $domain_id);
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        while ($row = $result_settings->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $stmt_settings->close();

        $blacklist = [];
        $stmt_blacklist = $mysqli->prepare("SELECT type, value FROM blacklist WHERE domain_id = ?");
        $stmt_blacklist->bind_param("i", $domain_id);
        $stmt_blacklist->execute();
        $result_blacklist = $stmt_blacklist->get_result();
        while ($row = $result_blacklist->fetch_assoc()) {
            $blacklist[] = $row;
        }
        $stmt_blacklist->close();

        $country_rules = [];
        $stmt_countries = $mysqli->prepare("SELECT country_code, rule_type FROM country_rules WHERE domain_id = ?");
        $stmt_countries->bind_param("i", $domain_id);
        $stmt_countries->execute();
        $result_countries = $stmt_countries->get_result();
        while ($row = $result_countries->fetch_assoc()) {
            $country_rules[] = $row;
        }
        $stmt_countries->close();

        $questions = [];
        $result = $mysqli->query("SHOW TABLES LIKE 'questions'");
        if ($result->num_rows > 0) {
            $stmt_questions = $mysqli->prepare("SELECT question, answer, hint FROM questions WHERE domain_id = ?");
            $stmt_questions->bind_param("i", $domain_id);
            $stmt_questions->execute();
            $result_questions = $stmt_questions->get_result();
            while ($row = $result_questions->fetch_assoc()) {
                $questions[] = $row;
            }
            $stmt_questions->close();
        }

        $ip_address = $_SERVER['REMOTE_ADDR'];
        $country_code = get_country_code_from_ip($ip_address);

        $response_data = [
            'success' => true,
            'settings' => $settings,
            'blacklist' => $blacklist,
            'country_rules' => $country_rules,
            'questions' => $questions,
            'stealth_mode' => (bool)$domain['stealth_mode'],
            'redirect_mode' => (bool)$domain['redirect_mode'],
            'honeypot_enabled' => (bool)$domain['honeypot_enabled'],
            'verification_mode' => (int)($domain['verification_mode'] ?? 0),
            'country_code' => $country_code,
            'contact_name' => $settings['contact_name'] ?? '',
            'contact_address' => $settings['contact_address'] ?? '',
            'contact_email' => $settings['contact_email'] ?? '',
            'contact_phone' => $settings['contact_phone'] ?? ''
        ];

        $cache->set($cache_key, $response_data);
        echo json_encode($response_data);
        break;

    case 'honeypot_triggered':
        $ip_address = $_POST['ip_address'] ?? $_SERVER['REMOTE_ADDR'];
        
        $stmt_update_visitors = $mysqli->prepare("UPDATE visitors SET is_bot = 1 WHERE domain_id = ? AND ip_address = ?");
        $stmt_update_visitors->bind_param("is", $domain_id, $ip_address);
        $stmt_update_visitors->execute();
        $stmt_update_visitors->close();

        $stmt_add_blacklist = $mysqli->prepare("INSERT IGNORE INTO blacklist (domain_id, type, value) VALUES (?, 'ip', ?)");
        $stmt_add_blacklist->bind_param("is", $domain_id, $ip_address);
        $stmt_add_blacklist->execute();
        $stmt_add_blacklist->close();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Bot flagged and IP blacklisted.']);
        break;

    case 'log_visitor':
        $ip_address = $_POST['ip_address'] ?? $_SERVER['REMOTE_ADDR'];
        $user_agent = $_POST['user_agent'] ?? '';
        $stmt_log = $mysqli->prepare("INSERT INTO visitors (domain_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt_log->bind_param("iss", $domain_id, $ip_address, $user_agent);
        $stmt_log->execute();
        $stmt_log->close();
        echo json_encode(['success' => true]);
        break;

    case 'get_secure_redirect_url':
        $stmt_url = $mysqli->prepare("SELECT setting_value FROM settings WHERE domain_id = ? AND setting_key = 'button_href'");
        $stmt_url->bind_param("i", $domain_id);
        $stmt_url->execute();
        $result_url = $stmt_url->get_result();
        $url_row = $result_url->fetch_assoc();
        $stmt_url->close();

        echo json_encode([
            'success' => true,
            'redirect_url' => $url_row['setting_value'] ?? '#'
        ]);
        break;

    case 'check_verification':
        $answer = $_POST['answer'] ?? '';
        $stmt = $mysqli->prepare("SELECT answer FROM questions WHERE domain_id = ?");
        $stmt->bind_param("i", $domain_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $correct = false;
        foreach ($questions as $question) {
            if (strtolower(trim($answer)) == strtolower(trim($question['answer']))) {
                $correct = true;
                break;
            }
        }

        if ($correct) {
            // Fetch the redirect URL to send back to the client.
            $stmt_url = $mysqli->prepare("SELECT setting_value FROM settings WHERE domain_id = ? AND setting_key = 'button_href'");
            $stmt_url->bind_param("i", $domain_id);
            $stmt_url->execute();
            $result_url = $stmt_url->get_result();
            $url_row = $result_url->fetch_assoc();
            $stmt_url->close();

            echo json_encode([
                'success' => true,
                'message' => 'Verification passed.',
                'redirect_url' => $url_row['setting_value'] ?? '#'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incorrect answer.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
        break;
}

$mysqli->close();

function get_country_code_from_ip($ip) {
    $cache = new CacheManager();
    $cache_key = "ip_country_{$ip}";
    
    // Try to get data from cache first
    $cached_country_code = $cache->get($cache_key);
    if ($cached_country_code) {
        return $cached_country_code;
    }

    // If cache miss, query the external API
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return null;
    }
    
    $url = "https://ip-api.com/json/{$ip}?fields=status,countryCode";
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    $country_code = ($data && $data['status'] == 'success') ? $data['countryCode'] : null;
    
    // Store the result in cache for 1 hour (3600 seconds)
    if ($country_code) {
        $cache->set($cache_key, $country_code, 3600);
    }
    
    return $country_code;
}
?>
