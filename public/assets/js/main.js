(() => {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

    async function requestJSON(url, options = {}) {
        const response = await fetch(url, options);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Error de comunicacion con el servidor.');
        }

        return data;
    }

    function renderKeyboard(container, onClick) {
        container.innerHTML = '';

        alphabet.forEach((letter) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'key-btn';
            button.textContent = letter;
            button.dataset.letter = letter;

            button.addEventListener('click', () => {
                onClick(letter);
            });

            container.appendChild(button);
        });
    }

    function updateKeyboard(container, correctLetters, wrongLetters, enabled = true) {
        const correct = new Set(correctLetters || []);
        const wrong = new Set(wrongLetters || []);

        container.querySelectorAll('.key-btn').forEach((button) => {
            const letter = button.dataset.letter;
            button.classList.remove('correct', 'wrong');

            if (correct.has(letter)) {
                button.classList.add('correct');
                button.disabled = true;
                return;
            }

            if (wrong.has(letter)) {
                button.classList.add('wrong');
                button.disabled = true;
                return;
            }

            button.disabled = !enabled;
        });
    }

    function renderHangman(container, errors) {
        const maxErrors = Math.max(0, Math.min(Number(errors) || 0, 6));
        const partClass = (index, name) => `part ${name} ${maxErrors >= index ? 'visible' : ''}`;

        container.innerHTML = `
            <span class="line base"></span>
            <span class="line pole"></span>
            <span class="line top"></span>
            <span class="line rope"></span>
            <span class="${partClass(1, 'head')}"></span>
            <span class="${partClass(2, 'body')}"></span>
            <span class="${partClass(3, 'arm-left')}"></span>
            <span class="${partClass(4, 'arm-right')}"></span>
            <span class="${partClass(5, 'leg-left')}"></span>
            <span class="${partClass(6, 'leg-right')}"></span>
        `;
    }

    function normalizeWord(value) {
        return value.trim().toUpperCase();
    }

    function escapeHTML(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.HangmanUI = {
        requestJSON,
        renderKeyboard,
        updateKeyboard,
        renderHangman,
        normalizeWord,
        escapeHTML,
    };
})();
