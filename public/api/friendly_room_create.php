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

try {
    $result = Game::createFriendlyRoom((int) currentUserId());
    jsonResponse($result);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo crear la sala.',
    ], 500);
}
