<section class="panel" id="friendlyLobbyApp">
    <h1>Modo amistoso</h1>
    <p>Crea una sala privada o unete con un codigo.</p>

    <div class="mode-grid">
        <article class="mode-card">
            <h2>Crear sala</h2>
            <p>Genera un codigo unico para invitar a otro jugador.</p>
            <button id="createRoomBtn" class="btn btn-primary" type="button">Crear sala</button>
        </article>

        <article class="mode-card">
            <h2>Unirse a sala</h2>
            <form id="joinRoomForm" class="stack-form" autocomplete="off">
                <label for="joinCode">Codigo de sala</label>
                <input id="joinCode" type="text" maxlength="12" placeholder="Ej: AB12CD" required>
                <button type="submit" class="btn">Unirme</button>
            </form>
        </article>
    </div>

    <p id="friendlyLobbyMessage" class="message-line"></p>
</section>
