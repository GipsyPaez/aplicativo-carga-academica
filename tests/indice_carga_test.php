<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/indice_carga.php';

$pruebasEjecutadas = 0;

function comprobar(bool $condicion, string $nombre): void
{
    global $pruebasEjecutadas;
    $pruebasEjecutadas++;

    if (!$condicion) {
        throw new RuntimeException("FALLO: {$nombre}");
    }

    echo "OK: {$nombre}\n";
}

function actividadPrueba(
    string $titulo,
    ?string $fecha,
    mixed $horas,
    ?string $dificultad,
    string $estado = 'Pendiente',
    string $prioridad = 'Baja'
): array {
    return [
        'id' => 1,
        'titulo' => $titulo,
        'asignatura' => 'Prueba',
        'fecha_entrega' => $fecha,
        'horas_estimadas' => $horas,
        'dificultad' => $dificultad,
        'prioridad' => $prioridad,
        'estado' => $estado,
    ];
}

function disponibilidadConTotal(float $total): array
{
    return [1 => $total, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];
}

$fechaSemana = new DateTimeImmutable('2026-10-28');
$rango = rangoSemanaAcademica($fechaSemana);
$inicioTexto = $rango['inicio']->format('Y-m-d');
$finTexto = $rango['fin']->format('Y-m-d');

comprobar($inicioTexto === '2026-10-26' && $finTexto === '2026-11-01', 'Semana de lunes a domingo cruzando de mes');

$rangoAnual = rangoSemanaAcademica(new DateTimeImmutable('2026-12-31'));
comprobar(
    $rangoAnual['inicio']->format('Y-m-d') === '2026-12-28'
        && $rangoAnual['fin']->format('Y-m-d') === '2027-01-03',
    'Semana de lunes a domingo cruzando de año'
);

$actividadOficial = [
    actividadPrueba('Baja', '2026-10-27', 4, 'Baja'),
    actividadPrueba('Alta', '2026-10-30', 3, 'Alta'),
];
$resultadoOficial = calcularIndiceCargaSemanal(
    $actividadOficial,
    disponibilidadConTotal(10),
    $fechaSemana
);
comprobar(
    abs($resultadoOficial['esfuerzo_ponderado_total'] - 8.5) < 0.000001
        && abs((float) $resultadoOficial['porcentaje'] - 85.0) < 0.000001
        && $resultadoOficial['clasificacion'] === 'Carga ajustada',
    'Ejemplo oficial: 8,5 ponderadas / 10 disponibles = 85 % ajustada'
);

$casosUmbral = [
    [7.99, 10.0, 'Carga baja'],
    [8.0, 10.0, 'Carga ajustada'],
    [10.0, 10.0, 'Carga ajustada'],
    [10.01, 10.0, 'Carga alta'],
];
foreach ($casosUmbral as [$esfuerzo, $disponibilidad, $clasificacionEsperada]) {
    $resultado = calcularIndiceCargaSemanal(
        [actividadPrueba('Umbral', '2026-10-28', $esfuerzo, 'Baja')],
        disponibilidadConTotal($disponibilidad),
        $fechaSemana
    );
    comprobar($resultado['clasificacion'] === $clasificacionEsperada, "Clasificación {$esfuerzo}/{$disponibilidad}: {$clasificacionEsperada}");
}

$sinHoras = calcularIndiceCargaSemanal(
    [actividadPrueba('Esfuerzo positivo', '2026-10-28', 2, 'Media')],
    disponibilidadConTotal(0),
    $fechaSemana
);
comprobar(
    $sinHoras['estado_indice'] === 'sin_disponibilidad'
        && $sinHoras['porcentaje'] === null
        && str_contains($sinHoras['mensaje'], 'No hay horas disponibles'),
    'Disponibilidad cero y esfuerzo positivo: no divide entre cero'
);

