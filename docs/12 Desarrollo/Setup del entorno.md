---
tipo: desarrollo
estado: revisado
adr: ["[[ADR-023 Entorno de desarrollo estandarizado]]"]
---

# Setup del entorno de desarrollo

Objetivo: que **todos los entornos sean lo más parecidos posible** ([[ADR-023 Entorno de desarrollo estandarizado]]).
- **Igual para todos:** PostgreSQL, Redis, Mailpit y MinIO en Docker, con la misma imagen y versión.
- **Nativo con versión fija:** PHP 8.4, Composer 2 y Bun.
- **Mismos comandos** gracias a los scripts de Composer.

Los archivos están en la raíz del repositorio `lms-ues` ([[Plantilla del repositorio]]).

## Versiones estándar
| Herramienta | Versión | Dónde |
|---|---|---|
| PHP | **8.4.27** o superior de la rama 8.4 (versión de seguridad del 21/10/2026) | Nativo (`php.new`) |
| Composer | **2.x** | Nativo (viene con `php.new`) |
| Bun | **1.2.x** o superior | Nativo |
| Base de datos | **SQLite** por defecto; **PostgreSQL 17** opcional, igual a producción | SQLite: archivo `database/database.sqlite`. PostgreSQL: Docker (`postgres:17-alpine`) |
| Redis | **7** | Docker (`redis:7-alpine`), cliente **predis** en PHP |
| Mailpit / MinIO | latest | Docker |
| Git | 2.40+ con `core.autocrlf=input` | Nativo |

Extensiones de PHP requeridas: `pdo_pgsql`, `mbstring`, `intl`, `bcmath`, `zip`, `gd`, `xml`, `curl`, y además **`pcntl` en Linux** (para Horizon y Pail).

---

## Linux (Ubuntu, Debian, Fedora…) y WSL2
### 1. Herramientas
```bash
# PHP 8.4 + Composer + instalador de Laravel (instalador oficial de Laravel)
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"

# Bun
curl -fsSL https://bun.sh/install | bash

# Git: finales de línea LF
git config --global core.autocrlf input
```
Docker: instale **Docker Engine** con el plugin `compose` (Linux) o **Docker Desktop** con la integración WSL activada (WSL2). Cierre y abra la terminal para cargar el `PATH`.

### 2. Proyecto
```bash
git clone git@github.com:<org>/lms-ues.git && cd lms-ues
git checkout test
docker compose -f docker-compose.dev.yml up -d      # PostgreSQL, Redis, Mailpit, MinIO
./scripts/doctor.sh                                  # verifica el entorno
composer setup                                       # dependencias, .env, key, migraciones+seed, bun, Boost
composer dev                                         # web + Horizon + Reverb + scheduler + logs + Vite
```

---

## Windows nativo
> [!warning] En Windows nativo no se usa Horizon
> Horizon y Pail necesitan la extensión `pcntl`, que no existe en PHP para Windows. Quien usa Windows **no toca Horizon**: `composer dev:win` levanta `queue:listen` con las mismas colas. Si necesita Horizon, use WSL2 y la guía de Linux.

### 1. Herramientas (PowerShell como administrador)
```powershell
# PHP 8.4 + Composer + instalador de Laravel (instalador oficial de Laravel)
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))

# Bun
powershell -c "irm bun.sh/install.ps1 | iex"

# Git: finales de línea LF
git config --global core.autocrlf input
```
Instale **Docker Desktop**. Cierre y abra la terminal.

### 2. Proyecto
```powershell
git clone git@github.com:<org>/lms-ues.git; cd lms-ues
git checkout test
docker compose -f docker-compose.dev.yml up -d
.\scripts\doctor.ps1
composer setup
composer dev:win                                     # web + queue:listen + Reverb + scheduler + Vite
```

---

## Base de datos: SQLite o PostgreSQL
Las migraciones son **adaptativas** ([[ADR-033 Migraciones adaptativas]]):
- **Por defecto, SQLite.** `composer setup` crea `database/database.sqlite`; no hace falta Docker para programar.
- **PostgreSQL**, igual a producción, con triggers de auditoría, RLS, EXCLUDE y particiones. En `.env`, comente `DB_CONNECTION=sqlite`, descomente el bloque `pgsql` y corra `composer fresh`.
- Si el puerto 5432 está ocupado en su máquina, levante el compose con `DB_FORWARD_PORT=55432 docker compose -f docker-compose.dev.yml up -d` y use `DB_PORT=55432`.
- En PostgreSQL, toda escritura en tablas auditadas pasa por `App\Support\DbContext` (o `DbContext::system()` en seeders); si no, el trigger la rechaza.

