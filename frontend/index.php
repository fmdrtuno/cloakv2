<?php
/**
 * Main Frontend Controller (Refactored for Stability)
 *
 * This file handles all frontend logic, including API data fetching,
 * blacklist checks, verification logic, and rendering the final page.
 */

// --- FULL DEBUG MODE (for development only, remove in production) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// --- SETUP LOGIC (if config is missing) ---
if (!file_exists($config_file)) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['setup_submit'])) {
        $api_base_url = filter_input(INPUT_POST, 'api_base_url', FILTER_VALIDATE_URL);
        $this_api_key = trim($_POST['this_api_key']);
        $this_domain_name = trim($_POST['this_domain_name']);
        if (!$api_base_url || empty($this_api_key) || empty($this_domain_name)) {
            display_setup_form('Vui lòng điền đầy đủ và đúng định dạng các trường.');
        } else {
            $test_api_url = rtrim($api_base_url, '/') . "/api.php?action=get_page_data&domain_name=" . urlencode($this_domain_name) . "&api_key=" . urlencode($this_api_key);
            $context = stream_context_create(['http' => ['timeout' => 10]]);
            $test_response_json = @file_get_contents($test_api_url, false, $context);
            if ($test_response_json === false) {
                display_setup_form("<b>Lỗi kết nối:</b> Không thể kết nối đến API. Vui lòng kiểm tra lại URL.");
            } else {
                $test_data = json_decode($test_response_json, true);
                if ($test_data && isset($test_data['success']) && $test_data['success']) {
                    $ini_content = "base_url = \"{$api_base_url}\"\nkey = \"{$this_api_key}\"\ndomain = \"{$this_domain_name}\"\n";
                    if (file_put_contents($config_file, $ini_content) === false) {
                        display_setup_form("<b>Lỗi nghiêm trọng:</b> Không thể ghi tệp cấu hình `config.ini`. Vui lòng kiểm tra quyền ghi của thư mục.");
                    } else {
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } else {
                    $error_detail = htmlspecialchars($test_data['error'] ?? 'Không có phản hồi hoặc phản hồi không phải JSON.');
                    display_setup_form("<b>Thông tin không chính xác.</b><br>Lỗi từ API: " . $error_detail);
                }
            }
        }
    } else {
        display_setup_form('');
    }
    exit;
}

// --- MAIN APPLICATION LOGIC ---

// --- MAIN APPLICATION LOGIC ---

$config = parse_ini_file($config_file);
if (empty($config['base_url']) || empty($config['key']) || empty($config['domain'])) {
    // If config is incomplete or malformed, force setup again.
    unlink($config_file);
    display_setup_form('Tệp cấu hình không hợp lệ hoặc không đầy đủ. Vui lòng thiết lập lại.');
    exit;
}

$api_base_url = $config['base_url'];
$this_api_key = $config['key'];
$raw_domain = $config['domain'];
$this_domain_name = preg_replace('/^www\./', '', (parse_url($raw_domain, PHP_URL_HOST) ?? $raw_domain));

$data = fetch_api_data($api_base_url, $this_domain_name, $this_api_key);

$ip_address = get_ip_address();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$country_code = $data['country_code'] ?? null;
$is_blacklisted = ($data['stealth_mode'] ?? false) || is_visitor_blacklisted($ip_address, $user_agent, $data['blacklist'], $data['country_rules'], $country_code);

if ($is_blacklisted) {
    // Make settings available to the blacklist page.
    $settings = $data['settings'] ?? [];
    $honeypot_enabled = $data['honeypot_enabled'] ?? false;
    require_once __DIR__ . '/blacklist_page.php';
    exit;
}

log_visitor($api_base_url, $this_domain_name, $this_api_key, $ip_address);
render_page($data, $this_domain_name);

// --- FUNCTION DEFINITIONS ---

