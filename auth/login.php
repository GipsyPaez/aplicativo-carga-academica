<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (usuarioAutenticado()) {
    header('Location: /carga-academica/');
    exit;
}

$error = '';
$email = '';
$csrfToken = tokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCSRF();
    $email = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email'])
        : '';
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Introduce un correo y una contraseña válidos.';
    } else {
        $consulta = $pdo->prepare(
            'SELECT id, nombre, email, password
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );

        $consulta->execute(['email' => $email]);
        $usuario = $consulta->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];

            header('Location: /carga-academica/');
            exit;
        }

        $error = 'Correo electrónico o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Carga Académica</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: system-ui, sans-serif;
            background: #f4f6fb;
            color: #20243a;
        }

        .card {
            width: 100%;
            max-width: 430px;
            padding: 32px;
            background: white;
            border: 1px solid #e8eaf2;
            border-radius: 18px;
        }

        h1 { margin: 0 0 8px; font-size: 26px; }
        .muted { color: #747b91; line-height: 1.5; }

        label {
            display: block;
            margin: 17px 0 7px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9dced;
            border-radius: 9px;
            font: inherit;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 9px;
            background: #5146e5;
            color: white;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            padding: 12px;
            border-radius: 9px;
            background: #fff0f0;
            color: #a32121;
        }

        a { color: #5146e5; }
    </style>
</head>
<body>
<main class="card">
    <h1>Bienvenida de nuevo 👋</h1>
    <p class="muted">Accede a tu espacio de planificación académica.</p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label for="email">Correo electrónico</label>
        <input
            id="email"
            name="email"
            type="email"
            required
            autocomplete="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="password">Contraseña</label>
        <input
            id="password"
            name="password"
            type="password"
            required
            autocomplete="current-password"
        >

        <button type="submit">Iniciar sesión</button>
    </form>

    <p class="muted">
        ¿Todavía no tienes cuenta?
        <a href="registro.php">Regístrate</a>
    </p>
</main>
</body>
</html>
