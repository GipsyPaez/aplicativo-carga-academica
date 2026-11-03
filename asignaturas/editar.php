<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador de asignatura no válido.');
}

$consulta = $pdo->prepare(
    'SELECT id, nombre, codigo, horas_semanales
     FROM asignaturas
     WHERE id = :id AND usuario_id = :usuario_id'
);
$consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
$asignatura = $consulta->fetch();

if (!$asignatura) {
    http_response_code(404);
    exit('No se encontró la asignatura.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCSRF();
    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? trim($_POST['nombre'])
        : '';
    $codigo = isset($_POST['codigo']) && is_string($_POST['codigo'])
        ? trim($_POST['codigo'])
        : '';
    $horas = isset($_POST['horas_semanales']) && is_string($_POST['horas_semanales'])
        ? trim($_POST['horas_semanales'])
        : '';

    if ($nombre === '' || mb_strlen($nombre) > 120) {
        $error = 'Introduce un nombre válido de hasta 120 caracteres.';
    } elseif (mb_strlen($codigo) > 30) {
        $error = 'El código no puede superar los 30 caracteres.';
    } elseif (
        $horas === '' ||
        !is_numeric($horas) ||
        (float) $horas < 0 ||
        (float) $horas > 999.9
    ) {
        $error = 'Introduce unas horas semanales válidas.';
    } else {
        $actualizar = $pdo->prepare(
            'UPDATE asignaturas
             SET nombre = :nombre,
                 codigo = :codigo,
                 horas_semanales = :horas
             WHERE id = :id AND usuario_id = :usuario_id'
        );

        $actualizar->execute([
            'nombre' => $nombre,
            'codigo' => $codigo !== '' ? $codigo : null,
            'horas' => (float) $horas,
            'id' => $id,
            'usuario_id' => $usuarioId,
        ]);

        header('Location: /carga-academica/asignaturas/');
        exit;
    }

    $asignatura['nombre'] = $nombre;
    $asignatura['codigo'] = $codigo;
    $asignatura['horas_semanales'] = $horas;
}

function escaparEditar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar asignatura</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f4f6fb;
            color: #20243a;
            font-family: system-ui, sans-serif;
        }
        main {
            max-width: 620px;
            margin: 30px auto;
            padding: 28px;
            background: white;
            border: 1px solid #e8eaf2;
            border-radius: 16px;
        }
        label {
            display: block;
            margin: 18px 0 7px;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #d9dced;
            border-radius: 9px;
            font: inherit;
        }
        button, .back {
            display: inline-block;
            margin-top: 22px;
            padding: 12px 16px;
            border: 0;
            border-radius: 9px;
            background: #5146e5;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
        }
        .back { background: #e9eaf8; color: #343458; }
        .error { color: #a32121; }
    </style>
</head>
<body>
<main>
    <h1>Editar asignatura</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?= escaparEditar($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escaparEditar($csrfToken) ?>">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required maxlength="120"
               value="<?= escaparEditar($asignatura['nombre']) ?>">

        <label for="codigo">Código</label>
        <input id="codigo" name="codigo" maxlength="30"
               value="<?= escaparEditar($asignatura['codigo'] ?? '') ?>">

        <label for="horas_semanales">Horas semanales</label>
        <input id="horas_semanales" name="horas_semanales"
               type="number" min="0" max="999.9" step="0.5" required
               value="<?= escaparEditar($asignatura['horas_semanales']) ?>">

        <button type="submit">Guardar cambios</button>
        <a class="back" href="index.php">Cancelar</a>
    </form>
</main>
</body>
</html>
