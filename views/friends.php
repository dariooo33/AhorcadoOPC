<section class="panel" id="friendsApp">
    <div class="panel-head">
        <h1>Amigos</h1>
        <span class="chip" id="friendsCountChip">0 amigos | 0 solicitudes</span>
    </div>

    <p>Envia solicitudes por nombre de usuario y acepta solicitudes para agregarlos como amigos.</p>

    <form id="friendAddForm" class="friend-add-form" autocomplete="off">
        <label for="friendUsernameInput">Nombre de usuario</label>
        <div class="friend-add-row">
            <input id="friendUsernameInput" type="text" maxlength="40" required>
            <button id="friendAddBtn" class="btn btn-primary" type="submit">Enviar solicitud</button>
        </div>
    </form>

    <p id="friendsMessage" class="message-line"></p>

    <h2>Solicitudes recibidas</h2>
    <div id="incomingEmpty" class="status-box hidden">No tienes solicitudes pendientes.</div>
    <ul id="incomingRequestsList" class="player-list"></ul>

    <h2>Solicitudes enviadas</h2>
    <div id="outgoingEmpty" class="status-box hidden">No tienes solicitudes enviadas pendientes.</div>
    <ul id="outgoingRequestsList" class="player-list"></ul>

    <h2>Lista de amigos</h2>

    <div id="friendsEmpty" class="status-box hidden">
        Aun no tienes amigos agregados. Envia solicitudes y espera aceptacion.
    </div>

    <ul id="friendsList" class="player-list"></ul>
</section>
