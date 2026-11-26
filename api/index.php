<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/indice_carga.php';
require_once __DIR__ . '/../includes/calendario.php';

function respuesta(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function cuerpo(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) respuesta(400, ['error' => 'El cuerpo debe ser un objeto JSON válido.']);
    return $data;
}
function exigirUsuario(): int {
    if (!usuarioAutenticado()) respuesta(401, ['error' => 'Autenticación requerida.']);
    return (int) $_SESSION['usuario_id'];
}
function exigirCsrfApi(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        respuesta(419, ['error' => 'Token CSRF ausente o no válido. Recarga la aplicación.']);
    }
}
function texto(mixed $v, int $max, string $campo): string {
    if (!is_string($v)) respuesta(422, ['error' => "El campo {$campo} es obligatorio."]);
    $v = trim($v);
    if ($v === '' || mb_strlen($v) > $max) respuesta(422, ['error' => "El campo {$campo} está vacío o supera {$max} caracteres."]);
    return $v;
}
function numero(mixed $v, float $min, float $max, string $campo): float {
    if (!is_numeric($v) || !is_finite((float)$v) || (float)$v < $min || (float)$v > $max) respuesta(422, ['error' => "El campo {$campo} debe estar entre {$min} y {$max}."]);
    return (float)$v;
}
function fechaValida(mixed $v): ?string {
    if ($v === null || $v === '') return null;
    if (!is_string($v)) respuesta(422, ['error' => 'Fecha no válida.']);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) respuesta(422, ['error' => 'La fecha debe tener formato AAAA-MM-DD.']);
    return $v;
}
function inicioSemanaIso(string $week): ?DateTimeImmutable {
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) return null;
    $year=(int)$m[1]; $number=(int)$m[2];
    if ($year < 1000 || $year > 9999 || $number < 1 || $number > 53) return null;
    $jan4=DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-01-04', $year));
    if (!$jan4) return null;
    $monday=$jan4->modify('monday this week')->modify('+' . ($number-1) . ' weeks');
    return $monday->format('o-\WW') === $week ? $monday : null;
}
function perteneceAsignatura(PDO $pdo, int $id, int $uid): bool {
    $q=$pdo->prepare('SELECT id FROM asignaturas WHERE id=? AND usuario_id=?'); $q->execute([$id,$uid]); return (bool)$q->fetchColumn();
}
function actividadPayload(array $d, bool $permitirTipoAnterior = false): array {
    $tipos=['Trabajo','Examen','Proyecto','Tarea']; $niveles=['Baja','Media','Alta']; $estados=['Pendiente','En progreso','Completada'];
    $tipo=$d['tipo']??''; $dif=$d['dificultad']??''; $prio=$d['prioridad']??''; $estado=$d['estado']??'Pendiente';
    if (!in_array($tipo,$tipos,true) && !($permitirTipoAnterior && in_array($tipo,['Lectura','Otro'],true))) respuesta(422,['error'=>'Tipo de actividad no válido.']);
    if (!in_array($dif,$niveles,true) || !in_array($prio,$niveles,true)) respuesta(422,['error'=>'Dificultad y prioridad deben ser Baja, Media o Alta.']);
    if (!in_array($estado,$estados,true)) respuesta(422,['error'=>'Estado no válido.']);
    return [texto($d['titulo']??null,150,'título'), (isset($d['descripcion']) && is_string($d['descripcion']) ? trim($d['descripcion']) : ''), $tipo, fechaValida($d['fecha_entrega']??null), numero($d['horas_estimadas']??null,0,9999,'horas estimadas'),$dif,$prio,$estado];
}

$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
$base=strpos($path,'/api/')!==false ? substr($path,strpos($path,'/api/')+5) : '';
$segments=array_values(array_filter(explode('/',trim($base,'/')),'strlen'));
$resource=$segments[0]??''; $id=isset($segments[1])&&ctype_digit($segments[1])?(int)$segments[1]:null;
$method=$_SERVER['REQUEST_METHOD']??'GET';

