# Ahorcado OPC (PHP + MySQL)

Aplicacion web completa del juego Ahorcado con:

- Frontend: HTML, CSS, JavaScript.
- Backend: PHP (sesiones para autenticacion).
- Base de datos: MySQL.

Incluye modo **Multijugador** (competitivo con trofeos) y modo **Amistoso** (cooperativo por sala).

## Estructura del proyecto

```text
AhorcadoOPC/
├─ config/
│  ├─ app.php
│  └─ database.php
├─ controllers/
│  ├─ AuthController.php
│  └─ PageController.php
├─ models/
│  ├─ Game.php
│  └─ User.php
├─ public/
│  ├─ index.php
│  ├─ api/
│  └─ assets/
│     ├─ css/style.css
│     └─ js/*.js
├─ sql/
│  └─ schema.sql
└─ views/
   ├─ partials/
   └─ *.php
```

## Requisitos

- PHP 8.0 o superior.
- MySQL 8.0 o superior.
- Apache (XAMPP/WAMP/LAMP).

## Instalacion (XAMPP recomendado)

1. Copia el proyecto en `htdocs`.
   - Ejemplo: `C:/xampp/htdocs/AhorcadoOPC`

2. Inicia Apache y MySQL desde XAMPP.

3. Crea la base de datos importando el script SQL:
   - Abre `http://localhost/phpmyadmin`
   - Crea/importa usando `sql/schema.sql`

4. Configura credenciales de DB en `config/database.php`.
   - Por defecto usa:
     - host: `127.0.0.1`
     - puerto: `3306`
     - db: `ahorcadoopc`
     - user: `root`
     - pass: vacio
   - Puedes cambiarlo con variables de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) o editando valores por defecto.

5. Abre la app en navegador:
   - `http://localhost/AhorcadoOPC/public/index.php`

## Flujo de uso

1. Registrate (empiezas con 30 trofeos).
2. Inicia sesion.
3. Desde Inicio elige:
   - **Multijugador**: emparejamiento automatico, turnos, trofeos y stats.
   - **Amistoso**: crea sala, comparte codigo, cooperativo sin impacto en stats.
4. Revisa Ranking y Perfil.

## Seguridad y validaciones implementadas

- Password hashing con `password_hash` y verificacion con `password_verify`.
- Prepared statements (PDO) para evitar inyeccion SQL.
- Validaciones de formulario en frontend y backend.
- Sesiones PHP para autenticacion y control de acceso.
