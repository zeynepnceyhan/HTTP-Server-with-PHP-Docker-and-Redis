<?php

require 'vendor/autoload.php';

use Predis\Client;

class RedisClient {
    private $client;

    public function __construct() {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host'   => 'host.docker.internal',
            'port'   => 6379,
        ]);
    }

    public function isConnected(): bool {
        try {
            $this->client->ping();
            return true;
        } catch (Exception $e) {
            error_log('Redis connection error: ' . $e->getMessage());
            return false;
        }
    }

    public function get(string $key) {
        return $this->client->get($key);
    }

    public function set(string $key, string $value) {
        return $this->client->set($key, $value);
    }

    public function incr(string $key) {
        return $this->client->incr($key);
    }

    public function del(string $key) {
        return $this->client->del([$key]);
    }

}

