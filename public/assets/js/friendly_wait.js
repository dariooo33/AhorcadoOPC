(() => {
    const app = document.getElementById('friendlyWaitingApp');

    if (!app) {
        return;
    }

    const code = app.dataset.roomCode;
    const playersList = document.getElementById('roomPlayersList');
    const startButton = document.getElementById('startFriendlyBtn');
    const messageLine = document.getElementById('friendlyWaitingMessage');

    function setMessage(message, isError = false) {
        messageLine.textContent = message || '';
        messageLine.style.color = isError ? '#8a2b28' : '#4d5b77';
    }

    function renderPlayers(players) {
        playersList.innerHTML = players
            .map((player) => `<li>${window.HangmanUI.escapeHTML(player.username || '')}</li>`)
            .join('');
    }

    async function refreshRoomState() {
        try {
            const data = await window.HangmanUI.requestJSON(`api/friendly_room_state.php?code=${encodeURIComponent(code)}`);

            if (!data.success) {
                setMessage(data.message || 'No se pudo obtener la sala.', true);
                return;
            }

            renderPlayers(data.room.players);

            if (data.room.is_creator) {
                startButton.classList.remove('hidden');
                startButton.disabled = data.room.players.length < 2 || data.room.started;
            } else {
                startButton.classList.add('hidden');
            }

            if (data.room.started) {
                window.location.href = `index.php?page=friendly_game&code=${encodeURIComponent(code)}`;
                return;
            }

            setMessage('Esperando que haya 2 jugadores para iniciar...');
        } catch (error) {
            setMessage(error.message, true);
        }
    }

    startButton.addEventListener('click', async () => {
        startButton.disabled = true;

        try {
            const data = await window.HangmanUI.requestJSON('api/friendly_room_start.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo iniciar la partida.', true);
                startButton.disabled = false;
                return;
            }

            window.location.href = `index.php?page=friendly_game&code=${encodeURIComponent(code)}`;
        } catch (error) {
            setMessage(error.message, true);
            startButton.disabled = false;
        }
    });

    refreshRoomState();
    setInterval(refreshRoomState, 2000);
})();
