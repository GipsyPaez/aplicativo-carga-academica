<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
exigirAutenticacion();


$nombreAplicacion = 'Carga Académica';
$fechaActual = date('d/m/Y');

require_once __DIR__ . '/config/database.php';

$usuarioId = (int) $_SESSION['usuario_id'];

// Contar las asignaturas del usuario.
$consulta = $pdo->prepare(
    'SELECT COUNT(*)
     FROM asignaturas
     WHERE usuario_id = :usuario_id'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$totalAsignaturas = (int) $consulta->fetchColumn();

// Contar las actividades pendientes.
$consulta = $pdo->prepare(
    "SELECT COUNT(*)
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE s.usuario_id = :usuario_id
     AND a.estado = 'Pendiente'"
);
$consulta->execute(['usuario_id' => $usuarioId]);
$totalPendientes = (int) $consulta->fetchColumn();

// Contar los exámenes próximos, sin incluir los ya vencidos.
$consulta = $pdo->prepare(
    "SELECT COUNT(*)
     FROM actividades a
     INNER JOIN asignaturas s ON s.id = a.asignatura_id
     WHERE s.usuario_id = :usuario_id
     AND a.tipo = 'Examen'
     AND a.fecha_entrega >= CURDATE()
     AND a.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     AND a.estado <> 'Completada'"
);
$consulta->execute(['usuario_id' => $usuarioId]);
$totalExamenes = (int) $consulta->fetchColumn();

// Sumar las horas semanales de las asignaturas.
$consulta = $pdo->prepare(
    'SELECT COALESCE(SUM(horas_semanales), 0)
     FROM asignaturas
     WHERE usuario_id = :usuario_id'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$totalHoras = (float) $consulta->fetchColumn();

$consulta = $pdo->prepare(
        'SELECT nombre, codigo, horas_semanales
         FROM asignaturas
         WHERE usuario_id = :usuario_id
         ORDER BY nombre
         LIMIT 4'
);
$consulta->execute(['usuario_id' => $usuarioId]);
$asignaturasRecientes = $consulta->fetchAll();

$consulta = $pdo->prepare(
        "SELECT a.titulo, a.tipo, a.fecha_entrega, a.estado,
                        s.nombre AS asignatura
         FROM actividades a
         INNER JOIN asignaturas s ON s.id = a.asignatura_id
         WHERE s.usuario_id = :usuario_id
             AND a.estado <> 'Completada'
         ORDER BY
             CASE WHEN a.fecha_entrega IS NULL THEN 1 ELSE 0 END,
             a.fecha_entrega ASC,
             a.id DESC
         LIMIT 5"
);
$consulta->execute(['usuario_id' => $usuarioId]);
$actividadesProximas = $consulta->fetchAll();

$estadisticas = [
    ['titulo' => 'Asignaturas', 'valor' => $totalAsignaturas, 'icono' => '📚'],
    ['titulo' => 'Actividades pendientes', 'valor' => $totalPendientes, 'icono' => '📝'],
    ['titulo' => 'Exámenes próximos', 'valor' => $totalExamenes, 'icono' => '📅'],
    ['titulo' => 'Horas semanales', 'valor' => number_format($totalHoras, 1, ',', '.') . ' h', 'icono' => '⏱️'],
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nombreAplicacion) ?></title>

    <style>
        :root {
            --fondo: #f4f6fb;
            --blanco: #ffffff;
            --primario: #5146e5;
            --texto: #20243a;
            --secundario: #747b91;
            --borde: #e8eaf2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--fondo);
            color: var(--texto);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 245px;
            background: #191b35;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 19px;
            font-weight: 750;
            margin: 4px 8px 42px;
        }

        .brand-icon {
            background: var(--primario);
            border-radius: 12px;
            padding: 10px;
        }

        .menu-label {
            color: #969bb8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.3px;
            margin: 25px 10px 12px;
        }

        .nav-link {
            display: block;
            padding: 13px 12px;
            margin: 5px 0;
            color: #d8daeb;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
        }

        .nav-link.active,
        .nav-link:hover {
            background: #343458;
            color: white;
        }

        main {
            flex: 1;
            min-width: 0;
            padding: 30px 36px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .topbar p {
            color: var(--secundario);
            margin: 6px 0 0;
            font-size: 14px;
        }

        .profile {
            background: var(--blanco);
            border: 1px solid var(--borde);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-actions form { margin: 0; }
        .top-actions .button { margin-top: 0; }

        h1 {
            font-size: 27px;
            margin: 0;
        }

        h2 {
            font-size: 18px;
            margin: 0 0 18px;
        }

        .welcome {
            background: linear-gradient(115deg, #5146e5, #7970f2);
            color: white;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 28px;
        }

        .welcome h2 {
            font-size: 23px;
            margin-bottom: 9px;
        }

        .welcome p {
            margin: 0;
            color: #eeedff;
            line-height: 1.6;
            max-width: 650px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .card, .panel {
            background: var(--blanco);
            border: 1px solid var(--borde);
            border-radius: 15px;
            padding: 21px;
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 18px;
        }

        .stat-title {
            color: var(--secundario);
            font-size: 13px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: 750;
            margin-top: 7px;
        }

        .columns {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 20px;
        }

        .panel p {
            color: var(--secundario);
            font-size: 14px;
            line-height: 1.6;
        }

        .empty {
            text-align: center;
            padding: 28px 12px;
            border: 1px dashed #d9dced;
            border-radius: 12px;
        }

        .summary-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .summary-list li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--borde);
        }

        .summary-list li:last-child { border-bottom: 0; }
        .summary-list small { color: var(--secundario); }

        .empty-icon {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .button {
            display: inline-block;
            background: var(--primario);
            color: white;
            text-decoration: none;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
            margin-top: 10px;
        }

        .button:hover {
            background: #4035cc;
        }

        footer {
            color: var(--secundario);
            font-size: 12px;
            text-align: center;
            padding: 30px 0 5px;
        }

        @media (max-width: 1050px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .brand {
                margin: 4px 8px 16px;
            }

            .menu-label {
                margin-top: 12px;
            }

            main {
                padding: 22px 16px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <span class="brand-icon">🎓</span>
            <span>Carga Académica</span>
        </div>

        <div class="menu-label">MENÚ PRINCIPAL</div>

        <a class="nav-link active" href="index.php">▦ &nbsp; Panel principal</a>
        <a class="nav-link" href="/carga-academica/asignaturas/">📚 &nbsp; Asignaturas</a>
        <a class="nav-link" href="/carga-academica/actividades/">📝 &nbsp; Actividades</a>
        <a class="nav-link" href="/carga-academica/disponibilidad/">⏱️ &nbsp; Disponibilidad semanal</a>
        <a class="nav-link" href="/carga-academica/indice/">📊 &nbsp; Índice semanal</a>
        <a class="nav-link" href="/carga-academica/calendario/">🗓️ &nbsp; Calendario</a>

        <div class="menu-label">PROYECTO</div>
        <a class="nav-link" href="#informacion">ⓘ &nbsp; Acerca del sistema</a>
    </aside>

    <main>
        <header class="topbar">
            <div>
                <h1>Panel principal</h1>
                <p>Organiza tus asignaturas y planifica tu tiempo de estudio.</p>
            </div>

            <div class="top-actions">
                <div class="profile">
                    <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars($fechaActual, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <form method="post" action="/carga-academica/auth/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCSRF(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </header>

        <section class="welcome">
            <h2>¡Bienvenida a tu espacio académico! 👋</h2>
            <p>
                Planifica tu semestre, controla las fechas de entrega
                y consulta la carga académica desde un único lugar.
            </p>
        </section>

        <section class="stats" aria-label="Resumen académico">
            <?php foreach ($estadisticas as $estadistica): ?>
                <article class="card">
                    <div class="stat-icon">
                        <?= $estadistica['icono'] ?>
                    </div>
                    <div class="stat-title">
                        <?= htmlspecialchars($estadistica['titulo']) ?>
                    </div>
                    <div class="stat-value">
                        <?= htmlspecialchars((string) $estadistica['valor']) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="columns">
            <section class="panel" id="asignaturas">
                <h2>Mis asignaturas</h2>

                <?php if ($asignaturasRecientes === []): ?>
                    <p>Aún no has registrado asignaturas.</p>
                <?php else: ?>
                    <ul class="summary-list">
                        <?php foreach ($asignaturasRecientes as $asignatura): ?>
                            <li>
                                <span>
                                    <strong><?= htmlspecialchars($asignatura['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <br><small><?= htmlspecialchars($asignatura['codigo'] ?? 'Sin código', ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                                <small><?= htmlspecialchars($asignatura['horas_semanales'], ENT_QUOTES, 'UTF-8') ?> h/semana</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="button" href="/carga-academica/asignaturas/">Abrir asignaturas</a>
            </section>

            <section class="panel" id="actividades">
                <h2>Actividades académicas</h2>

                <?php if ($actividadesProximas === []): ?>
                    <p>No tienes actividades pendientes.</p>
                <?php else: ?>
                    <ul class="summary-list">
                        <?php foreach ($actividadesProximas as $actividad): ?>
                            <li>
                                <span>
                                    <strong><?= htmlspecialchars($actividad['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <br><small><?= htmlspecialchars($actividad['asignatura'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($actividad['estado'], ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                                <small><?= $actividad['fecha_entrega'] ? htmlspecialchars(date('d/m/Y', strtotime($actividad['fecha_entrega'])), ENT_QUOTES, 'UTF-8') : 'Sin fecha' ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="button" href="/carga-academica/actividades/">Abrir actividades</a>
            </section>
        </div>

        <footer id="informacion">
            Aplicativo web para la planificación de la carga académica ·
            Prototipo funcional versión 0.1
        </footer>
    </main>

</div>
</body>
</html>
