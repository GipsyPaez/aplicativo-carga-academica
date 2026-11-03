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
$usuarioId = (int) $_SESSION['usuario_id'];

if (!$actividadId) {
    http_response_code(400);
    exit('Identificador de actividad no válido.');
}

$consulta = $pdo->prepare(
    'DELETE a
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE a.id = :id
     AND s.usuario_id = :usuario_id'
);

$consulta->execute([
    'id' => $actividadId,
    'usuario_id' => $usuarioId
]);

header('Location: /carga-academica/actividades/');
exit;
