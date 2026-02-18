<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once APP_ROOT . '/controllers/AuthController.php';
require_once APP_ROOT . '/controllers/PageController.php';
require_once APP_ROOT . '/models/Game.php';

$page = (string) ($_GET['page'] ?? 'home');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (isLoggedIn()) {
    $userId = (int) currentUserId();

    if ($page !== 'multiplayer') {
        $activeGameId = Game::getActiveMultiplayerGameIdForUser($userId);

        if ($activeGameId !== null) {
            setFlash('error', 'Tienes una partida multijugador en curso. Debes terminarla o abandonarla para salir.');
            redirectTo('index.php?page=multiplayer');
        }
    }

    $activeFriendlyCode = Game::getActiveFriendlyRoomCodeForUser($userId);

    if ($activeFriendlyCode !== null) {
        $requestedFriendlyCode = strtoupper(trim((string) ($_GET['code'] ?? '')));

        if ($page !== 'friendly_game' || $requestedFriendlyCode !== $activeFriendlyCode) {
            setFlash('error', 'Tienes una partida cooperativa en curso. Debes terminarla o abandonarla para salir.');
            redirectTo('index.php?page=friendly_game&code=' . urlencode($activeFriendlyCode));
        }
    }
}

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

    case 'friends':
        showFriendsPage();
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
