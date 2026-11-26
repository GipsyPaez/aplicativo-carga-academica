# Evolución Front-end + API REST

Esta ampliación conserva el sistema PHP/MariaDB original y añade una API JSON y un cliente React. La interfaz React no sustituye las reglas de negocio del servidor: consume los recursos de la API y la base de datos continúa siendo gestionada por PHP mediante PDO.

## Arquitectura

- **Frontend:** React 19, Vite, React Router, Axios.
- **Backend:** PHP 8+, PDO y MariaDB 10.11+.
- **API:** rutas JSON bajo `/api`; especificación OpenAPI 3.0 en `api/openapi.yaml`.
- **Sesión:** cookie PHP HttpOnly y SameSite=Lax; token CSRF enviado en `X-CSRF-Token` en operaciones de escritura.

## Instalación local (Apache)

1. Instala PHP 8+ con `pdo_mysql` y `mbstring`, Apache con `mod_rewrite`, MariaDB 10.11+ y Node.js 20.19+ o 22.12+.
2. Coloca la carpeta del repositorio en el directorio web como `carga-academica` (por ejemplo, `htdocs/carga-academica`).
3. Crea una base de datos vacía y ejecuta el esquema **solo en una base nueva**:

   ```bash
   mariadb -u USUARIO -p carga_academica < database/install.sql
   ```

4. Configura `config/database.local.php` a partir de `config/database.local.example.php`, o usa `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`. Nunca publiques credenciales.
5. Inicia Apache y comprueba que la API responde en `http://localhost/carga-academica/api/auth/csrf`.
6. En otra terminal:

   ```bash
   cd frontend
   npm install
   npm run dev
   ```

   Abre la URL que Vite indique (normalmente `http://localhost:5173`). El proxy de desarrollo reenvía `/api` a `http://localhost/carga-academica/api`.

> Si el directorio del proyecto tiene otro nombre o Apache utiliza otro puerto, actualiza la regla `rewrite` de `frontend/vite.config.js` y la URL de API.

## Compilación y despliegue

Para generar la versión de producción del frontend, sigue estos pasos desde la raíz del proyecto:

1. Accede a la carpeta del frontend:

   cd frontend

2. Crea el archivo de configuración de producción a partir de la plantilla:

   cp .env.example .env.production

3. Comprueba que el archivo `frontend/.env.production` contiene las siguientes variables:

   VITE_API_URL=/carga-academica/api
   VITE_APP_BASE=/carga-academica/frontend/dist/

   Estas rutas corresponden al despliegue local en XAMPP. Si se instala el proyecto en otra ubicación, deberán adaptarse.

4. Instala las dependencias:

   npm install

5. Genera los archivos optimizados para producción:

   npm run build

Vite genera los archivos compilados dentro de `frontend/dist/`. Este directorio es el que Apache utiliza para servir el frontend.

El archivo `.htaccess` de la raíz del proyecto permite gestionar las rutas de React y dirigirlas a `frontend/dist/index.html`, manteniendo las peticiones de la API bajo la ruta `/api`.

Direcciones de acceso en el entorno local:

- Frontend: http://localhost/carga-academica/frontend/dist/
- API REST: http://localhost/carga-academica/api
- Documentación Swagger: http://localhost/carga-academica/docs/

El frontend y la API funcionan bajo el mismo origen, lo que permite mantener la sesión PHP y evita la necesidad de configurar CORS para este despliegue.

Para producción se utiliza la compilación generada por Vite y servida mediante Apache. `vite preview` se reserva para comprobaciones locales y no sustituye a un servidor de producción.

## Documentación Swagger

La especificación de la API se encuentra en `api/openapi.yaml`. El proyecto incluye Swagger UI en `docs/index.html`, por lo que se puede consultar directamente desde el navegador:

http://localhost/carga-academica/docs/

Swagger UI permite visualizar los endpoints, sus parámetros y las respuestas documentadas. Para probar operaciones protegidas, es necesario iniciar sesión y disponer del token CSRF obtenido mediante `GET /auth/csrf`, que debe enviarse en la cabecera `X-CSRF-Token`.

## Recursos REST

| Método | Ruta | Uso |
|---|---|---|
| GET | `/auth/csrf` | Obtener token CSRF inicial |
| POST | `/auth/register` | Registrar usuario |
| POST | `/auth/login` | Iniciar sesión |
| GET | `/auth/me` | Usuario autenticado |
| POST | `/auth/logout` | Cerrar sesión |
| GET, POST | `/subjects` | Listar y crear asignaturas |
| PUT, DELETE | `/subjects/{id}` | Actualizar y eliminar asignatura propia |
| GET, POST | `/activities` | Listar y crear actividades |
| PUT, DELETE | `/activities/{id}` | Actualizar y eliminar actividad propia |
| GET, PUT | `/availability` | Consultar y guardar disponibilidad |
| GET | `/weekly-index?week=2026-W48` | Calcular índice semanal |
| GET | `/calendar?month=11&year=2026` | Consultar calendario mensual |

Las escrituras requieren autenticación y `X-CSRF-Token`. Las consultas de asignaturas y actividades siempre filtran por el usuario de la sesión; las actividades solo pueden asociarse a asignaturas propiedad de ese usuario.

## Tecnologías front-end demostradas

- `useState`: campos de formularios, filtros, periodos y mensajes.
- `useEffect`: carga inicial de usuario, asignaturas, actividades y disponibilidad.
- `useContext`: contexto global de autenticación.
- `useReducer`: gestión de estados de carga, error y datos de actividades.
- Context API: `AuthProvider` comparte usuario y operaciones de autenticación.
- Axios: cliente HTTP, cookies con `withCredentials`, interceptor CSRF y normalización de errores.
- React Router: navegación entre panel, asignaturas, actividades, calendario y disponibilidad; rutas privadas.

## Pruebas y verificación

Desde la raíz del repositorio:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/indice_carga_test.php
php tests/calendario_test.php
```

Para validar la interfaz:

```bash
cd frontend
npm install
npm run build
```

La compilación del frontend valida resolución de módulos y JSX; no sustituye las pruebas integradas contra una instancia MariaDB real. Para probar flujos completos, configura una base aislada y realiza registro, inicio de sesión, CRUD, disponibilidad, índice y calendario. No uses la base de producción para pruebas destructivas.

## Limitaciones de esta entrega

- No se ejecutó la aplicación contra una base MariaDB en este entorno; la conexión y los flujos HTTP requieren configuración local.
- No se han publicado los archivos en GitHub ni desplegado en un hosting externo: se entrega el proyecto preparado para que el propietario del repositorio lo revise y publique.
- Swagger/OpenAPI describe las rutas; la visualización de Swagger UI se realiza mediante el editor o un contenedor Swagger UI, no se incluye un servidor de documentación independiente.
