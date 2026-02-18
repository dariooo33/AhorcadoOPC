<section class="hero-panel">
    <div>
        <h1>Ahorcado competitivo y amistoso</h1>
        <p>Juega en tiempo real, sube trofeos en ranking y reta a tus amigos con salas privadas.</p>
    </div>
    <?php if (isLoggedIn()): ?>
        <span class="chip">Sesion activa: <?= e(currentUsername()) ?></span>
    <?php else: ?>
        <a class="btn btn-primary" href="index.php?page=login">Inicia sesion para jugar</a>
    <?php endif; ?>
</section>

<section class="mode-grid">
    <article class="mode-card">
        <h2>Multijugador</h2>
        <p>Emparejamiento automatico, turnos por jugador, trofeos y estadisticas competitivas.</p>
        <?php if (isLoggedIn()): ?>
            <a class="btn btn-primary" href="index.php?page=multiplayer">Entrar al modo</a>
        <?php else: ?>
            <a class="btn" href="index.php?page=login">Requiere login</a>
        <?php endif; ?>
    </article>

    <article class="mode-card">
        <h2>Amistoso</h2>
        <p>Crea una sala con codigo, invita a otro jugador y resuelvan juntos la palabra.</p>
        <?php if (isLoggedIn()): ?>
            <a class="btn btn-primary" href="index.php?page=friendly">Crear o unirse</a>
        <?php else: ?>
            <a class="btn" href="index.php?page=login">Requiere login</a>
        <?php endif; ?>
    </article>

</section>
