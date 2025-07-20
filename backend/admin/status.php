<?php
require_once __DIR__ . '/../config/config.php'; // Include main config
require_once __DIR__ . '/templates/header.php';
?>

<div class="container mt-4">
    <h2>System Status</h2>
    <p>This page checks the status of key system components like Redis and MaxMind GeoIP.</p>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Service</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <!-- Redis Status Check -->
            <tr>
                <td><strong>Redis Cache</strong></td>
                <?php
                $redis_status = '<span class="text-danger">Disabled</span>';
                $redis_details = 'Redis extension is not installed or enabled.';
                if (class_exists('Redis')) {
                    try {
                        $redis = new Redis();
                        if (@$redis->connect(REDIS_HOST, REDIS_PORT, 0.5)) {
                            $redis_status = '<span class="text-success">Connected</span>';
                            $redis_details = 'Successfully connected to ' . REDIS_HOST . ':' . REDIS_PORT . '.';
                            // Optional: Check server response
                            if ($redis->ping() == '+PONG') {
                                $redis_details .= ' Server responded with PONG.';
                            } else {
                                $redis_details .= ' Server did not respond to PING as expected.';
                            }
                            $redis->close();
                        } else {
                            $redis_status = '<span class="text-danger">Connection Failed</span>';
                            $redis_details = 'Could not connect to ' . REDIS_HOST . ':' . REDIS_PORT . '. Please check if Redis server is running and accessible.';
                        }
                    } catch (Exception $e) {
                        $redis_status = '<span class="text-danger">Error</span>';
                        $redis_details = 'An exception occurred: ' . $e->getMessage();
                    }
                }
                ?>
                <td><?php echo $redis_status; ?></td>
                <td><?php echo $redis_details; ?></td>
            </tr>

            <!-- MaxMind GeoIP Status Check -->
            <tr>
                <td><strong>MaxMind GeoIP</strong></td>
                <?php
                $geoip_status = '<span class="text-danger">Not Found</span>';
                $geoip_details = 'GeoIP database file not found.';
                $db_path = __DIR__ . '/../GeoLite2-Country.mmdb';

                if (file_exists($db_path)) {
                    // Composer's autoloader is required for the GeoIp2 classes
                    $autoloader_path = __DIR__ . '/../vendor/autoload.php';
                    if (file_exists($autoloader_path)) {
                        require_once $autoloader_path;
                        if (class_exists('\\GeoIp2\\Database\\Reader')) {
                            try {
                                $reader = new \GeoIp2\Database\Reader($db_path);
                                $geoip_status = '<span class="text-success">Loaded</span>';
                                $geoip_details = 'Successfully loaded the GeoIP database from: ' . $db_path;
                            } catch (Exception $e) {
                                $geoip_status = '<span class="text-danger">Error</span>';
                                $geoip_details = 'Failed to load GeoIP database. Error: ' . $e->getMessage();
                            }
                        } else {
                            $geoip_status = '<span class="text-danger">Class Not Found</span>';
                            $geoip_details = 'The GeoIp2\\Database\\Reader class is not available. Please check Composer dependencies.';
                        }
                    } else {
                        $geoip_status = '<span class="text-danger">Autoloader Missing</span>';
                        $geoip_details = 'Composer autoloader not found at: ' . $autoloader_path;
                    }
                } else {
                    $geoip_details = 'Database file not found at: ' . $db_path;
                }
                ?>
                <td><?php echo $geoip_status; ?></td>
                <td><?php echo $geoip_details; ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
