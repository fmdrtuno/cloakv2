<?php
session_start();
require_once '../config/config.php';
require_once 'templates/header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Lấy domain_id từ URL và xác thực nó
$domain_id = filter_input(INPUT_GET, 'domain_id', FILTER_VALIDATE_INT);
check_domain_permission($mysqli, $domain_id);
if (!$domain_id) {
    echo "<div class='alert alert-danger'>Không có domain nào được chọn. Vui lòng quay lại <a href='domains.php'>trang quản lý domain</a>.</div>";
    require_once 'templates/footer.php';
    exit;
}

// Lấy thông tin domain để hiển thị tên
$stmt_domain = $mysqli->prepare("SELECT domain_name FROM domains WHERE id = ?");
$stmt_domain->bind_param("i", $domain_id);
$stmt_domain->execute();
$result_domain = $stmt_domain->get_result();
$domain_info = $result_domain->fetch_assoc();
$stmt_domain->close();
if (!$domain_info) {
    echo "<div class='alert alert-danger'>Domain không hợp lệ.</div>";
    require_once 'templates/footer.php';
    exit;
}
$current_domain_name = $domain_info['domain_name'];

// Danh sách mã quốc gia để hiển thị trong dropdown
$countries = [
    'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, The Democratic Republic of the', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote D\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic of', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic of', 'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libyan Arab Jamahiriya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States of', 'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestinian Territory', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'CS' => 'Serbia and Montenegro', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
];

// Xử lý thêm quy tắc
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_rule'])) {
    $country_code = $_POST['country_code'];
    $rule_type = $_POST['rule_type'];
    if (!empty($country_code) && !empty($rule_type)) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO country_rules (domain_id, country_code, rule_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $domain_id, $country_code, $rule_type);
        $stmt->execute();
        $stmt->close();

        $cache = new CacheManager();
        $cache->delete("domain_settings_{$domain_id}");

        header("Location: countries.php?domain_id=" . $domain_id);
        exit;
    }
}

// Xử lý xóa quy tắc
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM country_rules WHERE id = ? AND domain_id = ?");
    $stmt->bind_param("ii", $id, $domain_id);
    $stmt->execute();
    $stmt->close();

    $cache = new CacheManager();
    $cache->delete("domain_settings_{$domain_id}");

    header("location: countries.php?domain_id=" . $domain_id);
    exit;
}

// Lấy các quy tắc hiện tại
$rules = [];
$stmt = $mysqli->prepare("SELECT * FROM country_rules WHERE domain_id = ? ORDER BY rule_type, country_code");
$stmt->bind_param("i", $domain_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rules[] = $row;
}
$stmt->close();

$page_title = 'Quản lý quốc gia';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $page_title; ?> cho <?php echo htmlspecialchars($current_domain_name); ?></h1>
    <a href="domains.php" class="btn btn-secondary">Quay lại danh sách Domain</a>
</div>

<div class="alert alert-info">
    <strong>Lưu ý:</strong> Chức năng này hoạt động bằng cách sử dụng API để tra cứu IP. Logic hiện tại: nếu có bất kỳ quốc gia nào trong danh sách trắng (whitelist), chỉ những quốc gia đó được phép. Nếu không, bất kỳ quốc gia nào trong danh sách đen (blacklist) sẽ bị chặn.
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Thêm quy tắc quốc gia
            </div>
            <div class="card-body">
                <form action="countries.php?domain_id=<?php echo $domain_id; ?>" method="post">
                    <div class="mb-3">
                        <label for="country_code" class="form-label">Quốc gia</label>
                        <select id="country_code" name="country_code" class="form-select">
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="rule_type" class="form-label">Hành động</label>
                        <select id="rule_type" name="rule_type" class="form-select">
                            <option value="blacklist">Chặn (Blacklist)</option>
                            <option value="whitelist">Cho phép (Whitelist)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_rule" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Thêm quy tắc
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Danh sách quy tắc hiện tại
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th scope="col">Quốc gia</th>
                                <th scope="col">Hành động</th>
                                <th scope="col">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($countries[$rule['country_code']] ?? $rule['country_code']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $rule['rule_type'] == 'blacklist' ? 'danger' : 'success'; ?>">
                                        <?php echo $rule['rule_type'] == 'blacklist' ? 'Bị chặn' : 'Được phép'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="countries.php?domain_id=<?php echo $domain_id; ?>&delete=<?php echo $rule['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa quy tắc này?');">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
