(() => {
    const app = document.getElementById('friendlyLobbyApp');

    if (!app) {
        return;
    }

    const createRoomBtn = document.getElementById('createRoomBtn');
    const joinRoomForm = document.getElementById('joinRoomForm');
    const joinCodeInput = document.getElementById('joinCode');
    const messageLine = document.getElementById('friendlyLobbyMessage');

    function setMessage(message, isError = false) {
        messageLine.textContent = message || '';
        messageLine.style.color = isError ? '#8a2b28' : '#4d5b77';
    }

    createRoomBtn.addEventListener('click', async () => {
        createRoomBtn.disabled = true;
        setMessage('Creando sala...');

        try {
            const data = await window.HangmanUI.requestJSON('api/friendly_room_create.php', {
                method: 'POST',
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo crear la sala.', true);
                return;
            }

            window.location.href = `index.php?page=friendly_waiting&code=${encodeURIComponent(data.code)}`;
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            createRoomBtn.disabled = false;
        }
    });

    joinRoomForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const code = window.HangmanUI.normalizeWord(joinCodeInput.value || '');

        if (!code) {
            setMessage('Ingresa un codigo valido.', true);
            return;
        }

        try {
            const data = await window.HangmanUI.requestJSON('api/friendly_room_join.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo unir a la sala.', true);
                return;
            }

            window.location.href = `index.php?page=friendly_waiting&code=${encodeURIComponent(code)}`;
        } catch (error) {
            setMessage(error.message, true);
        }
    });
})();
