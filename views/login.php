<section class="form-panel narrow">
    <h1>Login</h1>
    <p>Ingresa con correo electronico o nombre de usuario.</p>

    <form id="loginForm" method="POST" action="index.php?page=login" novalidate>
        <label for="login">Correo o usuario</label>
        <input id="login" name="login" type="text" maxlength="120" required>

        <label for="password">Contrasena</label>
        <input id="password" name="password" type="password" minlength="8" required>

        <button type="submit" class="btn btn-primary w-full">Entrar</button>
    </form>

    <p class="small-text">No tienes cuenta? <a href="index.php?page=register">Registrate aqui</a></p>
</section>
