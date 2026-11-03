<?php
declare(strict_types=1);

function rangoSemanaAcademica(DateTimeImmutable $fecha): array
{
    $inicio = $fecha->setTime(0, 0)->modify('monday this week');

    return [
        'inicio' => $inicio,
        'fin' => $inicio->modify('+6 days'),
    ];
}

function calcularIndiceCargaSemanal(
    array $actividades,
    array $disponibilidadPorDia,
    DateTimeImmutable $inicioSemana
): array {
    $dias = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
    $inicio = $inicioSemana->setTime(0, 0)->modify('monday this week');
    $fin = $inicio->modify('+6 days');
    $inicioTexto = $inicio->format('Y-m-d');
    $finTexto = $fin->format('Y-m-d');
    $factores = [
        'Baja' => 1.00,
        'Media' => 1.25,
        'Alta' => 1.50,
    ];
    $incluidas = [];
    $excluidas = [];
    $totalEsfuerzo = 0.0;

    foreach ($actividades as $actividad) {
        $motivos = [];
        $estado = $actividad['estado'] ?? null;

        if ($estado !== 'Pendiente') {
            $motivos[] = $estado === null
                ? 'Estado no válido'
                : "Estado {$estado}; solo se incluyen actividades Pendiente";
        }

        $fechaTexto = $actividad['fecha_entrega'] ?? null;
        $fechaValida = is_string($fechaTexto)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $fechaTexto)
            : false;

        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fechaTexto) {
            $motivos[] = 'Sin fecha límite válida';
        } elseif ($fechaTexto < $inicioTexto || $fechaTexto > $finTexto) {
            $motivos[] = 'Fecha fuera de la semana seleccionada';
        }

        $horas = $actividad['horas_estimadas'] ?? null;
        if (
            !is_numeric($horas) ||
            !is_finite((float) $horas) ||
            (float) $horas < 0
        ) {
            $motivos[] = 'Horas estimadas no válidas';
        }

        $dificultad = $actividad['dificultad'] ?? null;
        if (!array_key_exists($dificultad, $factores)) {
            $motivos[] = 'Dificultad sin clasificar o no válida';
        }

        if ($motivos !== []) {
            $actividad['motivos_exclusion'] = $motivos;
            $excluidas[] = $actividad;
            continue;
        }

        $factor = $factores[$dificultad];
        $esfuerzo = (float) $horas * $factor;
        $actividad['factor_dificultad'] = $factor;
        $actividad['esfuerzo_ponderado'] = $esfuerzo;
        $totalEsfuerzo += $esfuerzo;
        $incluidas[] = $actividad;
    }

    $horasDisponiblesParcial = 0.0;
    $diasSinConfigurar = [];
    $diasInvalidos = [];

    foreach ($dias as $numeroDia => $nombreDia) {
        $horas = $disponibilidadPorDia[$numeroDia] ?? null;

        if ($horas === null || $horas === '') {
            $diasSinConfigurar[] = $nombreDia;
            continue;
        }

        if (
            !is_numeric($horas) ||
            !is_finite((float) $horas) ||
            (float) $horas < 0 ||
            (float) $horas > 999.99
        ) {
            $diasInvalidos[] = $nombreDia;
            continue;
        }

        $horasDisponiblesParcial += (float) $horas;
    }

    $disponibilidadCompleta = $diasSinConfigurar === [] && $diasInvalidos === [];
    $horasDisponibles = $disponibilidadCompleta ? $horasDisponiblesParcial : null;
    $porcentaje = null;
    $clasificacion = null;
    $estadoIndice = 'disponibilidad_incompleta';
    $mensaje = 'Completa la disponibilidad de los siete días para calcular el índice.';

    if ($disponibilidadCompleta) {
        if ($horasDisponibles === 0.0 && $totalEsfuerzo > 0.0) {
            $estadoIndice = 'sin_disponibilidad';
            $mensaje = 'No hay horas disponibles declaradas para esta semana; no se puede calcular el porcentaje.';
        } elseif ($horasDisponibles === 0.0 && $totalEsfuerzo === 0.0) {
            $estadoIndice = 'sin_carga';
            $clasificacion = 'Sin carga pendiente';
            $mensaje = 'Sin carga pendiente';
        } else {
            $porcentaje = ($totalEsfuerzo / $horasDisponibles) * 100;
            $estadoIndice = 'calculado';

            if ($porcentaje < 80.0) {
                $clasificacion = 'Carga baja';
            } elseif ($porcentaje <= 100.0) {
                $clasificacion = 'Carga ajustada';
            } else {
                $clasificacion = 'Carga alta';
            }

            $mensaje = $clasificacion;
        }
    }

    return [
        'inicio_semana' => $inicio,
        'fin_semana' => $fin,
        'actividades_incluidas' => $incluidas,
        'actividades_excluidas' => $excluidas,
        'esfuerzo_ponderado_total' => $totalEsfuerzo,
        'disponibilidad_completa' => $disponibilidadCompleta,
        'dias_sin_configurar' => $diasSinConfigurar,
        'dias_invalidos' => $diasInvalidos,
        'horas_disponibles_parcial' => $horasDisponiblesParcial,
        'horas_disponibles' => $horasDisponibles,
        'porcentaje' => $porcentaje,
        'clasificacion' => $clasificacion,
        'estado_indice' => $estadoIndice,
        'mensaje' => $mensaje,
    ];
}