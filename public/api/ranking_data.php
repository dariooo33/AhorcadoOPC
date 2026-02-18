<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once APP_ROOT . '/models/User.php';

try {
    jsonResponse([
        'success' => true,
        'ranking' => User::getRanking(),
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo cargar el ranking.',
    ], 500);
}
