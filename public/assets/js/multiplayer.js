(() => {
    const app = document.getElementById('multiplayerApp');

    if (!app) {
        return;
    }

    const currentUserId = Number(app.dataset.userId || 0);

    const waitingBox = document.getElementById('mpWaiting');
    const gameBox = document.getElementById('mpGame');
    const keyboard = document.getElementById('mpKeyboard');
    const turnIndicator = document.getElementById('turnIndicator');
    const messageLine = document.getElementById('mpMessage');
    const wordMask = document.getElementById('wordMask');
    const solveForm = document.getElementById('mpSolveForm');
    const solveInput = document.getElementById('mpSolveInput');
    const player1Name = document.getElementById('player1Name');
    const player2Name = document.getElementById('player2Name');
    const player1Errors = document.getElementById('player1Errors');
    const player2Errors = document.getElementById('player2Errors');
    const hangman1 = document.getElementById('hangman1');
    const hangman2 = document.getElementById('hangman2');
    const summaryBox = document.getElementById('mpSummary');
    const summaryTitle = document.getElementById('mpSummaryTitle');
    const summaryResult = document.getElementById('mpSummaryResult');
    const summaryWinner = document.getElementById('mpSummaryWinner');
    const summaryWord = document.getElementById('mpSummaryWord');
    const summaryErrors = document.getElementById('mpSummaryErrors');
    const summaryTrophies = document.getElementById('mpSummaryTrophies');

    const state = {
        gameId: null,
        pollingRef: null,
        actionLocked: false,
        isWaitingQueue: false,
        queueExitNotified: false,
        isGameFinished: false,
        keyboardMounted: false,
    };

    function setMessage(message, isError = false) {
        messageLine.textContent = message || '';
        messageLine.style.color = isError ? '#8a2b28' : '#4d5b77';
    }

    function mountKeyboard() {
        if (state.keyboardMounted) {
            return;
        }

        window.HangmanUI.renderKeyboard(keyboard, async (letter) => {
            if (state.actionLocked) {
                return;
            }

            await sendAction('letter', letter);
        });

        state.keyboardMounted = true;
    }

    function stopPolling() {
        if (state.pollingRef !== null) {
            window.clearInterval(state.pollingRef);
            state.pollingRef = null;
        }
    }

    function hideSummary() {
        summaryBox.classList.add('hidden');
    }

    function showSummary(game) {
        const winnerId = game.winner_id !== null ? Number(game.winner_id) : null;
        let winnerName = 'Sin ganador';

        if (game.winner_username) {
            winnerName = game.winner_username;
        } else if (winnerId === Number(game.player1.id)) {
            winnerName = game.player1.username;
        } else if (winnerId === Number(game.player2.id)) {
            winnerName = game.player2.username;
        }

        let resultLabel = 'Partida finalizada';
        let trophyText = 'Se actualizaron trofeos y estadisticas segun el resultado final.';

        if (winnerId === null) {
            resultLabel = 'Sin ganador';
            trophyText = 'No se detecto un ganador para esta partida.';
        } else if (winnerId === currentUserId) {
            resultLabel = 'Victoria';
            trophyText = 'Recibiste +30 trofeos por ganar la partida.';
        } else {
            resultLabel = 'Derrota';
            trophyText = 'Perdiste entre 25 y 28 trofeos por la derrota.';
        }

        summaryTitle.textContent = 'Resumen de la partida';
        summaryResult.textContent = resultLabel;
        summaryWinner.textContent = winnerName;
        summaryWord.textContent = (game.masked_word || '').replace(/\s+/g, ' ').trim();
        summaryErrors.textContent = `${game.player1.username}: ${game.player1.errors}/6 | ${game.player2.username}: ${game.player2.errors}/6`;
        summaryTrophies.textContent = trophyText;

        summaryBox.classList.remove('hidden');
    }

    function scheduleFindMatch() {
        window.setTimeout(findMatch, 2500);
    }

    function notifyQueueExit() {
        if (!state.isWaitingQueue || !state.gameId || state.queueExitNotified) {
            return;
        }

        state.queueExitNotified = true;

        const payload = JSON.stringify({
            game_id: state.gameId,
        });

        if (navigator.sendBeacon) {
            const body = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('api/multiplayer_cancel.php', body);
            return;
        }

        fetch('api/multiplayer_cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: payload,
            keepalive: true,
        });
    }

    async function findMatch() {
        if (state.isGameFinished) {
            return;
        }

        try {
            const data = await window.HangmanUI.requestJSON('api/multiplayer_match.php');

            if (!data.success) {
                setMessage(data.message || 'No se pudo buscar partida.', true);
                scheduleFindMatch();
                return;
            }

            if (data.waiting) {
                state.isWaitingQueue = true;
                state.gameId = Number(data.game_id) || state.gameId;
                waitingBox.classList.remove('hidden');
                gameBox.classList.add('hidden');
                setMessage('Esperando que otro jugador permanezca buscando partida...');
                scheduleFindMatch();
                return;
            }

            state.isWaitingQueue = false;
            state.gameId = Number(data.game_id);
            waitingBox.classList.add('hidden');
            gameBox.classList.remove('hidden');
            mountKeyboard();
            await refreshState();

            if (state.pollingRef === null) {
                state.pollingRef = window.setInterval(refreshState, 2000);
            }
        } catch (error) {
            setMessage(error.message, true);
            scheduleFindMatch();
        }
    }

    function renderState(game) {
        player1Name.textContent = game.player1.username;
        player2Name.textContent = game.player2.username;

        player1Errors.textContent = game.player1.errors;
        player2Errors.textContent = game.player2.errors;
        wordMask.textContent = game.masked_word;

        window.HangmanUI.renderHangman(hangman1, game.player1.errors);
        window.HangmanUI.renderHangman(hangman2, game.player2.errors);
        window.HangmanUI.updateKeyboard(keyboard, game.correct_letters, game.incorrect_letters, game.can_play);

        if (game.status === 'finalizada') {
            state.isGameFinished = true;
            state.isWaitingQueue = false;
            turnIndicator.textContent = 'Partida finalizada';
            setMessage(game.result_text || 'La partida termino.');
            solveInput.disabled = true;
            showSummary(game);
            stopPolling();
            return;
        }

        hideSummary();
        solveInput.disabled = !game.can_play;
        turnIndicator.textContent = game.can_play ? 'Tu turno' : 'Turno rival';
        setMessage(game.info_text || 'Selecciona una letra o intenta resolver la palabra.');
    }

    async function refreshState() {
        if (!state.gameId) {
            return;
        }

        try {
            const data = await window.HangmanUI.requestJSON(`api/multiplayer_state.php?game_id=${state.gameId}`);

            if (!data.success) {
                setMessage(data.message || 'No fue posible cargar el estado.', true);
                return;
            }

            renderState(data.game);
        } catch (error) {
            setMessage(error.message, true);
        }
    }

    async function sendAction(action, value) {
        if (!state.gameId) {
            return;
        }

        try {
            state.actionLocked = true;

            const data = await window.HangmanUI.requestJSON('api/multiplayer_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game_id: state.gameId,
                    action,
                    value,
                }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo procesar la jugada.', true);
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

    solveForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const guess = window.HangmanUI.normalizeWord(solveInput.value || '');

        if (!guess) {
            setMessage('Ingresa una palabra antes de enviar.', true);
            return;
        }

        await sendAction('solve', guess);
    });

    window.addEventListener('pagehide', notifyQueueExit);
    window.addEventListener('beforeunload', notifyQueueExit);

    findMatch();
})();
