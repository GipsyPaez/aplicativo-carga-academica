<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();
$actividadId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$actividadId) {
    header('Location: /carga-academica/actividades/');
    exit;
}

$consulta = $pdo->prepare(
    'SELECT a.id, a.asignatura_id, a.titulo, a.descripcion,
            a.tipo, a.fecha_entrega, a.horas_estimadas,
            a.dificultad, a.prioridad
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE a.id = :id AND s.usuario_id = :usuario_id'
);

$consulta->execute([
    'id' => $actividadId,
    'usuario_id' => $usuarioId
]);

$actividad = $consulta->fetch();

if (!$actividad) {
    http_response_code(404);
    exit('Actividad no encontrada.');
}

$consulta = $pdo->prepare(
    'SELECT id, nombre FROM asignaturas
     WHERE usuario_id = :usuario_id
     ORDER BY nombre'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$asignaturas = $consulta->fetchAll();

$errores = [];
$tiposPermitidos = ['Examen', 'Trabajo', 'Proyecto', 'Tarea'];
$tiposLegacy = ['Lectura', 'Otro'];
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
    $asignaturaId = filter_input(INPUT_POST, 'asignatura_id', FILTER_VALIDATE_INT);
    $fechaEntrega = isset($_POST['fecha_entrega']) && is_string($_POST['fecha_entrega'])
        ? trim($_POST['fecha_entrega'])
        : '';
    $horas = isset($_POST['horas_estimadas']) && is_string($_POST['horas_estimadas'])
        ? trim($_POST['horas_estimadas'])
        : '';

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $errores[] = 'El título es obligatorio y no puede superar 150 caracteres.';
    }

    if ($descripcion !== '' && mb_strlen($descripcion) > 5000) {
        $errores[] = 'La descripción no puede superar los 5000 caracteres.';
    }

    $tipoLegacySinCambios = in_array($actividad['tipo'], $tiposLegacy, true)
        && $tipo === $actividad['tipo'];
    if (!in_array($tipo, $tiposPermitidos, true) && !$tipoLegacySinCambios) {
        $errores[] = 'El tipo de actividad no es válido.';
    }

    if (!in_array($dificultad, $nivelesPermitidos, true)) {
        $errores[] = 'Selecciona una dificultad válida.';
    }

    if (!in_array($prioridad, $nivelesPermitidos, true)) {
        $errores[] = 'Selecciona una prioridad válida.';
    }

    if (!is_numeric($horas) || (float) $horas < 0 || (float) $horas > 9999.9) {
        $errores[] = 'Las horas deben ser un número entre 0 y 9999,9.';
    }

    if ($fechaEntrega !== '') {
        $fecha = DateTime::createFromFormat('!Y-m-d', $fechaEntrega);
        if (!$fecha || $fecha->format('Y-m-d') !== $fechaEntrega) {
            $errores[] = 'La fecha de entrega no es válida.';
        }
    }

    $asignaturaValida = false;

    if ($asignaturaId) {
        foreach ($asignaturas as $asignatura) {
            if ((int) $asignatura['id'] === $asignaturaId) {
                $asignaturaValida = true;
                break;
            }
        }
    }

    if (!$asignaturaValida) {
        $errores[] = 'Selecciona una asignatura válida.';
    }

    if (!$errores) {
        $actualizar = $pdo->prepare(
            'UPDATE actividades a
             INNER JOIN asignaturas s ON s.id = a.asignatura_id
             SET a.asignatura_id = :asignatura_id,
                 a.titulo = :titulo,
                 a.descripcion = :descripcion,
                 a.tipo = :tipo,
                 a.fecha_entrega = :fecha_entrega,
                 a.horas_estimadas = :horas_estimadas,
                 a.dificultad = :dificultad,
                 a.prioridad = :prioridad
             WHERE a.id = :id AND s.usuario_id = :usuario_id'
        );

        $actualizar->execute([
            'asignatura_id' => $asignaturaId,
            'titulo' => $titulo,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'tipo' => $tipo,
            'fecha_entrega' => $fechaEntrega !== '' ? $fechaEntrega : null,
            'horas_estimadas' => (float) $horas,
            'dificultad' => $dificultad,
            'prioridad' => $prioridad,
            'id' => $actividadId,
            'usuario_id' => $usuarioId,
        ]);

        header('Location: /carga-academica/actividades/');
        exit;
    }

    $actividad['asignatura_id'] = $asignaturaId;
    $actividad['titulo'] = $titulo;
    $actividad['descripcion'] = $descripcion;
    $actividad['tipo'] = $tipo;
    $actividad['fecha_entrega'] = $fechaEntrega;
    $actividad['horas_estimadas'] = $horas;
    $actividad['dificultad'] = $dificultad;
    $actividad['prioridad'] = $prioridad;
}

