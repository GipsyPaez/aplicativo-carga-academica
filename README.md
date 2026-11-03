# Carga Académica

Aplicación web en PHP nativo para organizar asignaturas y actividades académicas. Usa MariaDB mediante PDO y no requiere frameworks.

## Requisitos

- PHP 8.0 o posterior con `pdo_mysql` y `mbstring`.
- Apache con PHP habilitado (o un servidor PHP equivalente).
- MariaDB 10.11 o posterior.
## Instalación


1. Coloca el proyecto dentro del directorio público de Apache.
2. Crea una base de datos MariaDB vacía llamada `carga_academica` y un usuario específico con permisos únicamente sobre ella.
3. Ejecuta el instalador desde la raíz del proyecto:

   ```bash
   mariadb -u USUARIO_BD -p carga_academica < database/install.sql
   ```

4. Copia `config/database.local.example.php` a `config/database.local.php` y configura el host, nombre de base de datos, usuario y contraseña. Ese archivo está excluido de Git. Alternativamente, configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` en el entorno de PHP/Apache.
5. Abre `/carga-academica/auth/registro.php` para crear una cuenta e inicia sesión.

No subas `config/database.local.php`, `.env` ni credenciales al repositorio. Usa secretos distintos para cada instalación.

## Esquema inicial

La estructura de la base de datos está definida en `database/install.sql`, que contiene las cuatro tablas necesarias:

- `usuarios`: cuentas de usuario.
- `asignaturas`: asignaturas asociadas a cada usuario.
- `actividades`: actividades académicas asociadas a cada asignatura.
- `disponibilidad_semanal`: horas disponibles por usuario y día de la semana.

El esquema utiliza InnoDB, codificación `utf8mb4` e intercalación `utf8mb4_unicode_ci`. Incluye claves primarias, claves foráneas, índices y restricciones de integridad.

**Importante:** el instalador está destinado exclusivamente a bases de datos nuevas y vacías. No es un script de migración ni de actualización. No lo ejecutes sobre instalaciones existentes o bases de datos con información que quieras conservar.

## Uso

- El registro y el inicio de sesión dan acceso al panel.
- Desde el panel se navega a asignaturas y actividades; los datos se filtran por usuario.
- Disponibilidad semanal permite declarar horas decimales de lunes a domingo. Deja un día vacío para mantenerlo sin declarar o introduce `0` para indicar cero horas.
- Índice semanal permite consultar una semana ISO y ver el esfuerzo ponderado, la disponibilidad, la clasificación y el desglose de actividades incluidas/excluidas.
- Calendario académico muestra actividades por fecha límite en una vista mensual, permite navegar entre meses y enlaza cada actividad a su edición.
- Las actividades permiten alta, edición, cambio de estado y eliminación. Los tipos de alta son Examen, Trabajo, Proyecto y Tarea; dificultad y prioridad aceptan Baja, Media y Alta.
- Al editar registros antiguos se conserva su tipo legacy. Los que aún tienen dificultad o prioridad `NULL` requieren completarlas para poder guardar.
- Las operaciones de escritura usan POST, sesión autenticada y token CSRF.

## Comprobaciones

Desde la raíz del proyecto:

```sh
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php test_db.php
php tests/indice_carga_test.php
php tests/calendario_test.php
```

`test_db.php` solo comprueba la conexión. La prueba de interfaz requiere iniciar sesión y recorrer los formularios; no ejecutes acciones de eliminación en una base con datos que deban conservarse.

### Verificación de adaptación de actividades

Probada en `carga_academica_test` con PHP 8.3 y MariaDB 10.11. Se comprobó por HTTP la creación de los cuatro tipos oficiales, el guardado y edición de dificultad/prioridad, el rechazo de tipo ausente o legacy y de clasificaciones vacías o inválidas, la visualización y clasificación de filas legacy `Lectura`/`Otro` con valores `NULL`, la conservación de `En progreso` y la denegación de edición cruzada entre usuarios (404 en GET y POST). Los registros de prueba se conservan en la base aislada; no se ejecutaron eliminaciones.

### Verificación de disponibilidad semanal

Probada en `carga_academica_test` con dos usuarios mediante HTTP. Se verificaron siete días inicializados como `NULL`, guardado y modificación de valores decimales y ceros, total semanal, día sin configurar, rechazo de negativos, texto, valores superiores a `999.99`, más de dos decimales y días faltantes sin cambios parciales. También se comprobó que un POST con `usuario_id` manipulado solo afecta al usuario autenticado y no duplica días. Los registros de prueba se conservan; no se ejecutaron eliminaciones.

### Índice semanal

La unidad de cálculo es la semana ISO, de lunes a domingo, elegida por el usuario. Solo contribuyen actividades con estado exactamente `Pendiente`, fecha dentro de la semana, horas válidas y dificultad clasificada. Las completadas, en progreso, sin fecha, fuera de semana o sin datos válidos se muestran excluidas con su motivo. La prioridad se conserva como dato, pero no interviene en la fórmula.

```text
Esfuerzo ponderado de actividad = horas estimadas × factor de dificultad
Índice semanal (%) = suma del esfuerzo ponderado pendiente / disponibilidad semanal × 100

