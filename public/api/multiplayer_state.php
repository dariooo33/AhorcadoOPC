<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once APP_ROOT . '/models/Game.php';

if (!isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'message' => 'Debes iniciar sesion.',
    ], 401);
}

$gameId = (int) ($_GET['game_id'] ?? 0);

if ($gameId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Partida invalida.',
    ], 422);
}

try {
    $state = Game::getMultiplayerState($gameId, (int) currentUserId());

    if (!$state) {
        jsonResponse([
            'success' => false,
            'message' => 'No tienes acceso a esta partida.',
        ], 404);
    }

    jsonResponse([
        'success' => true,
        'game' => $state,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo cargar el estado de la partida.',
    ], 500);
}
