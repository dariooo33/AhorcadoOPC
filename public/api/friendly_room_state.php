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

$code = trim((string) ($_GET['code'] ?? ''));

try {
    $room = Game::getFriendlyRoomState($code, (int) currentUserId());

    if (!$room) {
        jsonResponse([
            'success' => false,
            'message' => 'No tienes acceso a esta sala.',
        ], 404);
    }

    jsonResponse([
        'success' => true,
        'room' => $room,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo cargar la sala.',
    ], 500);
}
