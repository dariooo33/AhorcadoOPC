<section class="panel" id="friendlyGameApp" data-room-code="<?= e((string) $roomCode) ?>">
    <div class="panel-head">
        <h1>Partida amistosa</h1>
        <span class="chip">Sala: <?= e((string) $roomCode) ?></span>
    </div>

    <p>En este modo cooperativo comparten aciertos y errores. No afecta trofeos ni winrate.</p>

    <h3>Jugadores</h3>
    <ul id="friendlyPlayersList" class="player-list"></ul>

    <article class="player-board">
        <h3>Ahorcado compartido</h3>
        <div id="friendlyHangman" class="hangman-figure" data-errors="0"></div>
        <p>Errores compartidos: <span id="friendlyErrors">0</span>/6</p>
    </article>

    <div class="word-box" id="friendlyWordMask">_ _ _ _</div>
    <div class="keyboard" id="friendlyKeyboard"></div>

    <form id="friendlySolveForm" class="solve-form" autocomplete="off">
        <label for="friendlySolveInput">Resolver palabra</label>
        <input id="friendlySolveInput" type="text" maxlength="80" required>
        <button type="submit" class="btn">Resolver</button>
    </form>

    <p id="friendlyGameMessage" class="message-line"></p>
</section>
