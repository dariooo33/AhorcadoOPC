<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('VIEW_ROOT', APP_ROOT . '/views');

require_once APP_ROOT . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function currentUsername(): string
{
    return isLoggedIn() ? (string) $_SESSION['username'] : '';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consumeFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Debes iniciar sesion para acceder a esta seccion.');
        redirectTo('index.php?page=login');
    }
}

/**
 * Renderiza una vista con el layout base (header/footer).
 */
function render(string $view, array $data = []): void
{
    $title = $data['title'] ?? 'Ahorcado OPC';
    $currentPage = $data['currentPage'] ?? '';
    $pageScript = $data['pageScript'] ?? null;

    extract($data, EXTR_SKIP);

    include VIEW_ROOT . '/partials/header.php';
    include VIEW_ROOT . '/' . $view . '.php';
    include VIEW_ROOT . '/partials/footer.php';
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
