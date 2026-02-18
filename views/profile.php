<section class="panel">
    <h1>Tu perfil</h1>

    <div class="stats-grid">
        <article class="stat-card">
            <h3>Usuario</h3>
            <p><?= e((string) $profile['username']) ?></p>
        </article>

        <article class="stat-card">
            <h3>Correo</h3>
            <p><?= e((string) $profile['email']) ?></p>
        </article>

        <article class="stat-card">
            <h3>Trofeos</h3>
            <p><?= (int) $profile['trofeos'] ?></p>
        </article>

        <article class="stat-card">
            <h3>Partidas jugadas</h3>
            <p><?= (int) $profile['partidas_jugadas'] ?></p>
        </article>

        <article class="stat-card">
            <h3>Ganadas</h3>
            <p><?= (int) $profile['partidas_ganadas'] ?></p>
        </article>

        <article class="stat-card">
            <h3>Perdidas</h3>
            <p><?= (int) $profile['partidas_perdidas'] ?></p>
        </article>

        <article class="stat-card">
            <h3>Winrate</h3>
            <p><?= number_format((float) $winrate, 2) ?>%</p>
        </article>

        <article class="stat-card">
            <h3>Top ranking</h3>
            <?php if ($topPosition !== null): ?>
                <p>Top 5, posicion #<?= (int) $topPosition ?></p>
            <?php else: ?>
                <p>No estas en Top 5 aun.</p>
            <?php endif; ?>
        </article>
    </div>
</section>