function fetch_api_data($api_base_url, $domain_name, $api_key) {
    $api_url = rtrim($api_base_url, '/') . "/api.php?action=get_page_data&domain_name=" . urlencode($domain_name) . "&api_key=" . urlencode($api_key) . "&t=" . time();
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response_json = @file_get_contents($api_url, false, $context);

    if ($response_json === false) {
        $error = error_get_last();
        die("FATAL: Could not connect to API. " . htmlspecialchars($error['message'] ?? 'Request timed out.'));
    }
    $data = json_decode($response_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("FATAL: Invalid JSON response from API. Response: " . htmlspecialchars($response_json));
    }
    if (!$data || !isset($data['success']) || !$data['success']) {
        die("FATAL: API returned an error. Message: " . htmlspecialchars($data['error'] ?? 'Unknown error.'));
    }
    return $data;
}

function render_page($data, $domain_name) {
    $settings = $data['settings'] ?? [];
    $questions = $data['questions'] ?? [];
    $verification_mode = $data['verification_mode'] ?? 0;
    $redirect_mode_enabled = $data['redirect_mode'] ?? false;
    $honeypot_enabled = $data['honeypot_enabled'] ?? false;

    if ($verification_mode > 0 && empty($questions)) {
        $verification_mode = 0;
    }

    $lifetime = (int)($settings['verification_lifetime'] ?? 10);
    $is_verified = isset($_SESSION["verification_passed_{$domain_name}"]) && (time() - $_SESSION["verification_passed_{$domain_name}"]) < $lifetime;

    if ($is_verified) {
        if ($redirect_mode_enabled) {
            header("Location: " . ($settings['button_href'] ?? '#'));
            exit;
        }
        $verification_mode = 0;
    } else {
        if ($redirect_mode_enabled && $verification_mode == 0) {
            header("Location: " . ($settings['button_href'] ?? '#'));
            exit;
        }
    }

    if ($verification_mode === 1 && !$is_verified) {
        echo build_minimal_popup_page($settings, $questions, $redirect_mode_enabled);
    } else {
        // Pass the authoritative $is_verified status to the build function.
        echo build_full_page($settings, $questions, $verification_mode, $redirect_mode_enabled, $honeypot_enabled, $domain_name, $is_verified);
    }
}

function build_minimal_popup_page($settings, $questions, $redirect_mode_enabled) {
    $html = "<!DOCTYPE html><html lang=\"vi\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Xác thực</title>";
    $html .= get_verification_popup_styles();
    $html .= "</head><body>";
    $html .= get_verification_popup_html($settings, $questions);
    $html .= get_config_and_scripts_html(1, $redirect_mode_enabled, false);
    $html .= "</body></html>";
    return $html;
}

function build_full_page($settings, $questions, $verification_mode, $redirect_mode_enabled, $honeypot_enabled, $domain_name, $is_verified) {
    $template_path = __DIR__ . '/templates/1/index.html';
    if (!file_exists($template_path)) {
        die("FATAL: Template file not found.");
    }
    $html = file_get_contents($template_path);

    // Use the authoritative $is_verified status passed from render_page.
    $needs_verification = ($verification_mode > 0 && !$is_verified);
    $initial_button_href = $needs_verification ? ($settings['blacklist_href'] ?? '#') : ($settings['button_href'] ?? '#');
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $asset_base_path = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/templates/1/';
    $html = str_replace(['href="./', 'src="./'], ['href="' . $asset_base_path, 'src="' . $asset_base_path], $html);

    $replacements = [
        '<!--GOOGLE_GTAG_PLACEHOLDER-->' => get_tracking_script('google', $settings),
        '<!--TIKTOK_PIXEL_PLACEHOLDER-->' => get_tracking_script('tiktok', $settings),
        '<!--META_PIXEL_PLACEHOLDER-->' => get_tracking_script('meta', $settings),
        '<!--BUTTON_HREF_PLACEHOLDER-->' => htmlspecialchars($initial_button_href),
        '<!--CONTACT_INFO_PLACEHOLDER-->' => get_contact_info_html($settings),
    ];
    $html = str_replace(array_keys($replacements), array_values($replacements), $html);

    $injection_html = '';
    // Inject verification popup for modes 1 (popup-only) and 2 (overlay)
    if (($verification_mode === 1 || $verification_mode === 2) && !$is_verified) {
        $injection_html .= get_verification_popup_styles();
        $injection_html .= get_verification_popup_html($settings, $questions);
    }
    $injection_html .= get_config_and_scripts_html($verification_mode, $redirect_mode_enabled, $honeypot_enabled);
    
    return str_replace('</body>', $injection_html . "\n</body>", $html);
}

