<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';

use Predis\Client as PredisClient;

class MatchResult {
    private int $userid1;
    private int $userid2;
    private int $score1;
    private int $score2;
    private PredisClient $redis;
    private RepetitiveFunctions $repetitiveFunctions;

    function __construct() {
        $this->repetitiveFunctions = new RepetitiveFunctions();
        $this->redis = $this->repetitiveFunctions->getRedis(); // Get Redis instance
    }

    // Shortcuts for response error
    private function error(string $message) {
        return $this->repetitiveFunctions->responseError($message);
    }

    // Shortcuts for response success
    private function success(array $data = null) {
        return $this->repetitiveFunctions->responseSuccess($data);
    }

    // Initialize Redis
    function initRedis(PredisClient $redisClient) {
        $this->redis = $redisClient;
    }

    // Process match result
    public function processResult(int $userid1 = 0, int $userid2 = 0, int $score1 = 0, int $score2 = 0) {
        try {
            $this->userid1 = $userid1;
            $this->userid2 = $userid2;
            $this->score1 = $score1;
            $this->score2 = $score2;

            // Update scores based on match result
            $this->updateScores($score1, $score2);

            // Ensure users are added to the leaderboard even if they have not won any matches
            $this->ensureUserInLeaderboard($userid1);
            $this->ensureUserInLeaderboard($userid2);

            // Return success response with match result and updated ranks
            return $this->success([
                'userid1' => $userid1,
                'userid2' => $userid2,
                'score1' => $score1,
                'score2' => $score2,
                'rank1' => $this->getRank($userid1),
                'rank2' => $this->getRank($userid2)
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Update user scores based on match result
    private function updateScores(int $score1 = 0, int $score2 = 0) {
        if (!$this->redis) {
            throw new Exception("Redis client is not initialized");
        }

        $leaderboardKey = "leaderboard";

        if ($this->score1 > $this->score2) {
            $this->redis->zincrby($leaderboardKey, 3, $this->userid1);
        } elseif ($this->score1 < $this->score2) {
            $this->redis->zincrby($leaderboardKey, 3, $this->userid2);
        } else {
            $this->redis->zincrby($leaderboardKey, 1, $this->userid1);
            $this->redis->zincrby($leaderboardKey, 1, $this->userid2);
        }
    }

    // Ensure the user is in the leaderboard with at least a score of 0
    private function ensureUserInLeaderboard(int $userid) {
        $leaderboardKey = "leaderboard";

        // Check if the user already has a score
        $score = $this->redis->zscore($leaderboardKey, $userid);

        // If the score is null, it means the user is not in the leaderboard, so we add them with a score of 0
        if ($score === null) {
            $this->redis->zadd($leaderboardKey, ['score' => 0, 'member' => $userid]);
        }
    }

    // Get user rank based on points
    private function getRank(int $userid): int {
        if (!$this->redis) {
            throw new Exception("Redis client is not initialized");
        }

        $leaderboardKey = "leaderboard";

        // Retrieve the score
        $score = $this->redis->zscore($leaderboardKey, $userid);
        return $score !== null ? (int) $score : 0;
    }
}
