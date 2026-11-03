<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /carga-academica/actividades/');
    exit;
}

exigirTokenCSRF();

$actividadId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nuevoEstado = $_POST['estado'] ?? '';
$usuarioId = (int) $_SESSION['usuario_id'];

$estadosPermitidos = [
    'Pendiente',
    'En progreso',
    'Completada'
];

if (
    !$actividadId ||
    !in_array($nuevoEstado, $estadosPermitidos, true)
) {
    http_response_code(400);
    exit('Datos de actividad no válidos.');
}

$consulta = $pdo->prepare(
    'UPDATE actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     SET a.estado = :estado
     WHERE a.id = :id
     AND s.usuario_id = :usuario_id'
);

$consulta->execute([
    'estado' => $nuevoEstado,
    'id' => $actividadId,
    'usuario_id' => $usuarioId
]);

header('Location: /carga-academica/actividades/');
exit;
