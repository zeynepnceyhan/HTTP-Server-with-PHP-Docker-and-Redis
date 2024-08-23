<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';

use Predis\Client as PredisClient;

class Leaderboard {
    private PredisClient $redis;
    private RepetitiveFunctions $repetitiveFunctions;

    public function __construct() {
        $this->repetitiveFunctions = new RepetitiveFunctions();
        $this->redis = $this->repetitiveFunctions->getRedis();
    }

    private function error(string $message) {
        return $this->repetitiveFunctions->responseError($message);
    }

    private function success(array $data) {
        return $this->repetitiveFunctions->responseSuccess($data);
    }

    public function getLeaderboard(int $page, int $count) {
        if ($page < 1) $page = 1;
        if ($count < 1) $count = 10;
    
        $start = ($page - 1) * $count;
        $end = $start + $count - 1;
    
        try {
            // Fetch users and their scores from Redis, sorted by score in descending order
            $users = $this->redis->zrevrange("leaderboard", $start, $end, ['withscores' => true]);

            // Handle empty data case
            if (empty($users)) {
                return $this->repetitiveFunctions->responseSuccess([]);
            }
    
            $leaderboard = [];
            $rank = $start + 1;
            foreach ($users as $userID => $score) {
                // Check if userID is in the expected format
                if (!is_string($userID)) {
                    $this->logError("Unexpected userID format: " . print_r($userID, true));
                    continue;
                }

                $user = $this->redis->get("user:$userID");

                // Check if user data is null
                if ($user === null) {
                    $this->logError("User data not found for userID: $userID");
                    continue;
                }

                // Ensure the data is a valid string
                if (is_string($user) && $user !== '') {
                    $userData = json_decode($user, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $leaderboard[] = [
                            'id' => $userID,
                            'username' => $userData['username'] ?? "Unknown",
                            'rank' => $rank,
                            'score' => $score
                        ];
                        $rank++;
                    } else {
                        $this->logError('Invalid JSON data for user: ' . $userID);
                    }
                } else {
                    $this->logError('User data is invalid for user: ' . $userID);
                }
            }
            return $this->repetitiveFunctions->responseSuccess($leaderboard);
    
        } catch (Exception $e) {
            http_response_code(500);
            return $this->repetitiveFunctions->responseError($e->getMessage());
        }
    }

    // Log errors to a file
    private function logError(string $message) {
        file_put_contents('error_log.txt', $message . PHP_EOL, FILE_APPEND);
    }
}
