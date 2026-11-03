<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

exigirAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /carga-academica/asignaturas/');
    exit;
}

exigirTokenCSRF();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$usuarioId = (int) $_SESSION['usuario_id'];

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador no válido.');
}

$consulta = $pdo->prepare(
    'DELETE FROM asignaturas
     WHERE id = :id AND usuario_id = :usuario_id'
);

$consulta->execute([
    'id' => $id,
    'usuario_id' => $usuarioId,
]);

header('Location: /carga-academica/asignaturas/');
exit;
