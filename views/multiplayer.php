<section class="panel" id="multiplayerApp" data-user-id="<?= e((string) currentUserId()) ?>">
    <div id="mpWaiting" class="status-box">
        <h1>Buscando rival...</h1>
        <p>Para emparejarse, ambos jugadores deben mantenerse en esta pantalla de busqueda.</p>
    </div>

    <div id="mpFound" class="status-box match-found-box hidden" aria-live="polite">
        <h1>Partida encontrada</h1>
        <p id="mpFoundText">Conectando jugadores...</p>
        <div id="mpFoundCountdown" class="match-found-countdown">3</div>
    </div>

    <div id="mpGame" class="hidden">
        <div class="panel-head">
            <h1>Partida multijugador</h1>
            <div class="panel-head-actions">
                <span class="chip" id="turnIndicator">Cargando turno...</span>
                <button type="button" class="btn" id="mpAbandonBtn">Abandonar partida</button>
            </div>
        </div>

        <div class="players-grid two-cols">
            <article class="player-board">
                <h3 id="player1Name">Jugador 1</h3>
                <div id="hangman1" class="hangman-figure" data-errors="0"></div>
                <p>Errores: <span id="player1Errors">0</span>/6</p>
            </article>

            <article class="player-board">
                <h3 id="player2Name">Jugador 2</h3>
                <div id="hangman2" class="hangman-figure" data-errors="0"></div>
                <p>Errores: <span id="player2Errors">0</span>/6</p>
            </article>
        </div>

        <div class="word-box" id="wordMask">_ _ _ _</div>

        <div class="keyboard" id="mpKeyboard"></div>

        <form id="mpSolveForm" class="solve-form" autocomplete="off">
            <label for="mpSolveInput">Resolver palabra</label>
            <input id="mpSolveInput" type="text" maxlength="80" required>
            <button type="submit" class="btn">Resolver</button>
        </form>

        <p id="mpMessage" class="message-line"></p>

        <section id="mpSummary" class="match-summary hidden">
            <h2 id="mpSummaryTitle">Resumen de la partida</h2>

            <div class="summary-grid">
                <article class="summary-item">
                    <strong>Resultado</strong>
                    <span id="mpSummaryResult">-</span>
                </article>
                <article class="summary-item">
                    <strong>Ganador</strong>
                    <span id="mpSummaryWinner">-</span>
                </article>
                <article class="summary-item">
                    <strong>Palabra final</strong>
                    <span id="mpSummaryWord">-</span>
                </article>
                <article class="summary-item">
                    <strong>Errores</strong>
                    <span id="mpSummaryErrors">-</span>
                </article>
            </div>

            <p id="mpSummaryTrophies" class="small-text"></p>

            <div class="summary-actions">
                <a class="btn btn-primary" href="index.php?page=home">Volver al menu</a>
            </div>
        </section>
    </div>
</section>
