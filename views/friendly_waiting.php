<section class="panel" id="friendlyWaitingApp" data-room-code="<?= e((string) $roomCode) ?>">
    <div class="panel-head">
        <h1>Sala de espera</h1>
        <span class="chip">Codigo: <strong id="roomCodeText"><?= e((string) $roomCode) ?></strong></span>
    </div>

    <p>Comparte este codigo para que otro jugador se una.</p>

    <h3>Jugadores conectados</h3>
    <ul id="roomPlayersList" class="player-list"></ul>

    <button id="startFriendlyBtn" class="btn btn-primary hidden" type="button">Iniciar partida</button>
    <p id="friendlyWaitingMessage" class="message-line"></p>
</section>
