(() => {
    const app = document.getElementById('multiplayerApp');

    if (!app) {
        return;
    }

    const currentUserId = Number(app.dataset.userId || 0);

    const waitingBox = document.getElementById('mpWaiting');
    const foundBox = document.getElementById('mpFound');
    const foundText = document.getElementById('mpFoundText');
    const foundCountdown = document.getElementById('mpFoundCountdown');
    const gameBox = document.getElementById('mpGame');
    const keyboard = document.getElementById('mpKeyboard');
    const turnIndicator = document.getElementById('turnIndicator');
    const abandonButton = document.getElementById('mpAbandonBtn');
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
        introToken: 0,
        isAbandoning: false,
    };

    function delay(ms) {
        return new Promise((resolve) => {
            window.setTimeout(resolve, ms);
        });
    }

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

    function isNavigationLocked() {
        return Boolean(state.gameId) && !state.isWaitingQueue && !state.isGameFinished && !state.isAbandoning;
    }

    function handleBlockedNavigation() {
        setMessage('No puedes salir de una partida en curso. Usa "Abandonar partida" si deseas retirarte.', true);
    }

    function handleBeforeUnload(event) {
        notifyQueueExit();

        if (!isNavigationLocked()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    }

    async function playMatchFoundCountdown() {
        const introToken = ++state.introToken;

        if (!foundBox || !foundText || !foundCountdown) {
            waitingBox.classList.add('hidden');
            gameBox.classList.remove('hidden');
            return true;
        }

        waitingBox.classList.add('hidden');
        gameBox.classList.add('hidden');
        foundBox.classList.remove('hidden');

        const values = [3, 2, 1];

        for (const value of values) {
            if (introToken !== state.introToken) {
                return false;
            }

            foundCountdown.textContent = String(value);
            foundText.textContent = `Partida encontrada. Iniciando en ${value}...`;
            await delay(1000);
        }

        if (introToken !== state.introToken) {
            return false;
        }

        foundCountdown.textContent = '0';
        foundText.textContent = 'Preparando partida...';
        await delay(1000);

        if (introToken !== state.introToken) {
            return false;
        }

        foundBox.classList.add('hidden');
        gameBox.classList.remove('hidden');

        return true;
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
                if (foundBox) {
                    foundBox.classList.add('hidden');
                }
                if (abandonButton) {
                    abandonButton.classList.add('hidden');
                }
                gameBox.classList.add('hidden');
                setMessage('Esperando que otro jugador permanezca buscando partida...');
                scheduleFindMatch();
                return;
            }

            state.isWaitingQueue = false;
            state.gameId = Number(data.game_id);

            if (!state.gameId) {
                setMessage('No se pudo identificar la partida encontrada.', true);
                scheduleFindMatch();
                return;
            }

            const introComplete = await playMatchFoundCountdown();

            if (!introComplete) {
                return;
            }

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

        if (abandonButton) {
            abandonButton.classList.remove('hidden');
            abandonButton.disabled = state.actionLocked || state.isAbandoning;
        }

        if (game.status === 'cancelada') {
            state.isGameFinished = false;
            state.isWaitingQueue = false;
            state.gameId = null;
            turnIndicator.textContent = 'Partida cancelada';
            setMessage(game.result_text || 'La partida fue cancelada por inactividad.');
            solveInput.disabled = true;
            hideSummary();
            stopPolling();
            if (foundBox) {
                foundBox.classList.add('hidden');
            }
            if (abandonButton) {
                abandonButton.classList.add('hidden');
            }
            waitingBox.classList.remove('hidden');
            gameBox.classList.add('hidden');
            scheduleFindMatch();
            return;
        }

        if (game.status === 'finalizada') {
            state.isGameFinished = true;
            state.isWaitingQueue = false;
            turnIndicator.textContent = 'Partida finalizada';
            setMessage(game.result_text || 'La partida termino.');
            solveInput.disabled = true;
            showSummary(game);
            stopPolling();
            if (abandonButton) {
                abandonButton.classList.add('hidden');
            }
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

    async function abandonCurrentGame() {
        if (!state.gameId || state.isWaitingQueue || state.isGameFinished || state.actionLocked || state.isAbandoning) {
            return;
        }

        const confirmed = window.confirm('Si abandonas la partida perderas automaticamente y tu rival ganara.');

        if (!confirmed) {
            return;
        }

        try {
            state.actionLocked = true;
            state.isAbandoning = true;

            if (abandonButton) {
                abandonButton.disabled = true;
            }

            const data = await window.HangmanUI.requestJSON('api/multiplayer_abandon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game_id: state.gameId,
                }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo abandonar la partida.', true);
                return;
            }

            state.isGameFinished = true;
            setMessage('Abandonaste la partida. El rival gana automaticamente.');
            await refreshState();
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;
            state.isAbandoning = false;

            if (abandonButton) {
                abandonButton.disabled = false;
            }
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

    if (abandonButton) {
        abandonButton.addEventListener('click', async () => {
            await abandonCurrentGame();
        });
    }

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (!link || !isNavigationLocked()) {
            return;
        }

        const href = (link.getAttribute('href') || '').trim();

        if (href === '' || href.startsWith('#')) {
            return;
        }

        event.preventDefault();
        handleBlockedNavigation();
    }, true);

    window.addEventListener('pagehide', notifyQueueExit);
    window.addEventListener('beforeunload', handleBeforeUnload);

    findMatch();
})();
