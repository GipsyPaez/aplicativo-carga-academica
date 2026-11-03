<?php
declare(strict_types=1);

$configuracionLocal = __DIR__ . '/database.local.php';
$configuracion = is_file($configuracionLocal) ? require $configuracionLocal : [];
$configuracion = is_array($configuracion) ? $configuracion : [];

$host = getenv('DB_HOST') ?: ($configuracion['host'] ?? 'localhost');
$dbname = getenv('DB_NAME') ?: ($configuracion['dbname'] ?? 'carga_academica');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if ($username === false) {
    $username = $configuracion['username'] ?? false;
}

if ($password === false) {
    $password = $configuracion['password'] ?? false;
}

if (!is_string($username) || $username === '' || !is_string($password)) {
    http_response_code(500);
    exit('Configura DB_USER y DB_PASSWORD o crea config/database.local.php.');
}

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}