function get_verification_popup_html($settings, $questions) {
    if (empty($questions)) return '';
    $question = $questions[array_rand($questions)];
    $title = htmlspecialchars($settings['verification_title'] ?? 'Xác nhận');
    $q_text = htmlspecialchars($question['question']);
    $hint = !empty($question['hint']) ? '<p class="hint">' . htmlspecialchars($question['hint']) . '</p>' : '';
    return <<<HTML
<div id="verification-overlay"><div class="content"><h2>{$title}</h2><p>{$q_text}</p>{$hint}<form id="verification-form"><input type="text" id="verification-answer" placeholder="Nhập câu trả lời..." autofocus><button type="submit" id="verification-submit-button">Xác nhận</button></form><p id="verification-error" class="error" style="display: none;"></p></div></div>
HTML;
}

function get_config_and_scripts_html($verification_mode, $redirect_mode_enabled, $honeypot_enabled) {
    $config_div = '<div id="verification-config" style="display: none;" 
        data-verification-mode="' . intval($verification_mode) . '" 
        data-redirect-mode="' . ($redirect_mode_enabled ? 'true' : 'false') . '"></div>';
    
    $verification_script = '<script src="verification.js?v=' . time() . '"></script>';
    $honeypot_script = $honeypot_enabled ? "<script>(function(){var a=document.createElement('a');a.href='legal/privacy.php';a.style.cssText='position:absolute;left:-9999px;';document.body.appendChild(a);})();</script>" : '';
    
    return $config_div . $verification_script . $honeypot_script;
}

function get_ip_address() {
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($headers as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_visitor($api_base_url, $domain, $api_key, $ip_address) {
    $api_log_url = rtrim($api_base_url, '/') . "/api.php";
    $log_data = [
        'action' => 'log_visitor',
        'domain_name' => $domain,
        'api_key' => $api_key,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip_address' => $ip_address
    ];
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($log_data), 'timeout' => 2]];
    $context = stream_context_create($options);
    @file_get_contents($api_log_url, false, $context);
}

function is_visitor_blacklisted($ip, $ua, $blacklist_rules, $country_rules, $country_code) {
    foreach ($blacklist_rules as $rule) {
        if (($rule['type'] == 'ua' && !empty($rule['value']) && stripos($ua, $rule['value']) !== false) ||
            ($rule['type'] == 'ip' && !empty($rule['value']) && ip_in_range($ip, $rule['value']))) {
            return true;
        }
    }
    if ($country_code) {
        $whitelist = array_column(array_filter($country_rules, fn($r) => $r['rule_type'] == 'whitelist'), 'country_code');
        $blacklist = array_column(array_filter($country_rules, fn($r) => $r['rule_type'] == 'blacklist'), 'country_code');
        if (!empty($whitelist) && !in_array($country_code, $whitelist)) return true;
        if (empty($whitelist) && !empty($blacklist) && in_array($country_code, $blacklist)) return true;
    }
    return false;
}

function ip_in_range($ip, $range) {
    if (strpos($range, '/') !== false) {
        list($subnet, $bits) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        if ($ip_long === false || $subnet_long === false) return false;
        $mask = -1 << (32 - (int)$bits);
        return ($ip_long & $mask) == ($subnet_long & $mask);
    }
    return $ip === $range;
}

