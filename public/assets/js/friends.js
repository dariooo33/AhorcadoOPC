(() => {
    const app = document.getElementById('friendsApp');

    if (!app) {
        return;
    }

    const addForm = document.getElementById('friendAddForm');
    const usernameInput = document.getElementById('friendUsernameInput');
    const addButton = document.getElementById('friendAddBtn');
    const messageLine = document.getElementById('friendsMessage');
    const friendsEmptyBox = document.getElementById('friendsEmpty');
    const friendsList = document.getElementById('friendsList');
    const incomingEmptyBox = document.getElementById('incomingEmpty');
    const incomingList = document.getElementById('incomingRequestsList');
    const outgoingEmptyBox = document.getElementById('outgoingEmpty');
    const outgoingList = document.getElementById('outgoingRequestsList');
    const countChip = document.getElementById('friendsCountChip');

    const state = {
        actionLocked: false,
    };

    function setMessage(message, isError = false) {
        messageLine.textContent = message || '';
        messageLine.style.color = isError ? '#8a2b28' : '#4d5b77';
    }

    function friendsLabel(total) {
        if (total === 1) {
            return '1 amigo';
        }

        return `${total} amigos`;
    }

    function requestsLabel(total) {
        if (total === 1) {
            return '1 solicitud';
        }

        return `${total} solicitudes`;
    }

    function updateCountChip(totalFriends, totalIncoming) {
        if (!countChip) {
            return;
        }

        countChip.textContent = `${friendsLabel(totalFriends)} | ${requestsLabel(totalIncoming)}`;
    }

    function modeLabel(mode) {
        if (mode === 'multijugador') {
            return 'Jugando multijugador';
        }

        if (mode === 'amistoso') {
            return 'Jugando cooperativo';
        }

        return 'Jugando ahora';
    }

    function renderFriends(friends) {
        const list = Array.isArray(friends) ? friends : [];

        if (list.length === 0) {
            friendsList.innerHTML = '';
            if (friendsEmptyBox) {
                friendsEmptyBox.classList.remove('hidden');
            }
            return;
        }

        if (friendsEmptyBox) {
            friendsEmptyBox.classList.add('hidden');
        }

        friendsList.innerHTML = list.map((friend) => {
            const friendId = Number(friend.id) || 0;
            const username = window.HangmanUI.escapeHTML(friend.username || '');
            const trophies = Number(friend.trofeos) || 0;
            const playingNow = Boolean(friend.playing_now);
            const statusClass = playingNow ? 'playing' : 'idle';
            const statusText = playingNow
                ? modeLabel(friend.playing_type || '')
                : 'Sin partida activa';

            return `
                <li class="friend-item">
                    <div class="friend-main">
                        <span class="friend-name">${username}</span>
                        <span class="friend-meta">Trofeos: ${trophies}</span>
                    </div>
                    <div class="friend-actions">
                        <span class="friend-status ${statusClass}">${window.HangmanUI.escapeHTML(statusText)}</span>
                        <button class="btn" type="button" data-friend-remove-id="${friendId}">Eliminar</button>
                    </div>
                </li>
            `;
        }).join('');
    }

    function renderIncomingRequests(requests) {
        const list = Array.isArray(requests) ? requests : [];

        if (list.length === 0) {
            incomingList.innerHTML = '';
            if (incomingEmptyBox) {
                incomingEmptyBox.classList.remove('hidden');
            }
            return;
        }

        if (incomingEmptyBox) {
            incomingEmptyBox.classList.add('hidden');
        }

        incomingList.innerHTML = list.map((request) => {
            const username = window.HangmanUI.escapeHTML(request.username || '');
            const trophies = Number(request.trofeos) || 0;
            const requestId = Number(request.id) || 0;

            return `
                <li class="request-item">
                    <div class="friend-main">
                        <span class="friend-name">${username}</span>
                        <span class="friend-meta">Trofeos: ${trophies}</span>
                    </div>
                    <div class="request-actions">
                        <button class="btn btn-primary" type="button" data-request-id="${requestId}" data-request-action="accept">Aceptar</button>
                        <button class="btn" type="button" data-request-id="${requestId}" data-request-action="reject">Rechazar</button>
                    </div>
                </li>
            `;
        }).join('');
    }

    function renderOutgoingRequests(requests) {
        const list = Array.isArray(requests) ? requests : [];

        if (list.length === 0) {
            outgoingList.innerHTML = '';
            if (outgoingEmptyBox) {
                outgoingEmptyBox.classList.remove('hidden');
            }
            return;
        }

        if (outgoingEmptyBox) {
            outgoingEmptyBox.classList.add('hidden');
        }

        outgoingList.innerHTML = list.map((request) => {
            const username = window.HangmanUI.escapeHTML(request.username || '');
            const trophies = Number(request.trofeos) || 0;

            return `
                <li class="request-item">
                    <div class="friend-main">
                        <span class="friend-name">${username}</span>
                        <span class="friend-meta">Trofeos: ${trophies}</span>
                    </div>
                    <span class="friend-status pending">Pendiente</span>
                </li>
            `;
        }).join('');
    }

    function applyFriendsPayload(payload) {
        const friends = payload.friends || [];
        const incoming = payload.incoming_requests || [];
        const outgoing = payload.outgoing_requests || [];

        renderIncomingRequests(incoming);
        renderOutgoingRequests(outgoing);
        renderFriends(friends);
        updateCountChip(friends.length, incoming.length);
    }

    async function loadFriends(showErrors = true) {
        try {
            const data = await window.HangmanUI.requestJSON('api/friends_data.php');

            if (!data.success) {
                if (showErrors) {
                    setMessage(data.message || 'No se pudo cargar la seccion de amigos.', true);
                }
                return;
            }

            applyFriendsPayload(data);
        } catch (error) {
            if (showErrors) {
                setMessage(error.message, true);
            }
        }
    }

    async function resolveRequest(requestId, action) {
        if (state.actionLocked) {
            return;
        }

        if (!requestId || (action !== 'accept' && action !== 'reject')) {
            return;
        }

        try {
            state.actionLocked = true;

            const data = await window.HangmanUI.requestJSON('api/friends_request_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    action,
                }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo responder la solicitud.', true);
                return;
            }

            setMessage(data.message || 'Solicitud procesada correctamente.');
            await loadFriends(false);
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;
        }
    }

    async function removeFriend(friendUserId) {
        if (state.actionLocked || !friendUserId) {
            return;
        }

        const confirmed = window.confirm('Se eliminara este amigo de tu lista.');

        if (!confirmed) {
            return;
        }

        try {
            state.actionLocked = true;

            const data = await window.HangmanUI.requestJSON('api/friends_remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    friend_user_id: friendUserId,
                }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo eliminar al amigo.', true);
                return;
            }

            setMessage(data.message || 'Amigo eliminado correctamente.');
            await loadFriends(false);
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;
        }
    }

    addForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (state.actionLocked) {
            return;
        }

        const username = (usernameInput.value || '').trim();

        if (!username) {
            setMessage('Ingresa un nombre de usuario para enviar la solicitud.', true);
            return;
        }

        try {
            state.actionLocked = true;
            addButton.disabled = true;

            const data = await window.HangmanUI.requestJSON('api/friends_add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo enviar la solicitud.', true);
                return;
            }

            setMessage(data.message || 'Solicitud enviada correctamente.');
            usernameInput.value = '';
            await loadFriends(false);
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;
            addButton.disabled = false;
        }
    });

    incomingList.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target.closest('button[data-request-action]') : null;

        if (!target) {
            return;
        }

        const requestId = Number(target.getAttribute('data-request-id') || 0);
        const action = (target.getAttribute('data-request-action') || '').trim();

        await resolveRequest(requestId, action);
    });

    friendsList.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target.closest('button[data-friend-remove-id]') : null;

        if (!target) {
            return;
        }

        const friendUserId = Number(target.getAttribute('data-friend-remove-id') || 0);
        await removeFriend(friendUserId);
    });

    loadFriends();
    window.setInterval(() => {
        loadFriends(false);
    }, 5000);
})();
