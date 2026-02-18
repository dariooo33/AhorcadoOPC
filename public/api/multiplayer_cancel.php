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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Metodo no permitido.',
    ], 405);
}

$payload = requestJson();
$gameId = isset($payload['game_id']) ? (int) $payload['game_id'] : null;

try {
    $cancelled = Game::cancelWaitingMultiplayer((int) currentUserId(), $gameId);

    jsonResponse([
        'success' => true,
        'cancelled' => $cancelled,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo cancelar la busqueda.',
    ], 500);
}