function get_verification_popup_styles() {
    return '<style>#verification-overlay{position:fixed;top:0;left:0;width:100%;height:100%;display:flex;justify-content:center;align-items:center;background-color:rgba(0,0,0,0.8);z-index:9999;font-family:sans-serif;padding:15px;}#verification-overlay .content{background-color:#fff;padding:30px;border-radius:10px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,0.3);width:100%;max-width:500px;box-sizing:border-box;}#verification-overlay h2{margin-bottom:20px}#verification-overlay p{font-size:1.2em;font-weight:bold;color:#495057;margin-bottom:10px}#verification-overlay .hint{font-size:.9em;font-weight:normal;color:#6c757d;margin-bottom:20px}#verification-overlay form{display:flex;flex-direction:column;gap:10px;}#verification-overlay input{padding:12px;border:1px solid #ccc;border-radius:5px;width:100%;box-sizing:border-box;}#verification-overlay button{padding:12px 20px;border:none;background-color:#dc3545;color:#fff;border-radius:5px;cursor:pointer;width:100%;box-sizing:border-box;}#verification-overlay button:hover{background-color:#c82333}#verification-overlay .error{color:#dc3545;margin-top:10px}@media (max-width:600px){#verification-overlay .content{padding:20px;}}</style>';
}

function get_tracking_script($type, $settings) {
    $id = '';
    switch ($type) {
        case 'google': $id = $settings['google_gtag_id'] ?? ''; break;
        case 'tiktok': $id = $settings['tiktok_pixel_id'] ?? ''; break;
        case 'meta': $id = $settings['meta_pixel_id'] ?? ''; break;
    }
    if (empty($id)) return '';
    $id = htmlspecialchars($id);
    switch ($type) {
        case 'google': return "<!-- Google GTAG -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>";
        case 'tiktok': return "<!-- TikTok Pixel -->\n<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i=\"https://analytics.tiktok.com/i18n/pixel/events.js\";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e].pixelId=e,ttq._i[e].store=n;var o=d.createElement(\"script\");o.type=\"text/javascript\",o.async=!0,o.src=i+\"?sdkid=\"+e+\"&lib=\"+t;var a=d.getElementsByTagName(\"script\")[0];a.parentNode.insertBefore(o,a)}}(window,document,'ttq');ttq.load('{$id}');ttq.page();</script>";
        case 'meta': return "<!-- Meta Pixel -->\n<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>\n<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1\"/></noscript>";
    }
    return '';
}

function get_contact_info_html($settings) {
    $html = '<div class="contact-info">';
    if (!empty($settings['contact_name'])) $html .= '<div><strong>' . htmlspecialchars($settings['contact_name']) . '</strong></div>';
    if (!empty($settings['contact_address'])) $html .= '<div><strong>Địa chỉ:</strong> ' . htmlspecialchars($settings['contact_address']) . '</div>';
    $contact_line = '';
    if (!empty($settings['contact_email'])) $contact_line .= '<strong>Email:</strong> ' . htmlspecialchars($settings['contact_email']);
    if (!empty($settings['contact_phone'])) {
        if (!empty($contact_line)) $contact_line .= ' | ';
        $contact_line .= '<strong>Phone:</strong> ' . htmlspecialchars($settings['contact_phone']);
    }
    if (!empty($contact_line)) $html .= '<div>' . $contact_line . '</div>';
    $html .= '</div>';
    return $html;
}

function display_setup_form($error_message) {
    $error_html = $error_message ? "<div class=\"alert alert-danger\">{$error_message}</div>" : '';
    echo <<<HTML
<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>Cài đặt API Frontend</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container"><div class="row justify-content-center"><div class="col-md-6 mt-5"><div class="card"><div class="card-header"><h3>Cài đặt kết nối API</h3></div><div class="card-body"><p>Vui lòng nhập thông tin để kết nối đến hệ thống quản trị.</p>{$error_html}<form method="POST" action=""><div class="mb-3"><label for="api_base_url" class="form-label">URL cơ sở của API</label><input type="url" class="form-control" id="api_base_url" name="api_base_url" placeholder="http://admin.yourdomain.com/backend" required></div><div class="mb-3"><label for="this_domain_name" class="form-label">Tên miền của trang này</label><input type="text" class="form-control" id="this_domain_name" name="this_domain_name" placeholder="my-frontend-site.com" required></div><div class="mb-3"><label for="this_api_key" class="form-label">API Key</label><input type="text" class="form-control" id="this_api_key" name="this_api_key" required></div><button type="submit" name="setup_submit" class="btn btn-primary">Lưu và Kiểm tra</button></form></div></div></div></div></div></body></html>
HTML;
}
?>
