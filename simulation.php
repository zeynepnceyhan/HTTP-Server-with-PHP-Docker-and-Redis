<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'user.php';
require_once 'match.php'; 


use Predis\Client as PredisClient;

class Simulation {
    private PredisClient $redis;
    private RepetitiveFunctions $repetitiveFunctions;

    public function __construct(PredisClient $redisClient, RepetitiveFunctions $repetitiveFunctions) {
        $this->redis = $redisClient;
        $this->repetitiveFunctions = $repetitiveFunctions;
    }

    public function run(int $userCount) {
        try {
            if ($userCount < 2) {
                throw new Exception("At least 2 users are required for simulation.");
            }

            // Step 1: Register users
            $users = [];
            for ($i = 1; $i <= $userCount; $i++) {
                $username = "player_$i";
                $name = "Name$i";
                $surname = "Surname$i";
                $password = "password"; // Fixed password

                $userClass = new UserClass($this->redis, $this->repetitiveFunctions);
                $response = $userClass->register($username, $password, $name, $surname);

                if ($response['status']) {
                    $users[$response['data']['userId']] = $username;
                } else {
                    throw new Exception($response['message']);
                }
            }

            // Step 2: Create MatchResult instance
            $matchResult = new MatchResult();
            $matchResult->initRedis($this->redis);

            // Step 3: Conduct matches
            foreach ($users as $userId1 => $username1) {
                foreach ($users as $userId2 => $username2) {
                    if ($userId1 >= $userId2) continue; // Prevent rematches and self-matches

                    // Generate random scores
                    $score1 = rand(0, 10);
                    $score2 = rand(0, 10);

                    // Process match result
                    $result = $matchResult->processResult($userId1, $userId2, $score1, $score2);

                    if (!$result['status']) {
                        throw new Exception($result['message']);
                    }
                }
            }

            // Ensure users with 0 score are also visible in leaderboard
            $this->updateLeaderboard();

            return $this->repetitiveFunctions->responseSuccess([
                'status' => true,
                'message' => 'Simulation completed successfully'
            ]);

        } catch (Exception $e) {
            return $this->repetitiveFunctions->responseError($e->getMessage());
        }
    }

    private function updateLeaderboard() {
        // Retrieve all users from Redis
        $users = $this->redis->keys('user:*');
    
        foreach ($users as $userKey) {
            $userId = str_replace('user:', '', $userKey);
            $userScore = $this->redis->zscore('leaderboard', $userId);
            
            // If score is null, it means the user has never been in any match
            if ($userScore === null) {
                // For Redis 6.x and newer, the zadd command can accept a score and member parameters
                $this->redis->zadd('leaderboard', [
                    'NX', // Only add the member if it does not already exist
                    'CH', // Return the number of elements added
                    0, // Score
                    $userId // Member
                ]);
            }
        }
    }
    
}
