<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();
$error = '';
$nombre = '';
$codigo = '';
$horas = '0';

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
        $error = 'Introduce un nombre de asignatura válido.';
    } elseif ($codigo !== '' && mb_strlen($codigo) > 30) {
        $error = 'El código no puede superar los 30 caracteres.';
    } elseif (
        !is_numeric($horas) ||
        (float) $horas < 0 ||
        (float) $horas > 999.9
    ) {
        $error = 'Las horas semanales deben ser un número entre 0 y 999,9.';
    } else {
        $consulta = $pdo->prepare(
            'INSERT INTO asignaturas (usuario_id, nombre, codigo, horas_semanales)
             VALUES (:usuario_id, :nombre, :codigo, :horas)'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'codigo' => $codigo !== '' ? $codigo : null,
            'horas' => (float) $horas,
        ]);

        header('Location: /carga-academica/asignaturas/');
        exit;
    }
}

$consulta = $pdo->prepare(
    'SELECT id, nombre, codigo, horas_semanales
     FROM asignaturas
     WHERE usuario_id = :usuario_id
     ORDER BY nombre ASC'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$asignaturas = $consulta->fetchAll();

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis asignaturas | Carga Académica</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: #f4f6fb;
            color: #20243a;
        }

        main {
            max-width: 1050px;
            margin: auto;
            padding: 32px 22px;
        }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h1 { margin: 0 0 8px; font-size: 28px; }
        h2 { font-size: 18px; margin-top: 0; }
        p { color: #747b91; }

        .panel {
            background: white;
            border: 1px solid #e8eaf2;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9dced;
            border-radius: 9px;
            font: inherit;
        }

        button, .button {
            display: inline-block;
            border: 0;
            border-radius: 9px;
            background: #5146e5;
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            font: inherit;
            font-size: 14px;
            font-weight: 650;
            cursor: pointer;
        }

        .actions { margin-top: 18px; }
        .secondary { background: #e9eaf8; color: #343458; }

        .error {
            background: #fff0f0;
            color: #a32121;
            padding: 12px;
            border-radius: 9px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #e8eaf2;
        }

        th {
            color: #747b91;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .empty {
            text-align: center;
            padding: 25px 10px;
        }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            main { padding: 22px 14px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>Mis asignaturas 📚</h1>
            <p>Registra y organiza las asignaturas de tu semestre.</p>
        </div>
        <a class="button secondary" href="/carga-academica/">
            ← Volver al panel
        </a>
    </header>

    <section class="panel">
        <h2>Registrar asignatura</h2>

        <?php if ($error !== ''): ?>
            <p class="error"><?= escapar($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
            <div class="form-grid">
                <div>
                    <label for="nombre">Nombre de la asignatura *</label>
                    <input
                        id="nombre"
                        name="nombre"
                        maxlength="120"
                        required
                        value="<?= escapar($nombre) ?>"
                        placeholder="Ej. Ingeniería de software"
                    >
                </div>

                <div>
                    <label for="codigo">Código</label>
                    <input
                        id="codigo"
                        name="codigo"
                        maxlength="30"
                        value="<?= escapar($codigo) ?>"
                        placeholder="Ej. IS101"
                    >
                </div>

                <div>
                    <label for="horas_semanales">Horas por semana</label>
                    <input
                        id="horas_semanales"
                        name="horas_semanales"
                        type="number"
                        min="0"
                        max="999.9"
                        step="0.5"
                        required
                        value="<?= escapar($horas) ?>"
                    >
                </div>
            </div>

            <div class="actions">
                <button type="submit">+ Guardar asignatura</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Asignaturas registradas (<?= count($asignaturas) ?>)</h2>

        <?php if ($asignaturas === []): ?>
            <div class="empty">
                <div style="font-size: 34px">📖</div>
                <strong>Aún no tienes asignaturas</strong>
                <p>Utiliza el formulario para registrar la primera.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Asignatura</th>
                            <th>Código</th>
                            <th>Horas semanales</th>
			    <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($asignaturas as $asignatura): ?>
                        <tr>
                        <td><?= escapar($asignatura['nombre']) ?></td>
<td><?= escapar($asignatura['codigo'] ?? '—') ?></td>
<td><?= escapar($asignatura['horas_semanales']) ?> h</td>
<td>
    <a class="button secondary"
       href="editar.php?id=<?= (int) $asignatura['id'] ?>">
        Editar
    </a>

    <form method="post" action="eliminar.php"
          style="display:inline"
          onsubmit="return confirm('¿Seguro que quieres eliminar esta asignatura y sus actividades?');">
                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
        <input type="hidden" name="id"
               value="<?= (int) $asignatura['id'] ?>">
        <button type="submit"
                style="background:#b42318">
            Eliminar
        </button>
    </form>
</td>
            </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
