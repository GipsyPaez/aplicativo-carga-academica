<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/calendario.php';

$pruebasEjecutadas = 0;

function comprobarCalendario(bool $condicion, string $nombre): void
{
    global $pruebasEjecutadas;
    $pruebasEjecutadas++;

    if (!$condicion) {
        throw new RuntimeException("FALLO: {$nombre}");
    }

    echo "OK: {$nombre}\n";
}

$casosMeses = [
    [2021, 2, 28],
    [2024, 2, 29],
    [2026, 4, 30],
    [2026, 12, 31],
];

foreach ($casosMeses as [$anio, $mes, $diasEsperados]) {
    $periodo = validarPeriodoCalendario($mes, $anio);
    comprobarCalendario(
        $periodo['dias_en_mes'] === $diasEsperados,
        "{$anio}-{$mes} tiene {$diasEsperados} días"
    );

    $cuadricula = generarCuadriculaCalendario($anio, $mes);
    comprobarCalendario(
        count($cuadricula) % 7 === 0
            && $cuadricula[0]['fecha']->format('N') === '1'
            && end($cuadricula)['fecha']->format('N') === '7'
            && count(array_filter($cuadricula, static fn(array $dia): bool => $dia['es_mes_actual'])) === $diasEsperados,
        "Cuadrícula completa lunes-domingo para {$anio}-{$mes}"
    );
}

$diciembre = obtenerMesAdyacenteCalendario(2026, 12, 1);
$eneroPrevio = obtenerMesAdyacenteCalendario(2027, 1, -1);
comprobarCalendario($diciembre === ['anio' => 2027, 'mes' => 1], 'Navegación diciembre a enero');
comprobarCalendario($eneroPrevio === ['anio' => 2026, 'mes' => 12], 'Navegación enero a diciembre');

$mesActual = validarPeriodoCalendario(null, null, new DateTimeImmutable('2026-11-02'));
comprobarCalendario($mesActual['anio'] === 2026 && $mesActual['mes'] === 11, 'Mes actual como valor inicial');

foreach ([
    ['0', '2026'],
    ['13', '2026'],
    ['2', '999'],
    ['abc', '2026'],
    ['2', ['2026']],
] as [$mesInvalido, $anioInvalido]) {
    $rechazado = false;
    try {
        validarPeriodoCalendario($mesInvalido, $anioInvalido);
    } catch (InvalidArgumentException) {
        $rechazado = true;
    }
    comprobarCalendario($rechazado, 'Rechazo de parámetros de mes/año inválidos');
}

echo "Pruebas de calendario completadas: {$pruebasEjecutadas}\n";