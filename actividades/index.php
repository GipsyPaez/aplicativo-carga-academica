<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();
$error = '';

$consulta = $pdo->prepare(
    'SELECT id, nombre
     FROM asignaturas
     WHERE usuario_id = :usuario_id
     ORDER BY nombre'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$asignaturas = $consulta->fetchAll();

$titulo = '';
$descripcion = '';
$tipo = '';
$dificultad = '';
$prioridad = '';
$fechaEntrega = '';
$horas = '1';
$asignaturaId = '';

$tiposPermitidos = ['Examen', 'Trabajo', 'Proyecto', 'Tarea'];
$nivelesPermitidos = ['Baja', 'Media', 'Alta'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCSRF();
    $titulo = isset($_POST['titulo']) && is_string($_POST['titulo'])
        ? trim($_POST['titulo'])
        : '';
    $descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion'])
        ? trim($_POST['descripcion'])
        : '';
    $tipo = isset($_POST['tipo']) && is_string($_POST['tipo'])
        ? trim($_POST['tipo'])
        : '';
    $dificultad = isset($_POST['dificultad']) && is_string($_POST['dificultad'])
        ? trim($_POST['dificultad'])
        : '';
    $prioridad = isset($_POST['prioridad']) && is_string($_POST['prioridad'])
        ? trim($_POST['prioridad'])
        : '';
    $fechaEntrega = isset($_POST['fecha_entrega']) && is_string($_POST['fecha_entrega'])
        ? trim($_POST['fecha_entrega'])
        : '';
    $horas = isset($_POST['horas_estimadas']) && is_string($_POST['horas_estimadas'])
        ? trim($_POST['horas_estimadas'])
        : '';
    $asignaturaId = $_POST['asignatura_id'] ?? '';

    $idValido = filter_var(
        $asignaturaId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $error = 'Introduce un título de hasta 150 caracteres.';
    } elseif ($descripcion !== '' && mb_strlen($descripcion) > 5000) {
        $error = 'La descripción no puede superar los 5000 caracteres.';
    } elseif (!in_array($tipo, $tiposPermitidos, true)) {
        $error = 'Selecciona un tipo de actividad válido.';
    } elseif (!in_array($dificultad, $nivelesPermitidos, true)) {
        $error = 'Selecciona una dificultad válida.';
    } elseif (!in_array($prioridad, $nivelesPermitidos, true)) {
        $error = 'Selecciona una prioridad válida.';
    } elseif (!$idValido) {
        $error = 'Selecciona una asignatura válida.';
    } elseif (
        $horas === '' ||
        !is_numeric($horas) ||
        (float) $horas < 0 ||
        (float) $horas > 9999.9
    ) {
        $error = 'Las horas estimadas deben ser un número válido entre 0 y 9999,9.';
    } elseif (
        $fechaEntrega !== '' &&
        (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega) ||
            !checkdate(
                (int) substr($fechaEntrega, 5, 2),
                (int) substr($fechaEntrega, 8, 2),
                (int) substr($fechaEntrega, 0, 4)
            )
        )
    ) {
        $error = 'Introduce una fecha de entrega válida.';
    } else {
        $verificar = $pdo->prepare(
            'SELECT id FROM asignaturas
             WHERE id = :id AND usuario_id = :usuario_id'
        );
        $verificar->execute([
            'id' => (int) $idValido,
            'usuario_id' => $usuarioId,
        ]);

        if (!$verificar->fetch()) {
            $error = 'La asignatura seleccionada no te pertenece.';
        } else {
            $insertar = $pdo->prepare(
                'INSERT INTO actividades
                 (asignatura_id, titulo, descripcion, tipo,
                                    fecha_entrega, horas_estimadas, dificultad, prioridad, estado)
                 VALUES
                 (:asignatura_id, :titulo, :descripcion, :tipo,
                                    :fecha_entrega, :horas_estimadas, :dificultad,
                                    :prioridad, :estado)'
            );

            $insertar->execute([
                'asignatura_id' => (int) $idValido,
                'titulo' => $titulo,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'tipo' => $tipo,
                'fecha_entrega' => $fechaEntrega !== '' ? $fechaEntrega : null,
                'horas_estimadas' => (float) $horas,
                'dificultad' => $dificultad,
                'prioridad' => $prioridad,
                'estado' => 'Pendiente',
            ]);

            header('Location: /carga-academica/actividades/');
            exit;
        }
    }
}

