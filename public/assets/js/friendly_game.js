(() => {
    const app = document.getElementById('friendlyGameApp');

    if (!app) {
        return;
    }

    const code = app.dataset.roomCode;
    const playersList = document.getElementById('friendlyPlayersList');
    const hangman = document.getElementById('friendlyHangman');
    const errorsText = document.getElementById('friendlyErrors');
    const wordMask = document.getElementById('friendlyWordMask');
    const keyboard = document.getElementById('friendlyKeyboard');
    const solveForm = document.getElementById('friendlySolveForm');
    const solveInput = document.getElementById('friendlySolveInput');
    const messageLine = document.getElementById('friendlyGameMessage');

    const state = {
        actionLocked: false,
    };

    function setMessage(message, isError = false) {
        messageLine.textContent = message || '';
        messageLine.style.color = isError ? '#8a2b28' : '#4d5b77';
    }

    function renderPlayers(players) {
        playersList.innerHTML = players
            .map((player) => `<li>${window.HangmanUI.escapeHTML(player.username || '')}</li>`)
            .join('');
    }

    async function refreshState() {
        try {
            const data = await window.HangmanUI.requestJSON(`api/friendly_game_state.php?code=${encodeURIComponent(code)}`);

            if (!data.success) {
                setMessage(data.message || 'No se pudo cargar la partida.', true);
                return;
            }

            const game = data.game;
            renderPlayers(game.players);
            errorsText.textContent = game.shared_errors;
            wordMask.textContent = game.masked_word;
            window.HangmanUI.renderHangman(hangman, game.shared_errors);
            window.HangmanUI.updateKeyboard(keyboard, game.correct_letters, game.incorrect_letters, game.can_play);

            if (game.status === 'finalizada') {
                setMessage(game.result_text || 'La partida amistosa termino.');
                solveInput.disabled = true;
            } else {
                solveInput.disabled = false;
                setMessage('Cooperen para descubrir la palabra.');
            }
        } catch (error) {
            setMessage(error.message, true);
        }
    }

    async function sendAction(action, value) {
        if (state.actionLocked) {
            return;
        }

        try {
            state.actionLocked = true;

            const data = await window.HangmanUI.requestJSON('api/friendly_game_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code, action, value }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo registrar la accion.', true);
                return;
            }

            solveInput.value = '';
            await refreshState();
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;
        }
    }

    window.HangmanUI.renderKeyboard(keyboard, async (letter) => {
        await sendAction('letter', letter);
    });

    solveForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const guess = window.HangmanUI.normalizeWord(solveInput.value || '');

        if (!guess) {
            setMessage('Ingresa una palabra antes de enviar.', true);
            return;
        }

        await sendAction('solve', guess);
    });

    refreshState();
    setInterval(refreshState, 2000);
})();
