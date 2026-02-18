(() => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    if (loginForm) {
        loginForm.addEventListener('submit', (event) => {
            const login = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value;

            if (!login || !password) {
                event.preventDefault();
                alert('Completa todos los campos para iniciar sesion.');
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', (event) => {
            const email = document.getElementById('email').value.trim();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const usernameRegex = /^[A-Za-z0-9_]{3,20}$/;

            if (!email.includes('@')) {
                event.preventDefault();
                alert('Ingresa un correo valido.');
                return;
            }

            if (!usernameRegex.test(username)) {
                event.preventDefault();
                alert('El usuario solo acepta letras, numeros y guion bajo (3-20 chars).');
                return;
            }

            if (password.length < 8) {
                event.preventDefault();
                alert('La contrasena debe tener al menos 8 caracteres.');
                return;
            }

            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Las contrasenas no coinciden.');
            }
        });
    }
})();