## Comandos estándar
| Comando | Qué hace | Linux | Windows |
|---|---|---|---|
| `composer setup` | `composer install`, crea `.env`, `key:generate`, `migrate:fresh --seed`, `bun install`, `bun run build`, `boost:update` | ✅ | ✅ |
| `composer dev` | Web (8000), **Horizon**, **Reverb** (8080), scheduler, **Pail** (logs) y **Vite** | ✅ | ❌ |
| `composer dev:win` | Web, **queue:listen** (5 colas), Reverb, scheduler y Vite | — | ✅ |
| `composer fresh` | `migrate:fresh --seed`: base de datos limpia con datos de prueba ([[#Datos de prueba]]) | ✅ | ✅ |
| `composer test` | Pest en paralelo con SQLite en memoria; la CI las repite en PostgreSQL `lms_test` ([[ADR-033 Migraciones adaptativas]]) | ✅ | ✅ |
| `composer check` | Pint + Larastan 5 + Pest con cobertura ≥ 70 %: lo mismo que la CI | ✅ | ✅ |
| `php artisan migrate` | Aplica las migraciones nuevas | ✅ | ✅ |
| `bun install` / `bun run dev` / `bun run build` | Dependencias de frontend, Vite en modo desarrollo, build | ✅ | ✅ |
| `php artisan boost:update` | Regenera las directrices de IA después de cambiar `.ai/guidelines` | ✅ | ✅ |
| `docker compose -f docker-compose.dev.yml up -d` / `down` | Levanta o detiene los servicios | ✅ | ✅ |

## Workers (colas)
| | Linux (Horizon) | Windows (`queue:listen`) |
|---|---|---|
| Arranque | Incluido en `composer dev`, o `php artisan horizon` | Incluido en `composer dev:win`, o `php artisan queue:listen redis --queue=notificaciones,default,google,importaciones,reportes --tries=3` |
| Después de cambiar un job | `php artisan horizon:terminate` (`composer dev` lo relanza) | No hace falta: `queue:listen` recarga el código |
| Panel | `http://localhost:8000/horizon` | — |
| Jobs fallidos | Panel de Horizon | `php artisan queue:failed` / `queue:retry all` |

Las 5 colas y su prioridad son las de [[Colas, programador y tiempo real]].

## Reverb (tiempo real)
```bash
php artisan reverb:start --debug      # ya incluido en composer dev / dev:win
```
- Escucha en `ws://localhost:8080`. Las claves `REVERB_*` y `VITE_REVERB_*` vienen en `.env.example`.
- Si cambia la configuración de Reverb: detenga y vuelva a lanzar `composer dev`.
- **Si el 8080 ya está en uso** (`EADDRINUSE`): en una máquina con **Coolify**, su proxy (Traefik) publica el 8080 para el Reverb de staging, y chocará siempre. `php artisan dev --reverb` lo detecta antes de levantar nada y lo dice. Para ver quién lo ocupa: `ss -lntp | grep :8080`. Para usar otro puerto, cambie **los dos valores juntos** en `.env`, por ejemplo `REVERB_SERVER_PORT=8081` y `REVERB_PORT=8081`: el primero es donde escucha el servidor y el segundo adonde se conecta el navegador, y si no coinciden la campana nunca conecta.
- Para probarlo: abra dos sesiones, publique una nota y verifique que la notificación llegue en menos de 30 s ([[RNF12]]).

## Programador
`php artisan schedule:work` (incluido en `dev` y `dev:win`) ejecuta las tareas de [[Colas, programador y tiempo real]] cada minuto.

## Direcciones locales
| Servicio | URL |
|---|---|
| App | http://localhost:8000 |
| Horizon (solo Linux) | http://localhost:8000/horizon |
| Mailpit (correos) | http://localhost:8025 |
| Consola de MinIO | http://localhost:9001 (usuario `lms` / `lms-secret-dev`) |
| PostgreSQL | `127.0.0.1:5432`, usuario y contraseña `lms`. Bases: `lms` (desarrollo) y `lms_test` (pruebas, la crea `scripts/postgres-init.sql`) |

## Datos de prueba
`composer fresh` (o `php artisan migrate:fresh --seed`) llena la base en unos 4 s en SQLite y 25 s en PostgreSQL. El catálogo es **inventado** hasta recibir el real de la UES.

| Seeder | Entornos | Contenido |
|---|---|---|
| `BaseSystemSeeder` | Todos, también producción (idempotente) | 6 roles y 54 permisos de la [[Matriz RBAC]] (`database/seeders/data/rbac.php`), 13 parámetros provisionales y 15 eventos de notificación |
| `InstitutionSeeder` | Local y staging | 3 departamentos, 4 carreras (ISI con planes 98 y 25), 50 materias en 10 ciclos, prerrequisitos, 14 aulas, área del campus y períodos 2026-I (cerrado), 2026-II (activo) y 2027-I (en planificación) |
| `DemoPeopleSeeder` | Local y staging | 244 estudiantes, 30 docentes, jefes, coordinadores (con historial), soporte, lote de importación, dispositivos y accesos |
| `DemoAcademicSeeder` | Local y staging | 70 secciones sin choques de aula, docente ni estudiante; ~2 200 matrículas con retiros y cambios de grupo; contenidos (con papelera), tareas, parciales con banco de preguntas, rúbricas y equipos; ~2 300 notas; ~24 000 registros de asistencia (QR, código, Meet y manual); alertas, notificaciones, foros, mensajes masivos; el período 2026-I cerrado con actas, cierres y resultados |

Fechas de la demo: hoy hay una **sesión de asistencia abierta** en PRN115-01 (de `docente.dev`) y una clase virtual próxima en BAD115-02; la Tarea 2 está abierta y con entregas por calificar.

## Inicio de sesión en desarrollo
Con `LMS_DEV_LOGIN=true` aparece **«Entrar como…»** para elegir un usuario sembrado por rol, sin Google. Esto existe en local y staging; **nunca en producción** ([[Entornos]]). Se entra por `/login` → **Entrar como…** (`/dev/entrar-como`): muestra las 6 cuentas de desarrollo y un buscador por correo o nombre para entrar como cualquier otro usuario sembrado.

| Cuenta | Rol | Qué ve en la demo |
|---|---|---|
| `admin.dev@ues.edu.sv` | Administrador | Todo; reportes programados |
| `jefe.dev@ues.edu.sv` | Jefe de departamento (DIA) | Su departamento; cierre de 2026-I autorizado |
| `coordinador.dev@ues.edu.sv` | Coordinador de ISI y docente | Solicitudes de cambio de grupo pendientes, lote de importación, DSI115-01 |
| `docente.dev@ues.edu.sv` | Docente | PRN115-01 (sesión QR abierta) y BAD115-02 |
| `estudiante.dev@ues.edu.sv` | Estudiante, ciclo 5, carné DE26001 | 5 materias, notas, alertas y notificaciones |
| `soporte.dev@ues.edu.sv` | Soporte técnico | Solicitudes de cambio de teléfono |

## Google Calendar (RF21)
Usa el mismo cliente OAuth del inicio de sesión ([[ADR-018 Calendar con OAuth del docente]]). En la consola de Google Cloud: habilite la **Google Calendar API** y agregue la URI de redirección `${APP_URL}/perfil/google-calendar/callback`; si la pantalla de consentimiento es «Externa» en modo de prueba, agregue a los docentes como usuarios de prueba. En el `.env`:

```dotenv
GOOGLE_CALENDAR_ENABLED=true
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/perfil/google-calendar/callback"
```

1. `php artisan google:verificar` revisa el cliente y la URI de Calendar.
2. El docente entra con su cuenta @ues.edu.sv y conecta en **Mi perfil › Google Calendar**.
3. `php artisan google:calendar-prueba <correo>` crea hoy un evento de prueba con enlace de Meet en su calendario.
4. Tras cambiar el código de los jobs, `php artisan horizon:terminate`: los eventos se crean y se borran en la cola `google`.

Las pruebas apagan Calendar en `phpunit.xml` (`GOOGLE_CALENDAR_ENABLED=false`) y simulan a Google con `Http::fake`.

## Despliegue con Docker (staging en Coolify)
La carpeta `docker/` empaqueta la app para un servidor (OP#518):

| Archivo | Para qué |
|---|---|
| `docker/Dockerfile` | Una sola imagen: PHP 8.4 + nginx + php-fpm, dependencias de Composer y build de Vite con Bun. Se construye desde la raíz |
| `docker/compose.yml` | Pila completa: `app` (web), `horizon`, `scheduler` y `reverb` con la misma imagen, más `postgres:17` y `redis:7` |
| `docker/entrypoint.sh` | Rol del contenedor. Solo `app` migra y, con `LMS_DEMO_SEED=true` y la base vacía, siembra la demo |
| `docker/nginx.conf`, `php.ini`, `php-fpm.conf`, `supervisord.conf` | Configuración del servidor web. `/app/` se pasa a Reverb, así el navegador usa el mismo dominio |

**Coolify:** recurso *Docker Compose*, rama `main`, Base Directory `/`, Docker Compose Location `/docker/compose.yml`, dominio en el servicio `app`. Variables obligatorias: `APP_KEY` (`php artisan key:generate --show`), `DB_PASSWORD` y `REVERB_APP_SECRET`. Para la demo: `LMS_DEMO_SEED=true` y `LMS_DEV_LOGIN=true` (con `APP_ENV=staging`, el valor por defecto).

**Local:** copie `docker/.env.example` a `docker/.env`, complete las claves y, desde la raíz:
```bash
docker compose --project-directory . -f docker/compose.yml --env-file docker/.env up -d --build
```
El compose no publica puertos (Coolify enruta por dominio); para abrirla en local agregue `ports: ["8088:80"]` al servicio `app` en un archivo de override.

## Problemas comunes
| Síntoma | Causa | Solución |
|---|---|---|
| `could not find driver (pgsql)` | Falta `pdo_pgsql` | Reinstale con `php.new` o habilite la extensión en `php.ini` |
| `Connection refused 5432/6379` | Los servicios no corren | `docker compose -f docker-compose.dev.yml up -d` |
| Las notificaciones no llegan | Falta un worker o Reverb | Revise que `composer dev` muestre `horizon`/`queue` y `reverb` |
| Los cambios en un job no se aplican (Linux) | Horizon tiene el código viejo | `php artisan horizon:terminate` |
| Diffs enormes por finales de línea | CRLF de Windows | `git config --global core.autocrlf input` y volver a clonar |
| Puerto 8080 ocupado | Otro servicio | Cambie `REVERB_PORT` y `VITE_REVERB_PORT` en `.env` |