function escaparEdicion(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar actividad - Carga Académica</title>
    <style>
        .classification-pending { color: #855b00; font-weight: 650; }
        .field-error { color: #a32121; }
    </style>
</head>
<body>
    <h1>Editar actividad</h1>

    <?php foreach ($errores as $error): ?>
        <p class="field-error"><?= escaparEdicion($error) ?></p>
    <?php endforeach; ?>

    <?php if ($actividad['dificultad'] === null || $actividad['prioridad'] === null): ?>
        <p class="classification-pending">Actividad pendiente de clasificación. Completa dificultad y prioridad para guardar.</p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escaparEdicion($csrfToken) ?>">
        <p>
            <label for="titulo">Título:</label><br>
            <input id="titulo" name="titulo" required maxlength="150"
                   value="<?= escaparEdicion($actividad['titulo']) ?>">
        </p>

        <p>
            <label for="descripcion">Descripción:</label><br>
            <textarea id="descripcion" name="descripcion"><?= escaparEdicion($actividad['descripcion']) ?></textarea>
        </p>

        <p>
            <label for="asignatura_id">Asignatura:</label><br>
            <select id="asignatura_id" name="asignatura_id" required>
                <?php foreach ($asignaturas as $asignatura): ?>
                    <option value="<?= (int) $asignatura['id'] ?>"
                        <?= (int) $actividad['asignatura_id'] === (int) $asignatura['id'] ? 'selected' : '' ?>>
                        <?= escaparEdicion($asignatura['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="tipo">Tipo:</label><br>
            <select id="tipo" name="tipo" required>
                <option value="">Seleccionar tipo</option>
                <?php
                $tiposParaEdicion = $tiposPermitidos;
                if (in_array($actividad['tipo'], $tiposLegacy, true)) {
                    array_unshift($tiposParaEdicion, $actividad['tipo']);
                }
                foreach ($tiposParaEdicion as $tipoOpcion): ?>
                    <option value="<?= escaparEdicion($tipoOpcion) ?>"
                        <?= $actividad['tipo'] === $tipoOpcion ? 'selected' : '' ?>>
                        <?= escaparEdicion($tipoOpcion) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="dificultad">Dificultad: *</label><br>
            <select id="dificultad" name="dificultad" required>
                <option value="">Seleccionar dificultad</option>
                <?php foreach ($nivelesPermitidos as $nivel): ?>
                    <option value="<?= escaparEdicion($nivel) ?>"
                        <?= $actividad['dificultad'] === $nivel ? 'selected' : '' ?>>
                        <?= escaparEdicion($nivel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="prioridad">Prioridad: *</label><br>
            <select id="prioridad" name="prioridad" required>
                <option value="">Seleccionar prioridad</option>
                <?php foreach ($nivelesPermitidos as $nivel): ?>
                    <option value="<?= escaparEdicion($nivel) ?>"
                        <?= $actividad['prioridad'] === $nivel ? 'selected' : '' ?>>
                        <?= escaparEdicion($nivel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="fecha_entrega">Fecha de entrega:</label><br>
            <input id="fecha_entrega" type="date" name="fecha_entrega"
                   value="<?= escaparEdicion($actividad['fecha_entrega'] ?? '') ?>">
        </p>

        <p>
            <label for="horas_estimadas">Horas estimadas:</label><br>
            <input id="horas_estimadas" type="number" name="horas_estimadas"
                   min="0" step="0.5" required
                   value="<?= escaparEdicion($actividad['horas_estimadas']) ?>">
        </p>

        <button type="submit">Guardar cambios</button>
        <a href="/carga-academica/actividades/">Cancelar</a>
    </form>
</body>
</html>