$sinCarga = calcularIndiceCargaSemanal(
    [actividadPrueba('Esfuerzo cero', '2026-10-28', 0, 'Baja')],
    disponibilidadConTotal(0),
    $fechaSemana
);
comprobar(
    $sinCarga['estado_indice'] === 'sin_carga'
        && $sinCarga['porcentaje'] === null
        && $sinCarga['mensaje'] === 'Sin carga pendiente',
    'Disponibilidad y esfuerzo cero: Sin carga pendiente'
);

$disponibilidadIncompleta = disponibilidadConTotal(10);
$disponibilidadIncompleta[7] = null;
$incompleto = calcularIndiceCargaSemanal(
    $actividadOficial,
    $disponibilidadIncompleta,
    $fechaSemana
);
comprobar(
    !$incompleto['disponibilidad_completa']
        && $incompleto['porcentaje'] === null
        && $incompleto['dias_sin_configurar'] === ['Domingo'],
    'Disponibilidad incompleta: bloquea índice e identifica el día faltante'
);

$estados = calcularIndiceCargaSemanal(
    [
        actividadPrueba('Completada', '2026-10-28', 4, 'Alta', 'Completada'),
        actividadPrueba('En progreso', '2026-10-28', 4, 'Alta', 'En progreso'),
        actividadPrueba('Pendiente', '2026-10-28', 2, 'Baja'),
    ],
    disponibilidadConTotal(10),
    $fechaSemana
);
comprobar(
    count($estados['actividades_incluidas']) === 1
        && count($estados['actividades_excluidas']) === 2
        && $estados['esfuerzo_ponderado_total'] === 2.0,
    'Solo estado Pendiente contribuye; Completada y En progreso se excluyen'
);

$datosInvalidos = calcularIndiceCargaSemanal(
    [
        actividadPrueba('Sin fecha', null, 2, 'Baja'),
        actividadPrueba('Fecha inválida', '2026-02-30', 2, 'Baja'),
        actividadPrueba('Sin horas', '2026-10-28', null, 'Baja'),
        actividadPrueba('Horas negativas', '2026-10-28', -1, 'Baja'),
        actividadPrueba('Sin dificultad', '2026-10-28', 2, null),
        actividadPrueba('Fuera de semana', '2026-11-02', 2, 'Alta'),
    ],
    disponibilidadConTotal(10),
    $fechaSemana
);
comprobar(
    count($datosInvalidos['actividades_incluidas']) === 0
        && count($datosInvalidos['actividades_excluidas']) === 6,
    'Fecha, horas, dificultad inválidas y fecha fuera de semana se excluyen'
);
comprobar(
    str_contains(implode(' ', $datosInvalidos['actividades_excluidas'][0]['motivos_exclusion']), 'fecha'),
    'Las exclusiones incluyen el motivo correspondiente'
);

$semanaDiferente = calcularIndiceCargaSemanal(
    [
        actividadPrueba('Esta semana', '2026-10-28', 3, 'Media'),
        actividadPrueba('Siguiente semana', '2026-11-02', 8, 'Alta'),
    ],
    disponibilidadConTotal(10),
    $fechaSemana
);
comprobar(
    count($semanaDiferente['actividades_incluidas']) === 1
        && $semanaDiferente['actividades_incluidas'][0]['titulo'] === 'Esta semana',
    'Solo entran actividades con fecha límite en la semana consultada'
);

$conPrioridadBaja = calcularIndiceCargaSemanal(
    [actividadPrueba('Prioridad', '2026-10-28', 4, 'Media', 'Pendiente', 'Baja')],
    disponibilidadConTotal(10),
    $fechaSemana
);
$conPrioridadAlta = calcularIndiceCargaSemanal(
    [actividadPrueba('Prioridad', '2026-10-28', 4, 'Media', 'Pendiente', 'Alta')],
    disponibilidadConTotal(10),
    $fechaSemana
);
comprobar(
    $conPrioridadBaja['porcentaje'] === $conPrioridadAlta['porcentaje']
        && $conPrioridadBaja['esfuerzo_ponderado_total'] === $conPrioridadAlta['esfuerzo_ponderado_total'],
    'Cambiar prioridad no modifica esfuerzo ni índice'
);

echo "Pruebas completadas: {$pruebasEjecutadas}\n";