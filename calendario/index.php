<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calendario.php';

exigirAutenticacion();

$usuarioId = (int) $_SESSION['usuario_id'];

try {
    $periodo = validarPeriodoCalendario($_GET['mes'] ?? null, $_GET['anio'] ?? null);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Mes o año no válido.');
}

$mes = $periodo['mes'];
$anio = $periodo['anio'];
$primerDia = $periodo['primer_dia'];
$ultimoDia = $periodo['ultimo_dia'];
$cuadricula = generarCuadriculaCalendario($anio, $mes);
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
$diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$mesAnterior = obtenerMesAdyacenteCalendario($anio, $mes, -1);
$mesSiguiente = obtenerMesAdyacenteCalendario($anio, $mes, 1);

$consulta = $pdo->prepare(
    'SELECT a.id, a.titulo, a.tipo, a.fecha_entrega, a.estado,
            s.nombre AS asignatura
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE s.usuario_id = :usuario_id
       AND a.fecha_entrega >= :primer_dia
       AND a.fecha_entrega <= :ultimo_dia
     ORDER BY a.fecha_entrega, a.id'
);
$consulta->execute([
    'usuario_id' => $usuarioId,
    'primer_dia' => $primerDia->format('Y-m-d'),
    'ultimo_dia' => $ultimoDia->format('Y-m-d'),
]);
$actividades = $consulta->fetchAll();
$actividadesPorFecha = [];
foreach ($actividades as $actividad) {
    $actividadesPorFecha[$actividad['fecha_entrega']][] = $actividad;
}

$hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

