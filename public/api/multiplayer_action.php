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
$gameId = (int) ($payload['game_id'] ?? 0);
$action = trim((string) ($payload['action'] ?? ''));
$value = trim((string) ($payload['value'] ?? ''));

if ($gameId <= 0 || $action === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Solicitud invalida.',
    ], 422);
}

try {
    $result = Game::playMultiplayerAction($gameId, (int) currentUserId(), $action, $value);

    if (!$result['success']) {
        jsonResponse($result, 422);
    }

    jsonResponse($result);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo procesar la jugada.',
    ], 500);
}
