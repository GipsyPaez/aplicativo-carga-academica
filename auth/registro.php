<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (usuarioAutenticado()) {
    header('Location: /carga-academica/');
    exit;
}

$error = '';
$nombre = '';
$email = '';
$csrfToken = tokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCSRF();
    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? trim($_POST['nombre'])
        : '';
    $email = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email'])
        : '';
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    if (
        $nombre === '' ||
        mb_strlen($nombre) > 100 ||
        mb_strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Introduce un nombre y un correo electrónico válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();

            $consulta = $pdo->prepare(
                'INSERT INTO usuarios (nombre, email, password)
                 VALUES (:nombre, :email, :password)'
            );

            $consulta->execute([
                'nombre' => $nombre,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $usuarioId = (int) $pdo->lastInsertId();
            $insertarDisponibilidad = $pdo->prepare(
                'INSERT INTO disponibilidad_semanal
                 (usuario_id, dia_semana, horas_disponibles)
                 VALUES (:usuario_id, :dia_semana, NULL)'
            );

            for ($diaSemana = 1; $diaSemana <= 7; $diaSemana++) {
                $insertarDisponibilidad->execute([
                    'usuario_id' => $usuarioId,
                    'dia_semana' => $diaSemana,
                ]);
            }

            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuarioId;
            $_SESSION['usuario_nombre'] = $nombre;

            header('Location: /carga-academica/');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = 'Este correo electrónico ya está registrado.';
            } else {
                error_log($e->getMessage());
                $error = 'No se pudo completar el registro. Inténtalo de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | Carga Académica</title>
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
            background: white;
            padding: 32px;
            border: 1px solid #e8eaf2;
            border-radius: 18px;
        }
        h1 { margin: 0 0 8px; font-size: 26px; }
        .muted { color: #747b91; line-height: 1.5; }
        label { display: block; margin: 17px 0 7px; font-size: 14px; }
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
            background: #fff0f0;
            color: #a32121;
            padding: 12px;
            border-radius: 9px;
        }
        a { color: #5146e5; }
    </style>
</head>
<body>
<main class="card">
    <h1>Crear cuenta 🎓</h1>
    <p class="muted">Regístrate para organizar tu carga académica.</p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label for="nombre">Nombre completo</label>
        <input id="nombre" name="nombre" required maxlength="100"
               autocomplete="name"
               value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">

        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" required maxlength="150"
               autocomplete="email"
               value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password"
               required minlength="8" autocomplete="new-password">

        <button type="submit">Crear mi cuenta</button>
    </form>

    <p class="muted">¿Ya tienes una cuenta?
        <a href="login.php">Inicia sesión</a>
    </p>
</main>
</body>
</html>
