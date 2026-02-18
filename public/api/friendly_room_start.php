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
$code = trim((string) ($payload['code'] ?? ''));

try {
    $result = Game::startFriendlyRoom($code, (int) currentUserId());

    if (!$result['success']) {
        jsonResponse($result, 422);
    }

    jsonResponse($result);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo iniciar la partida.',
    ], 500);
}
