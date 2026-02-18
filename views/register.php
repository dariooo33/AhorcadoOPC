<section class="form-panel narrow">
    <h1>Crear cuenta</h1>
    <p>Empiezas con 30 trofeos al registrarte.</p>

    <form id="registerForm" method="POST" action="index.php?page=register" novalidate>
        <label for="email">Correo electronico</label>
        <input id="email" name="email" type="email" maxlength="120" required>

        <label for="username">Nombre de usuario</label>
        <input id="username" name="username" type="text" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" required>

        <label for="password">Contrasena</label>
        <input id="password" name="password" type="password" minlength="8" required>

        <label for="confirm_password">Confirmar contrasena</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required>

        <button type="submit" class="btn btn-primary w-full">Registrarme</button>
    </form>

    <p class="small-text">Ya tienes cuenta? <a href="index.php?page=login">Inicia sesion</a></p>
</section>
