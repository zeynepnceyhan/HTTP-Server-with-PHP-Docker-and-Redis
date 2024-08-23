<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';

use Predis\Client as PredisClient;

class UserClass {
    private int $id;
    private string $username;
    private string $name;
    private string $password;
    private string $surname;
    private PredisClient $redisClient;
    private RepetitiveFunctions $repetitiveFunctions;

    public function __construct(PredisClient $redisClient, RepetitiveFunctions $repetitiveFunctions, string $username = '', string $name = '', string $password = '', string $surname = '') {
        $this->redisClient = $redisClient;
        $this->repetitiveFunctions = $repetitiveFunctions;
        $this->setUsername($username);
        $this->setName($name);
        $this->setPassword($password);
        $this->setSurname($surname);
    }

    // Shortcuts for response error
    private function error(string $message) {
        return $this->repetitiveFunctions->responseError($message);
    }

    // Shortcuts for response success
    private function success(array $data) {
        return $this->repetitiveFunctions->responseSuccess($data);
    }

    // Shortcuts to access constants
    private function const(string $name) {
        return constant("ConstantsClass::$name");
    }

    // Getter and Setter for id
    function getId(): ?int {
        return $this->id;
    }

    function setId(int $id): void {
        $this->id = $id;
    }

    // Getter and Setter for username
    function getUsername(): string {
        return $this->username;
    }

    function setUsername(string $username): void {
        $this->username = $username;
    }

    // Getter and Setter for name
    function getName(): string {
        return $this->name;
    }

    function setName(string $name): void {
        $this->name = $name;
    }

    // Getter and Setter for password
    function getPassword(): string {
        return $this->password;
    }

    function setPassword(string $password): void {
        $this->password = $password;
    }

    // Getter and Setter for surname
    function getSurname(): string {
        return $this->surname;
    }

    function setSurname(string $surname): void {
        $this->surname = $surname;
    }

    // Hash password
    function hashPass(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // Verify password
    function verifyPass(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function register(string $username, string $password, string $name, string $surname) {
        try {
            if (!$this->getRedis()) {
                throw new Exception("Redis client is not initialized");
            }
    
            $checkUsernameResponse = $this->repetitiveFunctions->checkUsername($username);
            if (!$checkUsernameResponse['status']) {
                return $this->error($checkUsernameResponse['message']);
            }
    
            $this->setUsername($username);
            $this->setName($name);
            $this->setPassword($this->hashPass($password));
            $this->setSurname($surname);
    
            $nextUserId = $this->getRedis()->incr($this->const('NEXT_USER_ID'));
            $this->setId($nextUserId);
    
            $userJson = json_encode([
                'id' => $this->getId(),
                'username' => $this->getUsername(),
                'name' => $this->getName(),
                'password' => $this->getPassword(),
                'surname' => $this->getSurname()
            ]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error encoding user data: ' . json_last_error_msg());
            }
    
            $this->getRedis()->set($this->const('USER_PREFIX') . $this->getId(), $userJson);
            $this->getRedis()->set($this->const('USERNAME_PREFIX') . $username, $this->getId());
    
            return $this->success([
                'userId' => $this->getId(),
                'username' => $this->getUsername(),
                'name' => $this->getName(),
                'surname' => $this->getSurname()
            ]);
        } catch (Exception $e) {
            error_log('Error during registration: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    // Login user
    function login(string $username, string $password) {
        try {
            if (!$this->getRedis()) {
                throw new Exception("Redis client is not initialized");
            }
    
            $userId = $this->getRedis()->get($this->const('USERNAME_PREFIX') . $username);
            if (!$userId) {
                return $this->error("User not found!");
            }
    
            $userJson = $this->getRedis()->get($this->const('USER_PREFIX') . $userId);
            if (!$userJson) {
                return $this->error("User data not found!");
            }
    
            $user = json_decode($userJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding JSON: ' . json_last_error_msg());
            }
    
            if (!$this->verifyPass($password, $user['password'])) {
                return $this->error("Invalid password!");
            }
    
            $user['password'] = '';
    
            $token = bin2hex(random_bytes(16));
            $this->getRedis()->set($this->const('TOKEN_PREFIX') . $token, $userId);
    
            return $this->success(['user' => $user, 'token' => $token]);
        } catch (Exception $e) {
            error_log('Error during login: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    // Get user details
    function userDetails(int $id) {
        try {
            $userJson = $this->getRedis()->get($this->const('USER_PREFIX') . $id);
            if (!$userJson) {
                return $this->error('User not found!');
            }

            $user = json_decode($userJson, true);
            $user['password'] = "";

            return $this->success($user);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Update user
    function updateUser(int $userId, array $data) {
        try {
            $userKey = $this->const('USER_PREFIX') . $userId;
            $userJson = $this->getRedis()->get($userKey);
    
            if (!$userJson) {
                return $this->error('User not found!');
            }
    
            $user = json_decode($userJson, true);
    
            // Update username
            if (isset($data['username']) && $data['username'] !== $user['username']) {
                $existingUsernameId = $this->getRedis()->get($this->const('USERNAME_PREFIX') . $data['username']);
                if ($existingUsernameId && $existingUsernameId != $userId) {
                    return $this->error("Username already exists");
                }
                $this->getRedis()->del($this->const('USERNAME_PREFIX') . $user['username']);
                $this->getRedis()->set($this->const('USERNAME_PREFIX') . $data['username'], $userId);
                $user['username'] = $data['username'];
            }
    
            // Update password
            if (isset($data['password'])) {
                $user['password'] = $this->hashPass($data['password']);
            }
    
            // Update name and surname
            if (isset($data['name'])) {
                $user['name'] = $data['name'];
            }
    
            if (isset($data['surname'])) {
                $user['surname'] = $data['surname'];
            } 
    
            // Do not update ID
            if (isset($data['id']) && $data['id'] != $userId) {
                return $this->error("Invalid ID");
            }

            $user['password'] = '';
            
            // Save updated user data
            $this->getRedis()->set($userKey, json_encode($user));
    
            return $this->success($user);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Get Redis instance
    public function getRedis(): PredisClient {
        return $this->redisClient;
    }
}
