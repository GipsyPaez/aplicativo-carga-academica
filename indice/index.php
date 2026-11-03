<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/indice_carga.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];
$semanaSolicitada = $_GET['semana'] ?? null;

if ($semanaSolicitada !== null) {
    if (
        !is_string($semanaSolicitada) ||
        !preg_match('/^(\d{4})-W(\d{2})$/', $semanaSolicitada, $coincidencias)
    ) {
        http_response_code(400);
        exit('Semana no válida.');
    }

    $anioIso = (int) $coincidencias[1];
    $numeroSemana = (int) $coincidencias[2];
    $fechaInicioSolicitada = (new DateTimeImmutable())
        ->setISODate($anioIso, $numeroSemana, 1)
        ->setTime(0, 0);

    if ($fechaInicioSolicitada->format('o-\\WW') !== $semanaSolicitada) {
        http_response_code(400);
        exit('Semana no válida.');
    }
} else {
    $fechaInicioSolicitada = new DateTimeImmutable('today');
}

$rangoSemana = rangoSemanaAcademica($fechaInicioSolicitada);
$disponibilidadPorDia = array_fill_keys(range(1, 7), null);
$consulta = $pdo->prepare(
    'SELECT dia_semana, horas_disponibles
     FROM disponibilidad_semanal
     WHERE usuario_id = :usuario_id
     ORDER BY dia_semana'
);
$consulta->execute(['usuario_id' => $usuarioId]);
foreach ($consulta->fetchAll() as $fila) {
    $disponibilidadPorDia[(int) $fila['dia_semana']] = $fila['horas_disponibles'];
}

