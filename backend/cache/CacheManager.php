<?php
class CacheManager {
    private $redis;
    private $redis_enabled = false;

    public function __construct() {
        // Check if the Redis class exists before trying to use it.
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                // Use a short timeout to prevent long waits if Redis is down.
                if (@$this->redis->connect(REDIS_HOST, REDIS_PORT, 0.5)) {
                    $this->redis_enabled = true;
                }
            } catch (Exception $e) {
                // Handle connection or other Redis errors gracefully.
                $this->redis = null;
                $this->redis_enabled = false;
            }
        }
    }

    public function get($key) {
        if (!$this->redis_enabled) {
            return false;
        }
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : false;
    }

    public function set($key, $value, $ttl = 300) {
        if (!$this->redis_enabled) {
            return false;
        }
        return $this->redis->setex($key, $ttl, json_encode($value));
    }

    public function delete($key) {
        if (!$this->redis_enabled) {
            return false;
        }
        return $this->redis->del($key);
    }
}
?>
