<?php

class Cache {
    /**
     * 缓存类型，现支持 memcached 和 redis
     */
    private $type;

    private $memcached;

    private $redis;

    public function __construct($type, $host, $port, $auth = null) {
        $this->type = $type;
        if ($type == 'redis') {
            $this->redis = new Redis();
            $this->redis->connect($host, $port);
            if ($auth) {
                $this->redis->auth($auth);
            }
        } else if ($this->type == 'memcached') {
            $this->memcached = new memcached();
            $this->memcached->addServer($host, $port);
        }
    }

    public function get($key, $without_prefix = false) {
        if (!$without_prefix) {
            $key = 'sensors:AB:SDK:'.$key;
        }
        $data;
        if ($this->type == 'redis') {
            $data = $this->redis->get($key);
        } else if ($this->type == 'memcached') {
            $data = $this->memcached->get($key);
        }

        try {
            $data = json_decode($data);
        } catch(Exception $e) {}

        return $data;
    }

    public function set($key, $value, $expire) {
        $key = 'sensors:AB:SDK:'.$key;
        if (is_object($value)) {
            $value = json_encode($value);
        }

        if ($this->type == 'redis') {
            $this->redis->set($key, $value);
            $this->redis->expireAt($key, $expire);
        } else if ($this->type == 'memcached') {
            $this->memcached->set($key, $value, $expire);
        }
    }

    public function remove($key) {
        $key = 'sensors:AB:SDK:'.$key;
        if ($this->type == 'redis') {
            $this->redis->del($key);
        } else if ($this->type == 'memcached') {
            $this->memcached->remove($key);
        }
    }

    public function nodes_size($key_search) {
        $key_search = 'sensors:AB:SDK:'.$key_search;
        if ($this->type == 'redis') {
            return count($this->redis->keys($key_search));
        } else if ($this->type == 'memcached') {
            $keys = $this->memcached->getAllKeys();
            $matched_keys = [];
            foreach ($keys as $index => $key) {
                preg_match('/^'.$key_search.'/', $key, $matches);
                if (count($matches)) {
                    array_push($matched_keys, $key);
                }
            }
            return count($matched_keys);
        }
    }
}