Baja  = 1,00
Media = 1,25
Alta  = 1,50
```

La disponibilidad es la suma de los siete días. Si falta declarar algún día, el porcentaje no se calcula y se indican los días pendientes. Con cero horas disponibles y esfuerzo positivo se muestra un aviso sin porcentaje; con cero disponibilidad y cero esfuerzo se muestra `Sin carga pendiente`. La clasificación es menor de 80 %: `Carga baja`; de 80 % a 100 % inclusive: `Carga ajustada`; más de 100 %: `Carga alta`.

`php tests/indice_carga_test.php` ejecuta 15 pruebas sin dependencias externas: ejemplo oficial (8,5/10 = 85 %), valores inferiores y exactamente en 80 %/100 %, valor superior a 100 %, casos de disponibilidad cero e incompleta, filtros de estado/fecha/horas/dificultad, exclusión fuera de semana, prioridad sin efecto y semanas que cruzan mes/año. La ruta web se probó en `carga_academica_test` con disponibilidad incompleta, sin carga pendiente y semana ISO inválida.

### Calendario académico

La vista mensual abre por defecto el mes actual y permite ir al anterior/siguiente o seleccionar mes y año. La semana visual empieza en lunes. Se consultan únicamente actividades con fecha límite dentro del mes y asignaturas propiedad del usuario autenticado; no se muestran actividades sin fecha en una celda. Cada evento muestra título, asignatura y estado, tiene un estilo por estado y enlaza a la edición existente, que vuelve a validar la propiedad.

Probado en `carga_academica_test`: mes actual, noviembre/diciembre 2026, diciembre-enero en ambos sentidos, varios estados el mismo día, actividades en el límite entre meses, mes vacío, usuario sin actividades, acceso a edición del propietario y rechazo de edición ajena (404), y parámetros inválidos (400). `php tests/calendario_test.php` cubre meses de 28, 29, 30 y 31 días, cuadrículas lunes-domingo, navegación de año y validación de periodo. No se implementaron funcionalidades de agenda, calendario académico institucional ni edición directa desde la celda.

## Auditoría de aceptación CA-01–CA-12

Auditoría funcional/técnica ejecutada el 2 de noviembre de 2026 en PHP 8.3 y MariaDB 10.11. Todas las pruebas con datos se ejecutaron con `DATABASE() = carga_academica_test` y `CURRENT_USER() = carga_test@localhost`. No se ejecutó DDL sobre ninguna base. Se conservaron los registros previos; la prueba CRUD eliminó únicamente una asignatura temporal creada por esa misma prueba.

**Resultado de aceptación:** CA-01 a CA-12 aprobados después de aplicar y repetir la corrección de entradas malformadas. No quedan criterios críticos fallidos ni no verificables. Las suites de cálculo/calendario son automatizadas; los flujos de integración HTTP de esta auditoría se ejecutaron manualmente.

| Criterio | Resultado esperado | Resultado obtenido | Estado | Evidencia y observaciones |
|---|---|---|---|---|
| CA-01 | Registrar cuenta e iniciar sesión con credenciales válidas; rechazar las inválidas. | A/B registrados; login correcto; login inválido mostró error; password almacenado distinto del texto original. | Aprobado | Pruebas HTTP en `auth/registro.php` y `auth/login.php`; hash/verificación revisados. |
| CA-02 | CRUD de asignaturas propias. | Alta, listado y edición persistieron; se eliminó una asignatura temporal propia y se verificó su ausencia. | Aprobado | Pruebas HTTP en `asignaturas/`; las asignaturas previas se conservaron. |
| CA-03 | Actividad con tipo, título, asignatura, fecha, horas, dificultad, prioridad y estado. | Se crearon los cuatro tipos oficiales con campos completos; edición y estados se comprobaron. | Aprobado | Datos inspeccionados en `carga_academica_test`; formularios y endpoints de actividades. |
| CA-04 | Rechazar horas negativas, obligatorios vacíos y valores fuera de rango. | Rechazó título/clasificación vacíos, horas negativas y `10000`, fecha imposible, tipo legacy, niveles inválidos y campos array; no insertó filas. | Aprobado | POST de altas devolvió formulario con error; conteo antes/después igual. Los arrays provocaron 500 antes de la corrección; se repitieron después y devolvieron 200 con validación. |
| CA-05 | Calendario en fecha límite y estados distinguibles. | Noviembre mostró Pendiente, En progreso y Completada; múltiples eventos el mismo día y fechas de límite de mes correctas. | Aprobado | Fixtures `CAL-FASE4-*`; GET mensual y clases/etiquetas de estado. |
| CA-06 | Registrar/modificar disponibilidad diaria y sumar total semanal. | Siete días guardados; suma manual de 10 h verificada con 10 el lunes y 0 los otros días. | Aprobado | Módulo disponibilidad y consulta de filas de usuario auditor. |
| CA-07 | Solo Pendiente dentro de la semana y factor de dificultad aprobado. | 4 h Baja + 3 h Alta produjo 8,5 h ponderadas y 85 % con 10 h disponibles; estados no pendientes excluidos. | Aprobado | `tests/indice_carga_test.php` y pantalla HTTP `indice/?semana=2026-W45`. |
| CA-08 | Clasificar límites de 80 % y 100 %. | 79,9 % Carga baja; 80 % y 100 % Carga ajustada; más de 100 % Carga alta. | Aprobado | Suite de índice: 7,99/10, 8/10, 10/10 y 10,01/10. |
| CA-09 | Disponibilidad cero sin división por cero y mensaje definido. | Cero disponibilidad con esfuerzo positivo no da porcentaje; cero con cero esfuerzo muestra “Sin carga pendiente”. | Aprobado | Casos unitarios en `tests/indice_carga_test.php`. |
| CA-10 | No leer, editar ni eliminar datos de otra cuenta. | Usuario B no vio eventos de A; edición ajena dio 404; cambios/borrados cruzados no alteraron filas. | Aprobado | Prueba cruzada con dos cuentas y consultas posteriores de solo lectura. |
| CA-11 | Datos persisten tras cerrar sesión y volver a entrar. | Logout redirigió; dashboard sin sesión redirigió al login; después del login persistieron asignatura y actividades. | Aprobado | Flujo HTTP logout/login y conteos de filas del usuario auditor. |
| CA-12 | Completar login, asignatura, actividad, calendario e índice sin errores bloqueantes. | Flujo secuencial completo respondió correctamente y el índice mostró 85 % ajustado. | Aprobado | Peticiones HTTP autenticadas para calendario y semana `2026-W45`; suites de regresión aprobadas. |

### Seguridad, regresión y correcciones

- Lint ejecutado en todos los PHP: sin errores. Revisión estática: sin errores reportados.
- Consultas de aplicación revisadas con PDO preparado y filtros de propiedad por usuario; contraseñas usan `password_hash()`/`password_verify()`; operaciones POST validan CSRF; cookies de sesión usan HttpOnly y SameSite.
- Se detectaron cuatro TypeError HTTP 500 al enviar arrays a campos escalares en registro, login, alta de asignatura y edición de asignatura. Se añadieron guardas `is_string()` en esos campos y se repitieron las solicitudes: ahora responden 200 con errores de validación, sin modificar filas.
- No quedaron errores PHP/SQL en las solicitudes válidas finales. Los errores iniciales del servidor correspondían únicamente a esas entradas malformadas previas a la corrección.
- Pruebas de regresión: `php tests/indice_carga_test.php` (15 aprobadas) y `php tests/calendario_test.php` (16 aprobadas).

### Limitaciones conocidas

- La evidencia usa cuentas y fixtures sintéticos de la base aislada; no representa datos reales de usuarios.
- Las pruebas de interacción HTTP son una ejecución de auditoría, no una suite automatizada de navegador mantenida en el repositorio.
- Los eventos sin fecha no aparecen en una celda mensual; continúan visibles en el módulo de actividades.
- El cálculo es orientativo y depende de la información introducida por el usuario; no constituye una predicción académica.

### Ejecutar pruebas locales

Configura las variables de entorno para que PHP use la base de pruebas; no guardes la contraseña en el repositorio:

```sh
export DB_HOST=localhost
export DB_NAME=carga_academica_test
export DB_USER=carga_test
export DB_PASSWORD='<secreto local>'
php -S 127.0.0.1:8770 -t /var/www/html
```

En otra terminal, desde la raíz del proyecto:

```sh
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/indice_carga_test.php
php tests/calendario_test.php
```

Antes de ejecutar pruebas con escrituras, confirma que `SELECT DATABASE()` sea `carga_academica_test` y `SELECT CURRENT_USER()` sea `carga_test@localhost`. No utilices estas instrucciones con `carga_academica`.

## Prueba funcional aislada

No ejecutes este procedimiento contra `carga_academica`. El entorno aislado preparado es `carga_academica_test`, con el usuario MariaDB dedicado `carga_test`, limitado a esa base. No guardes su contraseña en este repositorio.

1. La base y el usuario deben aprovisionarse fuera de esta aplicación, con permisos limitados a `carga_academica_test.*`. Las tablas del esquema inicial ya están creadas en esa base.
2. Configura el proceso PHP de pruebas con `DB_HOST=localhost`, `DB_NAME=carga_academica_test`, `DB_USER=carga_test` y `DB_PASSWORD` suministrada de forma segura fuera del repositorio. Las variables de entorno tienen prioridad sobre `config/database.local.php`. Antes de probar, exige que `SELECT DATABASE()` devuelva `carga_academica_test` y que `SELECT CURRENT_USER()` identifique `carga_test@localhost`.
3. Registra dos cuentas desde la aplicación, Usuario A y Usuario B. Usa sesiones o perfiles de navegador separados.
4. Con Usuario A, registra una asignatura, edítala y comprueba los cambios. Crea una actividad asociada, edítala y cambia su estado a `En progreso` y después a `Completada`.
5. Comprueba en el dashboard que las estadísticas y los resúmenes corresponden a Usuario A. La actividad completada debe dejar de aparecer entre las pendientes.
6. Con Usuario B, comprueba que no aparecen los registros de A. Prueba abrir las URL de edición de la asignatura y actividad de A; deben responder 404. Envía también un cambio de estado y un borrado con los IDs de A y el token CSRF de B; al volver a A, sus registros deben seguir intactos.
7. Envía un POST sin token y otro con token incorrecto a un formulario de escritura; ambos deben responder 400 y no cambiar datos. Envía un token válido con una entrada inválida y comprueba que la validación muestra el error sin escribir.
8. Con Usuario A, elimina la actividad desde el formulario y verifica que ya no aparece. Después elimina la asignatura de prueba y confirma que su actividad asociada desaparece por la cascada de la clave foránea. Estas eliminaciones son solo para registros de prueba.
9. Cierra sesión y comprueba que una nueva visita al dashboard redirige al login. Prueba también el cierre por GET, que debe responder 405.

Toda eliminación de este procedimiento debe apuntar exclusivamente a `carga_academica_test`. No ejecutes `DROP DATABASE` ni reutilices esta cuenta para datos reales.
