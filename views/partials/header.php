<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="bg-orb orb-one"></div>
    <div class="bg-orb orb-two"></div>

    <header class="site-header">
        <nav class="container nav-wrap">
            <a class="brand" href="index.php?page=home">AhorcadoOPC</a>

            <div class="nav-links">
                <a class="<?= $currentPage === 'home' ? 'is-active' : '' ?>" href="index.php?page=home">Inicio</a>
                <a class="<?= $currentPage === 'ranking' ? 'is-active' : '' ?>" href="index.php?page=ranking">Ranking</a>

                <?php if (isLoggedIn()): ?>
                    <a class="<?= $currentPage === 'profile' ? 'is-active' : '' ?>" href="index.php?page=profile">Perfil</a>
                    <a href="index.php?page=logout">Logout</a>
                <?php else: ?>
                    <a class="<?= $currentPage === 'login' ? 'is-active' : '' ?>" href="index.php?page=login">Login</a>
                    <a class="<?= $currentPage === 'register' ? 'is-active' : '' ?>" href="index.php?page=register">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="container page-main">
        <?php $flash = consumeFlash(); ?>
        <?php if ($flash): ?>
            <div class="flash flash-<?= e((string) $flash['type']) ?>">
                <?= e((string) $flash['message']) ?>
            </div>
        <?php endif; ?>
