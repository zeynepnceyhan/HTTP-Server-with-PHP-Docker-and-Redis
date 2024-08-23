<?php

require 'vendor/autoload.php';
require_once 'constants.php';

use Predis\Client;

class RepetitiveFunctions {
    private $redis;

    // Constructor
    public function __construct() {
        $this->redis = $this->initRedis();
    }

    // Response success
    public function responseSuccess($data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
        exit(); // Ensure no further processing happens
    }
    
    // Response error
    public function responseError($message) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => false,
            'message' => $message
        ]);
        exit(); // Ensure no further processing happens
    }

    // Initialize Redis
    private function initRedis() {
        try {
            return new Client([
                'scheme' => 'tcp',
                'host'   => 'host.docker.internal',
                'port'   => 6379,
            ]);
        } catch (Exception $e) {
            $this->responseError("Failed to connect to Redis: " . $e->getMessage());
        }
    }

    // Check if the username exists
    public function checkUsername(string $username): array {
        if (!$this->redis) {
            return ['status' => false, 'message' => "Redis client is not initialized"];
        }
        if ($this->redis->exists(ConstantsClass::USERNAME_PREFIX . $username)) {
            return ['status' => false, 'message' => "Username already exists"];
        }
        
        return ['status' => true];
    }

    // Get Redis client
    public function getRedis() {
        return $this->redis;
    }
    
    // Get user by ID
    public function getUserByID(int $id) {
        try {
            if (!$this->redis) {
                return $this->responseError("Redis client is not initialized");
            }
            $userJson = $this->redis->get(ConstantsClass::USER_PREFIX . $id);
            if ($userJson === false) {
                return $this->responseError("User not found");
            }
    
            $user = json_decode($userJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding JSON: ' . json_last_error_msg());
            }
    
            return $this->responseSuccess($user);
    
        } catch (Exception $e) {
            error_log('Error fetching user by ID: ' . $e->getMessage());
            return $this->responseError($e->getMessage());
        }
    }
    
    // Save user to Redis
    public function saveUser(array $user) {
        try {
            if (!$this->redis) {
                throw new Exception("Redis client is not initialized");
            }
            $userJson = json_encode($user);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error encoding JSON: ' . json_last_error_msg());
            }
            $this->redis->set(ConstantsClass::USER_PREFIX . $user['id'], $userJson);
        } catch (Exception $e) {
            error_log('Error saving user: ' . $e->getMessage());
            return $this->responseError($e->getMessage());
        }
    }
}
