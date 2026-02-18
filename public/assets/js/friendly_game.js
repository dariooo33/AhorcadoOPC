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
    const abandonButton = document.getElementById('friendlyAbandonBtn');
    const replayActions = document.getElementById('friendlyReplayActions');
    const replayButton = document.getElementById('friendlyReplayBtn');
    const replayStatus = document.getElementById('friendlyReplayStatus');

    const state = {
        actionLocked: false,
        isAbandoning: false,
        gameStatus: null,
        redirectTimer: null,
        pollingRef: null,
        queueExitNotified: false,
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

    function setReplayStatus(message, isError = false) {
        if (!replayStatus) {
            return;
        }

        replayStatus.textContent = message || '';
        replayStatus.style.color = isError ? '#8a2b28' : '#4d5b77';

        if (message) {
            replayStatus.classList.remove('hidden');
            return;
        }

        replayStatus.classList.add('hidden');
    }

    function stopPolling() {
        if (state.pollingRef !== null) {
            window.clearInterval(state.pollingRef);
            state.pollingRef = null;
        }
    }

    function isNavigationLocked() {
        return state.gameStatus === 'en_curso' && !state.isAbandoning;
    }

    function handleBlockedNavigation() {
        setMessage('No puedes salir de una partida cooperativa en curso. Usa "Abandonar partida".', true);
    }

    function scheduleRedirectToMenu(delayMs = 1700) {
        if (state.redirectTimer !== null) {
            return;
        }

        state.redirectTimer = window.setTimeout(() => {
            window.location.href = 'index.php?page=home';
        }, delayMs);
    }

    function notifyForcedExit() {
        if (!isNavigationLocked() || state.queueExitNotified) {
            return;
        }

        state.queueExitNotified = true;

        const payload = JSON.stringify({ code });

        if (navigator.sendBeacon) {
            const body = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('api/friendly_game_abandon.php', body);
            return;
        }

        fetch('api/friendly_game_abandon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: payload,
            keepalive: true,
        });
    }

    function handleBeforeUnload(event) {
        if (!isNavigationLocked()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    }

    async function refreshState() {
        try {
            const data = await window.HangmanUI.requestJSON(`api/friendly_game_state.php?code=${encodeURIComponent(code)}`);

            if (!data.success) {
                setMessage(data.message || 'No se pudo cargar la partida.', true);
                return;
            }

            const game = data.game;
            state.gameStatus = game.status;
            renderPlayers(game.players);
            errorsText.textContent = game.shared_errors;
            wordMask.textContent = game.masked_word;
            window.HangmanUI.renderHangman(hangman, game.shared_errors);
            window.HangmanUI.updateKeyboard(keyboard, game.correct_letters, game.incorrect_letters, game.can_play);

            if (abandonButton) {
                const canAbandon = game.status === 'en_curso';
                abandonButton.classList.toggle('hidden', !canAbandon);
                abandonButton.disabled = !canAbandon || state.actionLocked || state.isAbandoning;
            }

            if (game.status === 'cancelada') {
                solveInput.disabled = true;
                if (replayActions) {
                    replayActions.classList.add('hidden');
                }

                if (replayButton) {
                    replayButton.textContent = 'Volver a jugar';
                    replayButton.disabled = false;
                }

                setReplayStatus('');
                setMessage(game.cancel_message || 'Tu companero ha abandonado la partida.', true);
                stopPolling();
                scheduleRedirectToMenu();
                return;
            }

            if (game.status === 'finalizada') {
                setMessage(game.result_text || 'La partida amistosa termino.');
                solveInput.disabled = true;
                if (replayActions) {
                    replayActions.classList.remove('hidden');
                }

                if (replayButton) {
                    replayButton.textContent = game.rematch_user_ready ? 'Listo' : 'Volver a jugar';
                    replayButton.disabled = state.actionLocked || game.rematch_user_ready || game.rematch_required_count < 2;
                }

                setReplayStatus(game.rematch_status_text || 'Pulsa "Volver a jugar" para iniciar revancha.');
            } else {
                state.queueExitNotified = false;
                solveInput.disabled = false;
                setMessage('Cooperen para descubrir la palabra.');
                if (replayActions) {
                    replayActions.classList.add('hidden');
                }

                if (replayButton) {
                    replayButton.textContent = 'Volver a jugar';
                    replayButton.disabled = false;
                }

                setReplayStatus('');
            }
        } catch (error) {
            setMessage(error.message, true);
            setReplayStatus(error.message, true);
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

            if (action !== 'replay') {
                solveInput.value = '';
            }

            await refreshState();
        } catch (error) {
            setMessage(error.message, true);
            if (action === 'replay') {
                setReplayStatus(error.message, true);
            }
        } finally {
            state.actionLocked = false;
        }
    }

    async function abandonFriendlyGame() {
        if (state.actionLocked || state.isAbandoning || state.gameStatus !== 'en_curso') {
            return;
        }

        const confirmed = window.confirm('Si abandonas la partida cooperativa, tu companero sera enviado al menu.');

        if (!confirmed) {
            return;
        }

        let abandoned = false;

        try {
            state.actionLocked = true;
            state.isAbandoning = true;

            if (abandonButton) {
                abandonButton.disabled = true;
            }

            const data = await window.HangmanUI.requestJSON('api/friendly_game_abandon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code }),
            });

            if (!data.success) {
                setMessage(data.message || 'No se pudo abandonar la partida.', true);
                return;
            }

            abandoned = true;
            state.gameStatus = 'cancelada';
            state.queueExitNotified = true;
            setReplayStatus('');
            setMessage('Has abandonado la partida cooperativa.', true);
            stopPolling();
            scheduleRedirectToMenu(700);
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            state.actionLocked = false;

            if (!abandoned) {
                state.isAbandoning = false;

                if (abandonButton) {
                    abandonButton.disabled = false;
                }
            }
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

    if (replayButton) {
        replayButton.addEventListener('click', async () => {
            await sendAction('replay', '');
        });
    }

    if (abandonButton) {
        abandonButton.addEventListener('click', async () => {
            await abandonFriendlyGame();
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

    window.addEventListener('pagehide', notifyForcedExit);
    window.addEventListener('beforeunload', handleBeforeUnload);

    refreshState();
    state.pollingRef = window.setInterval(refreshState, 2000);
})();
