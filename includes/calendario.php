<?php
declare(strict_types=1);

function validarPeriodoCalendario(
    mixed $mesSolicitado,
    mixed $anioSolicitado,
    ?DateTimeImmutable $hoy = null
): array {
    if ($mesSolicitado === null && $anioSolicitado === null) {
        $hoy ??= new DateTimeImmutable('today');
        $mes = (int) $hoy->format('n');
        $anio = (int) $hoy->format('Y');
    } else {
        if (
            (!is_string($mesSolicitado) && !is_int($mesSolicitado)) ||
            (!is_string($anioSolicitado) && !is_int($anioSolicitado)) ||
            !preg_match('/^(?:[1-9]|1[0-2])$/', (string) $mesSolicitado) ||
            !preg_match('/^\d{4}$/', (string) $anioSolicitado)
        ) {
            throw new InvalidArgumentException('Mes o año no válido.');
        }

        $mes = (int) $mesSolicitado;
        $anio = (int) $anioSolicitado;
        if ($anio < 1000 || $anio > 9999) {
            throw new InvalidArgumentException('Año fuera del rango permitido.');
        }
    }

    $primerDia = DateTimeImmutable::createFromFormat('!Y-n-j', "{$anio}-{$mes}-1");
    if (!$primerDia) {
        throw new InvalidArgumentException('No se pudo interpretar el mes solicitado.');
    }

    $ultimoDia = $primerDia->modify('last day of this month');

    return [
        'anio' => $anio,
        'mes' => $mes,
        'primer_dia' => $primerDia,
        'ultimo_dia' => $ultimoDia,
        'dias_en_mes' => (int) $ultimoDia->format('j'),
    ];
}

function obtenerMesAdyacenteCalendario(int $anio, int $mes, int $desplazamiento): ?array
{
    if ($anio < 1000 || $anio > 9999 || $mes < 1 || $mes > 12 || !in_array($desplazamiento, [-1, 1], true)) {
        throw new InvalidArgumentException('Periodo o desplazamiento no válido.');
    }

    $primerDia = DateTimeImmutable::createFromFormat('!Y-n-j', "{$anio}-{$mes}-1");
    $mesAdyacente = $primerDia->modify($desplazamiento < 0 ? '-1 month' : '+1 month');
    $anioAdyacente = (int) $mesAdyacente->format('Y');

    if ($anioAdyacente < 1000 || $anioAdyacente > 9999) {
        return null;
    }

    return [
        'anio' => $anioAdyacente,
        'mes' => (int) $mesAdyacente->format('n'),
    ];
}

function generarCuadriculaCalendario(int $anio, int $mes): array
{
    $periodo = validarPeriodoCalendario($mes, $anio);
    $primerDia = $periodo['primer_dia'];
    $ultimoDia = $periodo['ultimo_dia'];
    $desfaseInicio = (int) $primerDia->format('N') - 1;
    $desfaseFin = 7 - (int) $ultimoDia->format('N');
    $inicioCuadricula = $primerDia->modify("-{$desfaseInicio} days");
    $finCuadricula = $ultimoDia->modify("+{$desfaseFin} days");
    $dias = [];

    for ($fecha = $inicioCuadricula; $fecha <= $finCuadricula; $fecha = $fecha->modify('+1 day')) {
        $dias[] = [
            'fecha' => $fecha,
            'fecha_iso' => $fecha->format('Y-m-d'),
            'dia_mes' => (int) $fecha->format('j'),
            'es_mes_actual' => $fecha->format('Y-m') === $primerDia->format('Y-m'),
        ];
    }

    return $dias;
}