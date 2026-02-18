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

try {
    $result = Game::findOrCreateMultiplayer((int) currentUserId());

    jsonResponse([
        'success' => true,
        'waiting' => (bool) $result['waiting'],
        'game_id' => (int) $result['game_id'],
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo gestionar el emparejamiento.',
    ], 500);
}