$consulta = $pdo->prepare(
    'SELECT a.id, a.titulo, a.tipo, a.fecha_entrega,
            a.horas_estimadas, a.dificultad, a.prioridad, a.estado,
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
$resultado = calcularIndiceCargaSemanal(
    $actividades,
    $disponibilidadPorDia,
    $rangoSemana['inicio']
);

function escaparIndice(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatearHorasIndice(float $horas): string
{
    return number_format($horas, 2, ',', '.');
}

$semanaIso = $rangoSemana['inicio']->format('o-\\WW');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Índice semanal | Carga Académica</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f4f6fb;
            color: #20243a;
            font-family: system-ui, sans-serif;
        }
        main { max-width: 1080px; margin: auto; padding: 28px 20px; }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 6px; font-size: 26px; }
        h2 { margin: 0 0 16px; font-size: 18px; }
        p { color: #747b91; }
        .panel {
            padding: 22px;
            margin-bottom: 18px;
            background: #fff;
            border: 1px solid #e8eaf2;
            border-radius: 12px;
        }
        .week-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
        }
        label { display: block; margin-bottom: 6px; font-weight: 650; }
        input, button, .button {
            min-height: 42px;
            padding: 9px 12px;
            border: 1px solid #d9dced;
            border-radius: 8px;
            font: inherit;
        }
        button, .button {
            display: inline-flex;
            align-items: center;
            border: 0;
            background: #5146e5;
            color: #fff;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }
        .secondary { background: #e9eaf8; color: #343458; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .metric { padding: 15px; background: #f7f8fc; border-radius: 8px; }
        .metric-label { color: #747b91; font-size: 13px; }
        .metric-value { margin-top: 6px; font-size: 22px; font-weight: 750; }
        .classification { border-left: 4px solid #5146e5; }
        .classification-low { border-left-color: #27864a; }
        .classification-adjusted { border-left-color: #b47b00; }
        .classification-high { border-left-color: #b42318; }
        .warning { padding: 12px 14px; background: #fff7df; color: #765400; border-radius: 8px; }
        .no-load { padding: 12px 14px; background: #e8f7ed; color: #176b36; border-radius: 8px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 11px 9px; border-bottom: 1px solid #e8eaf2; vertical-align: top; }
        th { color: #747b91; font-size: 12px; text-transform: uppercase; }
        .reason { color: #855b00; }
        .empty { color: #747b91; }
        @media (max-width: 680px) {
            main { padding: 20px 12px; }
            .summary-grid { grid-template-columns: 1fr; }
            .panel { padding: 16px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>Índice de carga semanal</h1>
            <p>
                Semana <?= escaparIndice($rangoSemana['inicio']->format('d/m/Y')) ?>
                a <?= escaparIndice($rangoSemana['fin']->format('d/m/Y')) ?>
            </p>
        </div>
        <a class="button secondary" href="/carga-academica/">Volver al panel</a>
    </header>

    <section class="panel">
        <form class="week-form" method="get">
            <div>
                <label for="semana">Semana ISO</label>
                <input id="semana" name="semana" type="week" required
                       value="<?= escaparIndice($semanaIso) ?>">
            </div>
            <button type="submit">Consultar semana</button>
        </form>
    </section>

    <section class="panel">
        <div class="summary-grid">
            <div class="metric">
                <div class="metric-label">Esfuerzo ponderado pendiente</div>
                <div class="metric-value"><?= escaparIndice(formatearHorasIndice($resultado['esfuerzo_ponderado_total'])) ?> h</div>
            </div>
            <div class="metric">
                <div class="metric-label">Disponibilidad semanal</div>
                <div class="metric-value">
                    <?php if ($resultado['disponibilidad_completa']): ?>
                        <?= escaparIndice(formatearHorasIndice($resultado['horas_disponibles'])) ?> h
                    <?php else: ?>
                        <?= escaparIndice(formatearHorasIndice($resultado['horas_disponibles_parcial'])) ?> h parcial
                    <?php endif; ?>
                </div>
            </div>
            <div class="metric classification<?php
                if ($resultado['clasificacion'] === 'Carga baja') {
                    echo ' classification-low';
                } elseif ($resultado['clasificacion'] === 'Carga ajustada') {
                    echo ' classification-adjusted';
                } elseif ($resultado['clasificacion'] === 'Carga alta') {
                    echo ' classification-high';
                }
            ?>">
                <div class="metric-label">Resultado</div>
                <div class="metric-value">
                    <?php if ($resultado['porcentaje'] !== null): ?>
                        <?= escaparIndice(number_format($resultado['porcentaje'], 2, ',', '.')) ?> %
                    <?php else: ?>
                        <?= escaparIndice($resultado['clasificacion'] ?? 'No calculado') ?>
                    <?php endif; ?>
                </div>
                <div><?= escaparIndice($resultado['mensaje']) ?></div>
            </div>
        </div>

        <?php if ($resultado['dias_sin_configurar'] !== []): ?>
            <p class="warning">
                Disponibilidad sin declarar para:
                <?= escaparIndice(implode(', ', $resultado['dias_sin_configurar'])) ?>.
                <a href="/carga-academica/disponibilidad/">Configurar disponibilidad</a>
            </p>
        <?php endif; ?>

        <?php if ($resultado['dias_invalidos'] !== []): ?>
            <p class="warning">
                Hay valores inválidos en:
                <?= escaparIndice(implode(', ', $resultado['dias_invalidos'])) ?>.
                <a href="/carga-academica/disponibilidad/">Revisar disponibilidad</a>
            </p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Actividades incluidas</h2>
        <?php if ($resultado['actividades_incluidas'] === []): ?>
            <p class="empty">Ninguna actividad pendiente con datos válidos dentro de esta semana.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Actividad</th><th>Asignatura</th><th>Fecha límite</th>
                        <th>Horas</th><th>Dificultad</th><th>Factor</th><th>Ponderadas</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultado['actividades_incluidas'] as $actividad): ?>
                        <tr>
                            <td><?= escaparIndice($actividad['titulo']) ?></td>
                            <td><?= escaparIndice($actividad['asignatura'] ?? '') ?></td>
                            <td><?= escaparIndice(date('d/m/Y', strtotime($actividad['fecha_entrega']))) ?></td>
                            <td><?= escaparIndice(formatearHorasIndice((float) $actividad['horas_estimadas'])) ?> h</td>
                            <td><?= escaparIndice($actividad['dificultad']) ?></td>
                            <td><?= escaparIndice(number_format($actividad['factor_dificultad'], 2, ',', '.')) ?></td>
                            <td><?= escaparIndice(formatearHorasIndice($actividad['esfuerzo_ponderado'])) ?> h</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Actividades excluidas</h2>
        <?php if ($resultado['actividades_excluidas'] === []): ?>
            <p class="empty">No hay actividades excluidas en los datos consultados.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Actividad</th><th>Fecha límite</th><th>Estado</th><th>Motivo</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultado['actividades_excluidas'] as $actividad): ?>
                        <tr>
                            <td><?= escaparIndice($actividad['titulo'] ?? 'Sin título') ?></td>
                            <td><?= $actividad['fecha_entrega']
                                ? escaparIndice($actividad['fecha_entrega'])
                                : 'Sin fecha' ?></td>
                            <td><?= escaparIndice($actividad['estado'] ?? 'Sin estado') ?></td>
                            <td class="reason">
                                <?= escaparIndice(implode('; ', $actividad['motivos_exclusion'])) ?>
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