function escaparCalendario(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function claseEstadoCalendario(string $estado): string
{
    return match ($estado) {
        'Pendiente' => 'event-pending',
        'En progreso' => 'event-in-progress',
        'Completada' => 'event-completed',
        default => 'event-unknown',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendario académico | Carga Académica</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f4f6fb;
            color: #20243a;
            font-family: system-ui, sans-serif;
        }
        main { max-width: 1280px; margin: auto; padding: 28px 20px; }
        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 5px; font-size: 26px; }
        h2 { margin: 0 0 16px; font-size: 18px; }
        p { color: #747b91; }
        .top-actions, .month-navigation, .legend {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
        }
        .panel {
            padding: 18px;
            margin-bottom: 18px;
            background: #fff;
            border: 1px solid #e8eaf2;
            border-radius: 12px;
        }
        .month-navigation { justify-content: space-between; }
        .month-title { min-width: 160px; text-align: center; font-size: 20px; font-weight: 750; }
        .button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 9px 13px;
            border: 0;
            border-radius: 8px;
            background: #5146e5;
            color: #fff;
            font: inherit;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }
        .button-secondary { background: #e9eaf8; color: #343458; }
        .month-form { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; }
        .month-form label { display: block; margin-bottom: 4px; color: #747b91; font-size: 12px; }
        .month-form select, .month-form input {
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #d9dced;
            border-radius: 8px;
            background: #fff;
            color: inherit;
            font: inherit;
        }
        .calendar-scroll { overflow-x: auto; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            min-width: 840px;
            border-top: 1px solid #e8eaf2;
            border-left: 1px solid #e8eaf2;
        }
        .weekday {
            padding: 10px 8px;
            background: #f7f8fc;
            color: #626a80;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            border-right: 1px solid #e8eaf2;
            border-bottom: 1px solid #e8eaf2;
        }
        .day-cell {
            min-width: 0;
            min-height: 136px;
            padding: 7px;
            background: #fff;
            border-right: 1px solid #e8eaf2;
            border-bottom: 1px solid #e8eaf2;
        }
        .day-cell-outside { background: #f7f8fc; color: #9aa0b1; }
        .day-cell-today { box-shadow: inset 0 0 0 2px #5146e5; }
        .day-number { margin-bottom: 6px; font-size: 13px; font-weight: 750; }
        .day-cell-outside .day-number { color: #9aa0b1; }
        .event-list { display: grid; align-content: start; gap: 5px; }
        .event {
            display: block;
            overflow: hidden;
            padding: 6px 7px;
            border-left: 3px solid;
            border-radius: 5px;
            color: #20243a;
            font-size: 11px;
            line-height: 1.35;
            text-decoration: none;
        }
        .event:hover { filter: brightness(.97); text-decoration: underline; }
        .event-pending { border-color: #b47b00; background: #fff4d6; }
        .event-in-progress { border-color: #2867a8; background: #e8f2ff; }
        .event-completed { border-color: #27864a; background: #e8f7ed; }
        .event-unknown { border-color: #6b7280; background: #f0f1f4; }
        .event-title, .event-subject, .event-state { display: block; overflow-wrap: anywhere; }
        .event-title { font-weight: 750; }
        .event-subject { margin-top: 2px; color: #555d70; }
        .event-state { margin-top: 2px; color: #626a80; font-size: 10px; }
        .legend { margin-top: 15px; color: #626a80; font-size: 12px; }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; }
        .dot-pending { background: #b47b00; }
        .dot-progress { background: #2867a8; }
        .dot-completed { background: #27864a; }
        .empty { margin: 0; padding: 12px 0 0; }
        @media (max-width: 640px) {
            main { padding: 18px 10px; }
            .panel { padding: 12px; }
            .top { align-items: flex-start; }
            .month-navigation { justify-content: center; }
            .month-title { order: -1; flex-basis: 100%; }
            .calendar-grid { min-width: 735px; }
            .day-cell { min-height: 122px; padding: 5px; }
            .event { padding: 5px; font-size: 10px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>Calendario académico</h1>
            <p><?= escaparCalendario($meses[$mes]) ?> de <?= $anio ?></p>
        </div>
        <div class="top-actions">
            <a class="button button-secondary" href="/carga-academica/actividades/">Actividades</a>
            <a class="button button-secondary" href="/carga-academica/">Dashboard</a>
        </div>
    </header>

    <section class="panel">
        <div class="month-navigation">
            <?php if ($mesAnterior !== null): ?>
                <a class="button button-secondary" href="?mes=<?= $mesAnterior['mes'] ?>&amp;anio=<?= $mesAnterior['anio'] ?>" aria-label="Mes anterior">← Anterior</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <strong class="month-title"><?= escaparCalendario($meses[$mes]) ?> <?= $anio ?></strong>

            <?php if ($mesSiguiente !== null): ?>
                <a class="button button-secondary" href="?mes=<?= $mesSiguiente['mes'] ?>&amp;anio=<?= $mesSiguiente['anio'] ?>" aria-label="Mes siguiente">Siguiente →</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <form class="month-form" method="get">
                <div>
                    <label for="mes">Mes</label>
                    <select id="mes" name="mes">
                        <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                            <option value="<?= $numeroMes ?>" <?= $numeroMes === $mes ? 'selected' : '' ?>>
                                <?= escaparCalendario($nombreMes) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="anio">Año</label>
                    <input id="anio" name="anio" type="number" min="1000" max="9999" required value="<?= $anio ?>">
                </div>
                <button class="button" type="submit">Ir</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <?php if ($actividades === []): ?>
            <p class="empty">No hay actividades con fecha límite en este mes.</p>
        <?php endif; ?>

        <div class="calendar-scroll">
            <div class="calendar-grid" role="grid" aria-label="Calendario de <?= escaparCalendario($meses[$mes]) ?> de <?= $anio ?>">
                <?php foreach ($diasSemana as $diaSemana): ?>
                    <div class="weekday" role="columnheader"><?= escaparCalendario($diaSemana) ?></div>
                <?php endforeach; ?>

                <?php foreach ($cuadricula as $dia): ?>
                    <?php
                    $clasesCelda = ['day-cell'];
                    if (!$dia['es_mes_actual']) {
                        $clasesCelda[] = 'day-cell-outside';
                    }
                    if ($dia['fecha_iso'] === $hoy) {
                        $clasesCelda[] = 'day-cell-today';
                    }
                    $actividadesDelDia = $actividadesPorFecha[$dia['fecha_iso']] ?? [];
                    ?>
                    <div class="<?= implode(' ', $clasesCelda) ?>" role="gridcell" aria-label="<?= escaparCalendario($dia['fecha']->format('d/m/Y')) ?>">
                        <div class="day-number"><?= $dia['dia_mes'] ?></div>
                        <div class="event-list">
                            <?php foreach ($actividadesDelDia as $actividad): ?>
                                <a class="event <?= claseEstadoCalendario($actividad['estado']) ?>"
                                   href="/carga-academica/actividades/editar.php?id=<?= (int) $actividad['id'] ?>"
                                   title="<?= escaparCalendario($actividad['titulo']) ?> — <?= escaparCalendario($actividad['asignatura']) ?> — <?= escaparCalendario($actividad['estado']) ?>">
                                    <span class="event-title"><?= escaparCalendario($actividad['titulo']) ?></span>
                                    <span class="event-subject"><?= escaparCalendario($actividad['asignatura']) ?></span>
                                    <span class="event-state"><?= escaparCalendario($actividad['estado']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="legend" aria-label="Estados de actividades">
            <span class="legend-item"><span class="legend-dot dot-pending"></span>Pendiente</span>
            <span class="legend-item"><span class="legend-dot dot-progress"></span>En progreso</span>
            <span class="legend-item"><span class="legend-dot dot-completed"></span>Completada</span>
        </div>
    </section>
</main>
</body>
</html>