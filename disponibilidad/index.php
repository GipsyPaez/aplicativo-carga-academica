<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = tokenCSRF();
$diasSemana = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
$horasPorDia = array_fill_keys(array_keys($diasSemana), null);
$error = '';
$mensaje = $_SESSION['mensaje_disponibilidad'] ?? '';
unset($_SESSION['mensaje_disponibilidad']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCSRF();
    $valoresRecibidos = $_POST['horas'] ?? null;

    if (!is_array($valoresRecibidos) || count($valoresRecibidos) !== 7) {
        $error = 'Debes enviar un valor para cada uno de los siete días.';
    } else {
        foreach ($diasSemana as $diaSemana => $nombreDia) {
            if (!array_key_exists($diaSemana, $valoresRecibidos)) {
                $error = 'Debes enviar un valor para cada uno de los siete días.';
                break;
            }

            $valor = $valoresRecibidos[$diaSemana];
            if (!is_string($valor)) {
                $error = "El valor de {$nombreDia} no es válido.";
                break;
            }

            $valor = trim($valor);
            if ($valor === '') {
                $horasPorDia[$diaSemana] = null;
                continue;
            }

            if (
                !preg_match('/^\d+(?:\.\d{1,2})?$/', $valor) ||
                (float) $valor < 0 ||
                (float) $valor > 999.99
            ) {
                $error = "Introduce para {$nombreDia} un número entre 0 y 999,99 con hasta dos decimales.";
                break;
            }

            $horasPorDia[$diaSemana] = (float) $valor;
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $guardarDia = $pdo->prepare(
                'INSERT INTO disponibilidad_semanal
                 (usuario_id, dia_semana, horas_disponibles)
                 VALUES (:usuario_id, :dia_semana, :horas_disponibles)
                 ON DUPLICATE KEY UPDATE
                   horas_disponibles = VALUES(horas_disponibles)'
            );

            foreach ($diasSemana as $diaSemana => $nombreDia) {
                $guardarDia->execute([
                    'usuario_id' => $usuarioId,
                    'dia_semana' => $diaSemana,
                    'horas_disponibles' => $horasPorDia[$diaSemana],
                ]);
            }

            $pdo->commit();
            $_SESSION['mensaje_disponibilidad'] = 'Disponibilidad semanal guardada.';
            header('Location: /carga-academica/disponibilidad/');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = 'No se pudo guardar la disponibilidad. No se aplicaron cambios.';
        }
    }
} else {
    try {
        $pdo->beginTransaction();
        $crearDia = $pdo->prepare(
            'INSERT IGNORE INTO disponibilidad_semanal
             (usuario_id, dia_semana, horas_disponibles)
             VALUES (:usuario_id, :dia_semana, NULL)'
        );

        foreach ($diasSemana as $diaSemana => $nombreDia) {
            $crearDia->execute([
                'usuario_id' => $usuarioId,
                'dia_semana' => $diaSemana,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());
        $error = 'No se pudo consultar la disponibilidad semanal.';
    }

    $consulta = $pdo->prepare(
        'SELECT dia_semana, horas_disponibles
         FROM disponibilidad_semanal
         WHERE usuario_id = :usuario_id
         ORDER BY dia_semana'
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    foreach ($consulta->fetchAll() as $fila) {
        $horasPorDia[(int) $fila['dia_semana']] = $fila['horas_disponibles'] === null
            ? null
            : (float) $fila['horas_disponibles'];
    }
}

$diasSinConfigurar = [];
$totalHorasDeclaradas = 0.0;
foreach ($diasSemana as $diaSemana => $nombreDia) {
    if ($horasPorDia[$diaSemana] === null) {
        $diasSinConfigurar[] = $nombreDia;
    } else {
        $totalHorasDeclaradas += $horasPorDia[$diaSemana];
    }
}

function escaparDisponibilidad(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disponibilidad semanal | Carga Académica</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f4f6fb;
            color: #20243a;
            font-family: system-ui, sans-serif;
        }
        main { max-width: 760px; margin: auto; padding: 30px 20px; }
        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 22px;
        }
        h1 { margin: 0 0 6px; font-size: 26px; }
        p { color: #747b91; }
        .panel {
            padding: 24px;
            margin-bottom: 18px;
            background: #fff;
            border: 1px solid #e8eaf2;
            border-radius: 12px;
        }
        .day-row {
            display: grid;
            grid-template-columns: minmax(120px, 1fr) minmax(150px, 220px);
            align-items: center;
            gap: 18px;
            padding: 13px 0;
            border-bottom: 1px solid #e8eaf2;
        }
        .day-row:last-of-type { border-bottom: 0; }
        label { font-weight: 650; }
        .day-state {
            display: block;
            margin-top: 3px;
            color: #747b91;
            font-size: 12px;
            font-weight: 400;
        }
        input {
            width: 100%;
            min-width: 0;
            padding: 10px 11px;
            border: 1px solid #d9dced;
            border-radius: 8px;
            font: inherit;
        }
        .summary {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e8eaf2;
        }
        .total { color: #20243a; font-weight: 700; }
        .missing { color: #855b00; }
        .notice, .error, .success {
            padding: 11px 13px;
            border-radius: 8px;
        }
        .notice { background: #fff7df; color: #765400; }
        .error { background: #fff0f0; color: #a32121; }
        .success { background: #e8f7ed; color: #176b36; }
        button, .button {
            display: inline-block;
            padding: 11px 15px;
            border: 0;
            border-radius: 8px;
            background: #5146e5;
            color: #fff;
            font: inherit;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }
        .secondary { background: #e9eaf8; color: #343458; }
        @media (max-width: 520px) {
            main { padding: 20px 12px; }
            .panel { padding: 17px; }
            .day-row { grid-template-columns: minmax(0, 1fr) minmax(120px, 42%); gap: 10px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>Disponibilidad semanal</h1>
            <p>Declara cuántas horas puedes dedicar al estudio cada día.</p>
        </div>
        <a class="button secondary" href="/carga-academica/">Volver al panel</a>
    </header>

    <?php if ($mensaje !== ''): ?>
        <p class="success"><?= escaparDisponibilidad($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= escaparDisponibilidad($error) ?></p>
    <?php endif; ?>

    <?php if ($diasSinConfigurar !== []): ?>
        <p class="notice">
            Días sin configurar: <?= escaparDisponibilidad(implode(', ', $diasSinConfigurar)) ?>.
            Escribe 0 si no tienes disponibilidad ese día; deja el campo vacío si aún no lo has declarado.
        </p>
    <?php endif; ?>

    <section class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= escaparDisponibilidad($csrfToken) ?>">

            <?php foreach ($diasSemana as $diaSemana => $nombreDia): ?>
                <?php $horas = $horasPorDia[$diaSemana]; ?>
                <div class="day-row">
                    <label for="horas-<?= $diaSemana ?>">
                        <?= escaparDisponibilidad($nombreDia) ?>
                        <span class="day-state">
                            <?= $horas === null ? 'Sin configurar' : 'Configurado' ?>
                        </span>
                    </label>
                    <input
                        id="horas-<?= $diaSemana ?>"
                        name="horas[<?= $diaSemana ?>]"
                        type="number"
                        min="0"
                        max="999.99"
                        step="0.01"
                        value="<?= $horas === null ? '' : escaparDisponibilidad(number_format($horas, 2, '.', '')) ?>"
                        aria-label="Horas disponibles el <?= escaparDisponibilidad($nombreDia) ?>"
                    >
                </div>
            <?php endforeach; ?>

            <div class="summary">
                <span class="total">
                    <?= $diasSinConfigurar === [] ? 'Total semanal' : 'Total declarado (parcial)' ?>:
                    <?= escaparDisponibilidad(number_format($totalHorasDeclaradas, 2, ',', '.')) ?> h
                </span>
                <button type="submit">Guardar disponibilidad</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>