$consulta = $pdo->prepare(
    'SELECT a.id, a.titulo, a.descripcion, a.tipo,
            a.fecha_entrega, a.horas_estimadas, a.dificultad,
            a.prioridad, a.estado,
            s.nombre AS asignatura
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE s.usuario_id = :usuario_id
     ORDER BY
       CASE WHEN a.fecha_entrega IS NULL THEN 1 ELSE 0 END,
       a.fecha_entrega ASC,
       a.id DESC'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$actividades = $consulta->fetchAll();

function escaparActividad(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actividades académicas</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: #f4f6fb;
            color: #20243a;
        }

        main {
            max-width: 1150px;
            margin: auto;
            padding: 30px 20px;
        }

        h1 { margin: 0 0 8px; }
        h2 { font-size: 19px; margin-top: 0; }
        p { color: #747b91; }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
        }

        .top-links { display: flex; flex-wrap: wrap; gap: 8px; }

        .panel {
            background: white;
            border: 1px solid #e8eaf2;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .full { grid-column: 1 / -1; }

        label {
            display: block;
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 7px;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #d9dced;
            border-radius: 9px;
            font: inherit;
            background: white;
        }

        textarea { min-height: 85px; resize: vertical; }

        button, .button {
            display: inline-block;
            border: 0;
            border-radius: 9px;
            background: #5146e5;
            color: white;
            padding: 11px 15px;
            font: inherit;
            font-size: 14px;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }

        .secondary { background: #e9eaf8; color: #343458; }
        .error { background: #fff0f0; color: #a32121; padding: 12px; border-radius: 9px; }

        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; text-align: left; }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #e8eaf2;
            vertical-align: top;
        }

        th {
            color: #747b91;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #fff1cf;
            color: #855b00;
            font-size: 12px;
            white-space: nowrap;
        }

        .classification-pending {
            display: inline-block;
            margin-top: 6px;
            color: #855b00;
            font-size: 12px;
            font-weight: 650;
        }

        @media (max-width: 650px) {
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            main { padding: 20px 12px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>Actividades académicas 📝</h1>
            <p>Organiza tus trabajos, exámenes y fechas de entrega.</p>
        </div>
        <div class="top-links">
            <a class="button secondary" href="/carga-academica/calendario/">Ver calendario</a>
            <a class="button secondary" href="/carga-academica/">← Volver al panel</a>
        </div>
    </header>

    <section class="panel">
        <h2>Registrar actividad</h2>

        <?php if ($error !== ''): ?>
            <p class="error"><?= escaparActividad($error) ?></p>
        <?php endif; ?>

        <?php if ($asignaturas === []): ?>
            <p>
                Primero debes registrar una asignatura.
                <a href="/carga-academica/asignaturas/">Ir a asignaturas</a>
            </p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escaparActividad($csrfToken) ?>">
                <div class="form-grid">
                    <div class="full">
                        <label for="titulo">Título de la actividad *</label>
                        <input id="titulo" name="titulo" required maxlength="150"
                               value="<?= escaparActividad($titulo) ?>"
                               placeholder="Ej. Entrega del trabajo final">
                    </div>

                    <div>
                        <label for="asignatura_id">Asignatura *</label>
                        <select id="asignatura_id" name="asignatura_id" required>
                            <option value="">Seleccionar asignatura</option>
                            <?php foreach ($asignaturas as $asignatura): ?>
                                <option value="<?= (int) $asignatura['id'] ?>"
                                    <?= (string) $asignatura['id'] === $asignaturaId ? 'selected' : '' ?>>
                                    <?= escaparActividad($asignatura['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="tipo">Tipo de actividad *</label>
                        <select id="tipo" name="tipo" required>
                            <option value="">Seleccionar tipo</option>
                            <?php foreach ($tiposPermitidos as $opcion): ?>
                                <option value="<?= escaparActividad($opcion) ?>"
                                    <?= $tipo === $opcion ? 'selected' : '' ?>>
                                    <?= escaparActividad($opcion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="dificultad">Dificultad *</label>
                        <select id="dificultad" name="dificultad" required>
                            <option value="">Seleccionar dificultad</option>
                            <?php foreach ($nivelesPermitidos as $nivel): ?>
                                <option value="<?= escaparActividad($nivel) ?>"
                                    <?= $dificultad === $nivel ? 'selected' : '' ?>>
                                    <?= escaparActividad($nivel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="prioridad">Prioridad *</label>
                        <select id="prioridad" name="prioridad" required>
                            <option value="">Seleccionar prioridad</option>
                            <?php foreach ($nivelesPermitidos as $nivel): ?>
                                <option value="<?= escaparActividad($nivel) ?>"
                                    <?= $prioridad === $nivel ? 'selected' : '' ?>>
                                    <?= escaparActividad($nivel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="fecha_entrega">Fecha de entrega</label>
                        <input id="fecha_entrega" name="fecha_entrega"
                               type="date" value="<?= escaparActividad($fechaEntrega) ?>">
                    </div>

                    <div>
                        <label for="horas_estimadas">Horas estimadas *</label>
                        <input id="horas_estimadas" name="horas_estimadas"
                               type="number" min="0" max="9999.9" step="0.5"
                               required value="<?= escaparActividad($horas) ?>">
                    </div>

                    <div class="full">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"
                                  maxlength="5000"><?= escaparActividad($descripcion) ?></textarea>
                    </div>
                </div>

                <p>
                    <button type="submit">+ Guardar actividad</button>
                </p>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Actividades registradas (<?= count($actividades) ?>)</h2>

        <?php if ($actividades === []): ?>
            <p>Aún no tienes actividades registradas.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Actividad</th>
                        <th>Asignatura</th>
                        <th>Tipo</th>
                        <th>Dificultad</th>
                        <th>Prioridad</th>
                        <th>Entrega</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($actividades as $actividad): ?>
                        <tr>
                            <td>
                                <strong><?= escaparActividad($actividad['titulo']) ?></strong>
                                <?php if ($actividad['dificultad'] === null || $actividad['prioridad'] === null): ?>
                                    <br><small class="classification-pending">Pendiente de clasificación</small>
                                <?php endif; ?>
                                <?php if ($actividad['descripcion']): ?>
                                    <br>
                                    <small><?= escaparActividad($actividad['descripcion']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= escaparActividad($actividad['asignatura']) ?></td>
                            <td><?= escaparActividad($actividad['tipo']) ?></td>
                            <td><?= $actividad['dificultad'] !== null
                                ? escaparActividad($actividad['dificultad'])
                                : 'Sin clasificar' ?></td>
                            <td><?= $actividad['prioridad'] !== null
                                ? escaparActividad($actividad['prioridad'])
                                : 'Sin clasificar' ?></td>
                            <td>
                                <?= $actividad['fecha_entrega']
                                    ? escaparActividad(date('d/m/Y', strtotime($actividad['fecha_entrega'])))
                                    : 'Sin fecha' ?>
                            </td>
                            <td><?= escaparActividad($actividad['horas_estimadas']) ?> h</td>
                            <td>
                                <form method="post" action="estado.php">
                                    <input type="hidden" name="csrf_token" value="<?= escaparActividad($csrfToken) ?>">
                                    <input type="hidden" name="id"
                                           value="<?= (int) $actividad['id'] ?>">
                                    <select name="estado" aria-label="Estado de la actividad"
                                            onchange="this.form.submit()">
                                        <?php foreach (['Pendiente', 'En progreso', 'Completada'] as $estado): ?>
                                            <option value="<?= escaparActividad($estado) ?>"
                                                <?= $actividad['estado'] === $estado ? 'selected' : '' ?>>
                                                <?= escaparActividad($estado) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit">Actualizar estado</button></noscript>
                                </form>
                            </td>
            <td>
                                <a href="editar.php?id=<?= (int) $actividad['id'] ?>">
                                    Editar
                                </a>
                                <form method="post" action="eliminar.php"
                                      onsubmit="return confirm('¿Seguro que deseas eliminar esta actividad?');"
                                      style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?= escaparActividad($csrfToken) ?>">
                                    <input type="hidden" name="id"
                                           value="<?= (int) $actividad['id'] ?>">
                                    <button type="submit">Eliminar</button>
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
