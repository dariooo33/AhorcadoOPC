<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once APP_ROOT . '/controllers/AuthController.php';
require_once APP_ROOT . '/controllers/PageController.php';

$page = (string) ($_GET['page'] ?? 'home');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($page) {
    case 'home':
        showHomePage();
        break;

    case 'login':
        if ($method === 'POST') {
            loginUser();
            break;
        }

        showLoginPage();
        break;

    case 'register':
        if ($method === 'POST') {
            registerUser();
            break;
        }

        showRegisterPage();
        break;

    case 'logout':
        logoutUser();
        break;

    case 'ranking':
        showRankingPage();
        break;

    case 'profile':
        showProfilePage();
        break;

    case 'multiplayer':
        showMultiplayerPage();
        break;

    case 'friendly':
        showFriendlyLobbyPage();
        break;

    case 'friendly_waiting':
        showFriendlyWaitingPage();
        break;

    case 'friendly_game':
        showFriendlyGamePage();
        break;

    default:
        showHomePage();
        break;
}
