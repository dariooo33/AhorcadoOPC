<?php
declare(strict_types=1);

require_once APP_ROOT . '/models/User.php';

function showHomePage(): void
{
    render('home', [
        'title' => 'Inicio | Ahorcado OPC',
        'currentPage' => 'home',
    ]);
}

function showRankingPage(): void
{
    $ranking = User::getRanking();

    render('ranking', [
        'title' => 'Ranking | Ahorcado OPC',
        'currentPage' => 'ranking',
        'ranking' => $ranking,
        'pageScript' => 'ranking.js',
    ]);
}

function showProfilePage(): void
{
    requireLogin();

    $userId = currentUserId();
    $profile = User::findById((int) $userId);

    if (!$profile) {
        setFlash('error', 'No se pudo cargar tu perfil.');
        redirectTo('index.php?page=home');
    }

    $played = (int) $profile['partidas_jugadas'];
    $won = (int) $profile['partidas_ganadas'];
    $winrate = $played > 0 ? round(($won / $played) * 100, 2) : 0;

    render('profile', [
        'title' => 'Perfil | Ahorcado OPC',
        'currentPage' => 'profile',
        'profile' => $profile,
        'winrate' => $winrate,
        'topPosition' => User::getTopPosition((int) $userId),
    ]);
}

function showMultiplayerPage(): void
{
    requireLogin();

    render('multiplayer', [
        'title' => 'Multijugador | Ahorcado OPC',
        'currentPage' => 'multiplayer',
        'pageScript' => 'multiplayer.js',
    ]);
}

function showFriendlyLobbyPage(): void
{
    requireLogin();

    render('friendly_lobby', [
        'title' => 'Amistoso | Ahorcado OPC',
        'currentPage' => 'friendly',
        'pageScript' => 'friendly_lobby.js',
    ]);
}

function showFriendlyWaitingPage(): void
{
    requireLogin();

    $code = strtoupper(trim((string) ($_GET['code'] ?? '')));

    if ($code === '') {
        setFlash('error', 'Debes indicar un codigo de sala valido.');
        redirectTo('index.php?page=friendly');
    }

    render('friendly_waiting', [
        'title' => 'Sala de espera | Ahorcado OPC',
        'currentPage' => 'friendly',
        'roomCode' => $code,
        'pageScript' => 'friendly_wait.js',
    ]);
}

function showFriendlyGamePage(): void
{
    requireLogin();

    $code = strtoupper(trim((string) ($_GET['code'] ?? '')));

    if ($code === '') {
        setFlash('error', 'Debes indicar un codigo de sala valido.');
        redirectTo('index.php?page=friendly');
    }

    render('friendly_game', [
        'title' => 'Partida amistosa | Ahorcado OPC',
        'currentPage' => 'friendly',
        'roomCode' => $code,
        'pageScript' => 'friendly_game.js',
    ]);
}