try {
  if ($resource==='auth' && ($segments[1]??'')==='csrf' && $method==='GET') respuesta(200,['csrf_token'=>tokenCSRF()]);
  if ($resource==='auth' && ($segments[1]??'')==='register' && $method==='POST') {
    exigirCsrfApi(); $d=cuerpo(); $nombre=texto($d['nombre']??null,100,'nombre'); $email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL); $pass=$d['password']??'';
    if (!$email || !is_string($pass) || strlen($pass)<10) respuesta(422,['error'=>'Introduce un correo válido y una contraseña de al menos 10 caracteres.']);
    $q=$pdo->prepare('INSERT INTO usuarios(nombre,email,password) VALUES(?,?,?)');
    try {$q->execute([$nombre,$email,password_hash($pass,PASSWORD_DEFAULT)]);} catch(PDOException $e){ if((string)$e->getCode()==='23000') respuesta(409,['error'=>'Ya existe una cuenta con ese correo.']); throw $e; }
    session_regenerate_id(true); $_SESSION['usuario_id']=(int)$pdo->lastInsertId(); $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    respuesta(201,['user'=>['id'=>$_SESSION['usuario_id'],'nombre'=>$nombre,'email'=>$email],'csrf_token'=>$_SESSION['csrf_token']]);
  }
  if ($resource==='auth' && ($segments[1]??'')==='login' && $method==='POST') {
    exigirCsrfApi(); $d=cuerpo(); $email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL); $pass=$d['password']??'';
    if(!$email||!is_string($pass)) respuesta(422,['error'=>'Credenciales no válidas.']);
    $q=$pdo->prepare('SELECT id,nombre,email,password FROM usuarios WHERE email=?'); $q->execute([$email]); $u=$q->fetch();
    if(!$u||!password_verify($pass,$u['password'])) respuesta(401,['error'=>'Correo o contraseña incorrectos.']);
    session_regenerate_id(true); $_SESSION['usuario_id']=(int)$u['id']; $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    respuesta(200,['user'=>['id'=>(int)$u['id'],'nombre'=>$u['nombre'],'email'=>$u['email']],'csrf_token'=>$_SESSION['csrf_token']]);
  }
  if ($resource==='auth' && ($segments[1]??'')==='me' && $method==='GET') {
    $uid=exigirUsuario(); $q=$pdo->prepare('SELECT id,nombre,email FROM usuarios WHERE id=?');$q->execute([$uid]);respuesta(200,['user'=>$q->fetch(),'csrf_token'=>tokenCSRF()]);
  }
  if ($resource==='auth' && ($segments[1]??'')==='logout' && $method==='POST') { exigirUsuario(); exigirCsrfApi(); $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);} session_destroy(); respuesta(200,['message'=>'Sesión cerrada.']); }

  $uid=exigirUsuario();
  if ($resource==='subjects' && $method==='GET') { $q=$pdo->prepare('SELECT id,nombre,codigo,horas_semanales,creado_en FROM asignaturas WHERE usuario_id=? ORDER BY nombre');$q->execute([$uid]);respuesta(200,['data'=>$q->fetchAll()]); }
  if ($resource==='subjects' && $method==='POST') { exigirCsrfApi();$d=cuerpo();$nombre=texto($d['nombre']??null,120,'nombre');$codigo=isset($d['codigo'])&&$d['codigo']!==''?texto($d['codigo'],30,'código'):null;$horas=numero($d['horas_semanales']??0,0,999.9,'horas semanales');$q=$pdo->prepare('INSERT INTO asignaturas(usuario_id,nombre,codigo,horas_semanales) VALUES(?,?,?,?)');$q->execute([$uid,$nombre,$codigo,$horas]);respuesta(201,['id'=>(int)$pdo->lastInsertId(),'message'=>'Asignatura creada.']); }
  if ($resource==='subjects' && $id!==null && $method==='PUT') { exigirCsrfApi();$d=cuerpo();$nombre=texto($d['nombre']??null,120,'nombre');$codigo=isset($d['codigo'])&&$d['codigo']!==''?texto($d['codigo'],30,'código'):null;$horas=numero($d['horas_semanales']??0,0,999.9,'horas semanales');$q=$pdo->prepare('UPDATE asignaturas SET nombre=?,codigo=?,horas_semanales=? WHERE id=? AND usuario_id=?');$q->execute([$nombre,$codigo,$horas,$id,$uid]);if(!$q->rowCount()){$c=$pdo->prepare('SELECT id FROM asignaturas WHERE id=? AND usuario_id=?');$c->execute([$id,$uid]);if(!$c->fetchColumn())respuesta(404,['error'=>'Asignatura no encontrada.']);}respuesta(200,['message'=>'Asignatura actualizada.']); }
  if ($resource==='subjects' && $id!==null && $method==='DELETE') { exigirCsrfApi();$q=$pdo->prepare('DELETE FROM asignaturas WHERE id=? AND usuario_id=?');$q->execute([$id,$uid]);if(!$q->rowCount())respuesta(404,['error'=>'Asignatura no encontrada.']);respuesta(200,['message'=>'Asignatura eliminada.']); }

  if ($resource==='activities' && $method==='GET') { $sql='SELECT a.id,a.asignatura_id,a.titulo,a.descripcion,a.tipo,a.fecha_entrega,a.horas_estimadas,a.dificultad,a.prioridad,a.estado,s.nombre AS asignatura FROM actividades a JOIN asignaturas s ON s.id=a.asignatura_id WHERE s.usuario_id=?';$params=[$uid];if(isset($_GET['week'])&&preg_match('/^\d{4}-W\d{2}$/',$_GET['week'])){$dt=inicioSemanaIso($_GET['week']);if($dt){$end=$dt->modify('+6 days');$sql.=' AND a.fecha_entrega BETWEEN ? AND ?';$params[]=$dt->format('Y-m-d');$params[]=$end->format('Y-m-d');}}$sql.=' ORDER BY a.fecha_entrega IS NULL,a.fecha_entrega,a.id DESC';$q=$pdo->prepare($sql);$q->execute($params);respuesta(200,['data'=>$q->fetchAll()]); }
  if ($resource==='activities' && $method==='POST') { exigirCsrfApi();$d=cuerpo();$sid=(int)($d['asignatura_id']??0);if($sid<1||!perteneceAsignatura($pdo,$sid,$uid))respuesta(422,['error'=>'Selecciona una asignatura propia.']);[$t,$desc,$tipo,$fecha,$horas,$dif,$prio,$estado]=actividadPayload($d);$q=$pdo->prepare('INSERT INTO actividades(asignatura_id,titulo,descripcion,tipo,fecha_entrega,horas_estimadas,dificultad,prioridad,estado) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$sid,$t,$desc,$tipo,$fecha,$horas,$dif,$prio,$estado]);respuesta(201,['id'=>(int)$pdo->lastInsertId(),'message'=>'Actividad creada.']); }
  if ($resource==='activities' && $id!==null && $method==='PUT') { exigirCsrfApi();$d=cuerpo();$sid=(int)($d['asignatura_id']??0);if($sid<1||!perteneceAsignatura($pdo,$sid,$uid))respuesta(422,['error'=>'Selecciona una asignatura propia.']);[$t,$desc,$tipo,$fecha,$horas,$dif,$prio,$estado]=actividadPayload($d,true);$q=$pdo->prepare('UPDATE actividades a JOIN asignaturas s ON s.id=a.asignatura_id SET a.asignatura_id=?,a.titulo=?,a.descripcion=?,a.tipo=?,a.fecha_entrega=?,a.horas_estimadas=?,a.dificultad=?,a.prioridad=?,a.estado=? WHERE a.id=? AND s.usuario_id=?');$q->execute([$sid,$t,$desc,$tipo,$fecha,$horas,$dif,$prio,$estado,$id,$uid]);if(!$q->rowCount()){$c=$pdo->prepare('SELECT a.id FROM actividades a JOIN asignaturas s ON s.id=a.asignatura_id WHERE a.id=? AND s.usuario_id=?');$c->execute([$id,$uid]);if(!$c->fetchColumn())respuesta(404,['error'=>'Actividad no encontrada.']);}respuesta(200,['message'=>'Actividad actualizada.']); }
  if ($resource==='activities' && $id!==null && $method==='DELETE') { exigirCsrfApi();$q=$pdo->prepare('DELETE a FROM actividades a JOIN asignaturas s ON s.id=a.asignatura_id WHERE a.id=? AND s.usuario_id=?');$q->execute([$id,$uid]);if(!$q->rowCount())respuesta(404,['error'=>'Actividad no encontrada.']);respuesta(200,['message'=>'Actividad eliminada.']); }

  if ($resource==='availability' && $method==='GET') { $q=$pdo->prepare('SELECT dia_semana,horas_disponibles FROM disponibilidad_semanal WHERE usuario_id=? ORDER BY dia_semana');$q->execute([$uid]);$rows=$q->fetchAll();$out=[];for($i=1;$i<=7;$i++)$out[$i]=null;foreach($rows as $r)$out[(int)$r['dia_semana']]=$r['horas_disponibles']===null?null:(float)$r['horas_disponibles'];respuesta(200,['data'=>$out]); }
  if ($resource==='availability' && $method==='PUT') { exigirCsrfApi();$d=cuerpo();$days=$d['days']??null;if(!is_array($days)||count($days)!==7)respuesta(422,['error'=>'Debes enviar los siete días (1 a 7).']);$pdo->beginTransaction();$q=$pdo->prepare('INSERT INTO disponibilidad_semanal(usuario_id,dia_semana,horas_disponibles) VALUES(?,?,?) ON DUPLICATE KEY UPDATE horas_disponibles=VALUES(horas_disponibles)');for($i=1;$i<=7;$i++){if(!array_key_exists((string)$i,$days)&&!array_key_exists($i,$days))respuesta(422,['error'=>'Falta un día de la semana.']);$v=$days[$i]??$days[(string)$i];$v=($v===null||$v==='')?null:numero($v,0,999.99,'horas disponibles');$q->execute([$uid,$i,$v]);}$pdo->commit();respuesta(200,['message'=>'Disponibilidad guardada.']); }

  if ($resource==='weekly-index' && $method==='GET') { $week=$_GET['week']??(new DateTimeImmutable())->format('o-\WW');if(!is_string($week)||!preg_match('/^(\d{4})-W(\d{2})$/',$week,$m)||!checkdate(1,1,(int)$m[1])||(int)$m[2]<1||(int)$m[2]>53)respuesta(400,['error'=>'Semana ISO no válida.']);$start=inicioSemanaIso($week);if(!$start)respuesta(400,['error'=>'Semana ISO no válida.']);$q=$pdo->prepare('SELECT a.id,a.titulo,a.fecha_entrega,a.horas_estimadas,a.dificultad,a.prioridad,a.estado,s.nombre AS asignatura FROM actividades a JOIN asignaturas s ON s.id=a.asignatura_id WHERE s.usuario_id=? AND a.fecha_entrega BETWEEN ? AND ? ORDER BY a.fecha_entrega');$q->execute([$uid,$start->format('Y-m-d'),$start->modify('+6 days')->format('Y-m-d')]);$acts=$q->fetchAll();$q=$pdo->prepare('SELECT dia_semana,horas_disponibles FROM disponibilidad_semanal WHERE usuario_id=?');$q->execute([$uid]);$avail=[];foreach($q->fetchAll() as $r)$avail[(int)$r['dia_semana']]=$r['horas_disponibles'];$result=calcularIndiceCargaSemanal($acts,$avail,$start);$result['inicio_semana']=$result['inicio_semana']->format('Y-m-d');$result['fin_semana']=$result['fin_semana']->format('Y-m-d');respuesta(200,$result); }

  if ($resource==='calendar' && $method==='GET') {try{$p=validarPeriodoCalendario($_GET['month']??null,$_GET['year']??null);}catch(InvalidArgumentException $e){respuesta(400,['error'=>$e->getMessage()]);}$q=$pdo->prepare('SELECT a.id,a.titulo,a.fecha_entrega,a.estado,s.nombre AS asignatura FROM actividades a JOIN asignaturas s ON s.id=a.asignatura_id WHERE s.usuario_id=? AND a.fecha_entrega BETWEEN ? AND ? ORDER BY a.fecha_entrega,a.id');$q->execute([$uid,$p['primer_dia']->format('Y-m-d'),$p['ultimo_dia']->format('Y-m-d')]);respuesta(200,['month'=>$p['mes'],'year'=>$p['anio'],'data'=>$q->fetchAll()]); }
  respuesta(404,['error'=>'Ruta API no encontrada.']);
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); error_log((string)$e); respuesta(500,['error'=>'Error interno del servidor.']); }
