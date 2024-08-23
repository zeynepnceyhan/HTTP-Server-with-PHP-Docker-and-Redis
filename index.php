<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'user.php';
require_once 'match.php';
require_once 'leaderboard.php';
require_once 'simulation.php';

// Initialize classes
$repetitiveFunctions = new RepetitiveFunctions();
$redis = $repetitiveFunctions->getRedis();
$userClass = new UserClass($redis, $repetitiveFunctions);
$matchResult = new MatchResult();
$leaderboard = new Leaderboard();
$simulation = new Simulation($redis, $repetitiveFunctions);

// Capture request method and data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestData = json_decode(file_get_contents('php://input'), true) ?? [];

// Route handlers based on action
function handleRequest(string $action, array $request, UserClass $userClass, MatchResult $matchResult, Leaderboard $leaderboard, RepetitiveFunctions $repetitiveFunctions, Simulation $simulation) {
    switch ($action) {
        case 'register':
            if (!isset($request['username'], $request['password'], $request['name'], $request['surname'])) {
                return $repetitiveFunctions->responseError('Missing parameters');
            }
            return $userClass->register($request['username'], $request['password'], $request['name'], $request['surname']);

        case 'login':
            if (!isset($request['username'], $request['password'])) {
                return $repetitiveFunctions->responseError('Missing parameters');
            }
            return $userClass->login($request['username'], $request['password']);

        case 'update':
            if (!isset($request['id'])) {
                return $repetitiveFunctions->responseError('Missing parameters');
            }
            return $userClass->updateUser((int)$request['id'], $request); // Ensure ID is an integer

        case 'matchresult':
            if (!isset($request['userid1'], $request['userid2'], $request['score1'], $request['score2'])) {
                return $repetitiveFunctions->responseError('Missing parameters');
            }
            return $matchResult->processResult($request['userid1'], $request['userid2'], $request['score1'], $request['score2']);

        case 'simulate':
            if (!isset($request['usercount'])) {
                return $repetitiveFunctions->responseError('Missing usercount parameter');
            }
            $userCount = (int)$request['usercount'];
            return $simulation->run($userCount);

        default:
            return $repetitiveFunctions->responseError('Invalid action');
    }
}

// Handle POST requests
if ($requestMethod === 'POST') {
    $action = $requestData['action'] ?? '';
    if (in_array($action, ['register', 'login', 'update', 'matchresult', 'simulate'])) {
        echo json_encode(handleRequest($action, $requestData, $userClass, $matchResult, $leaderboard, $repetitiveFunctions, $simulation));
    } else {
        echo json_encode($repetitiveFunctions->responseError('Invalid action'));
    }
}
// Handle GET requests
elseif ($requestMethod === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'userdetails' && isset($_GET['id'])) {
        echo json_encode($repetitiveFunctions->responseSuccess($userClass->userDetails((int)$_GET['id'])));
    } elseif ($action === 'leaderboard' && isset($_GET['page'], $_GET['count'])) {
        echo json_encode($leaderboard->getLeaderboard((int)$_GET['page'], (int)$_GET['count']));
    } elseif ($action === 'simulate' && isset($_GET['usercount'])) {
        $userCount = (int)$_GET['usercount'];
        echo json_encode($simulation->run($userCount));
    } else {
        echo json_encode($repetitiveFunctions->responseError('Invalid action or missing parameters'));
    }
} else {
    echo json_encode($repetitiveFunctions->responseError('Unsupported request method'));
}
