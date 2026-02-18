<?php
declare(strict_types=1);

require_once APP_ROOT . '/models/User.php';

function showLoginPage(): void
{
    if (isLoggedIn()) {
        redirectTo('index.php?page=home');
    }

    render('login', [
        'title' => 'Login | Ahorcado OPC',
        'currentPage' => 'login',
        'pageScript' => 'auth.js',
    ]);
}

function loginUser(): void
{
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        setFlash('error', 'Debes completar usuario/correo y contrasena.');
        redirectTo('index.php?page=login');
    }

    $user = User::findByLogin($login);

    if (!$user || !password_verify($password, (string) $user['password'])) {
        setFlash('error', 'Credenciales invalidas.');
        redirectTo('index.php?page=login');
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['email'] = (string) $user['email'];

    setFlash('success', 'Bienvenido de nuevo, ' . (string) $user['username'] . '.');
    redirectTo('index.php?page=home');
}

function showRegisterPage(): void
{
    if (isLoggedIn()) {
        redirectTo('index.php?page=home');
    }

    render('register', [
        'title' => 'Registro | Ahorcado OPC',
        'currentPage' => 'register',
        'pageScript' => 'auth.js',
    ]);
}

function registerUser(): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Debes ingresar un correo valido.');
        redirectTo('index.php?page=register');
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        setFlash('error', 'El nombre de usuario debe tener 3-20 caracteres alfanumericos o guion bajo.');
        redirectTo('index.php?page=register');
    }

    if (strlen($password) < 8) {
        setFlash('error', 'La contrasena debe tener al menos 8 caracteres.');
        redirectTo('index.php?page=register');
    }

    if ($password !== $confirmPassword) {
        setFlash('error', 'Las contrasenas no coinciden.');
        redirectTo('index.php?page=register');
    }

    if (User::existsByEmail($email)) {
        setFlash('error', 'Ese correo ya esta registrado.');
        redirectTo('index.php?page=register');
    }

    if (User::existsByUsername($username)) {
        setFlash('error', 'Ese nombre de usuario ya esta en uso.');
        redirectTo('index.php?page=register');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    User::create($email, $username, $passwordHash);

    setFlash('success', 'Cuenta creada correctamente. Ahora inicia sesion para jugar.');
    redirectTo('index.php?page=login');
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }

    session_destroy();
    redirectTo('index.php?page=login');
}
