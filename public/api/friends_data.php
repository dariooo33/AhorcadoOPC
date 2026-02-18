<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once APP_ROOT . '/models/User.php';

if (!isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'message' => 'Debes iniciar sesion.',
    ], 401);
}

try {
    $friends = User::getFriends((int) currentUserId());
    $incomingRequests = User::getIncomingFriendRequests((int) currentUserId());
    $outgoingRequests = User::getOutgoingFriendRequests((int) currentUserId());

    jsonResponse([
        'success' => true,
        'friends' => $friends,
        'incoming_requests' => $incomingRequests,
        'outgoing_requests' => $outgoingRequests,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'success' => false,
        'message' => 'No se pudo cargar la lista de amigos.',
    ], 500);
}
