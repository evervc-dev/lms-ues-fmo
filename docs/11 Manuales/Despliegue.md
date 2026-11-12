---
tipo: tecnico
estado: revisado
rnf: ["[[RNF03]]", "[[RNF04]]", "[[RNF13]]"]
---

# Despliegue

Guía para el equipo técnico que pone el LMS en un servidor. Sirve para **staging** hoy y para **producción** cuando se decida el hosting.

> [!warning] Falta decidir el hosting de producción
> [[ADR-020 Hosting y almacenamiento]] sigue pendiente. Lo que depende de esa decisión está marcado **(según el hosting)**. Ya están decididos la plataforma ([[ADR-015 Plataforma base]]) y los entornos ([[ADR-019 Entornos]]: producción y staging).

## 1. Qué se despliega
Una sola imagen de Docker (`docker/Dockerfile`: PHP 8.4, nginx, php-fpm y el build de Vite) que corre en cuatro procesos, más dos servicios de datos:

| Servicio | Qué hace | Comando del contenedor |
|---|---|---|
| `app` | La web. Al arrancar **migra la base** y, en la demo, la siembra | `web` |
| `horizon` | Las colas: `notificaciones`, `default`, `google`, `importaciones`, `reportes` | `horizon` |
| `scheduler` | Las tareas programadas: cierre de intentos vencidos, aviso de revisiones, reportes por correo y limpieza nocturna | `scheduler` |
| `reverb` | Los avisos en tiempo real | `reverb` |
| `postgres:17` | Base de datos, con triggers de auditoría y RLS | — |
| `redis:7` | Sesiones, caché y colas | — |

`docker/compose.yml` arma la pila completa; `docker/entrypoint.sh` decide el rol de cada contenedor y se niega a arrancar, con un mensaje claro, si falta una variable obligatoria.

## 2. Variables de entorno
Nunca en el repositorio: se cargan en el panel del hosting.

| Grupo | Variables | Nota |
|---|---|---|
| Aplicación | `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_TIMEZONE=America/El_Salvador` | `APP_KEY` con `php artisan key:generate --show`. `APP_ENV=production` en producción |
| Base de datos | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (o `DB_URL`) | PostgreSQL 17 |
| Redis | `REDIS_HOST` (o `REDIS_URL`) | — |
| Tiempo real | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | — |
| Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `GOOGLE_ALLOWED_DOMAIN=ues.edu.sv` | Cliente OAuth de la UES (lo tramita TI) |
| Google Calendar | `GOOGLE_CALENDAR_ENABLED=true`, `GOOGLE_CALENDAR_REDIRECT_URI` | Mismo cliente, con la Calendar API habilitada |
| Correo | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | SMTP institucional (lo tramita TI) |
| Solo staging | `LMS_DEMO_SEED=true`, `LMS_DEV_LOGIN=true` | **Nunca en producción**: siembran datos de prueba y abren «Entrar como…» |

En el cliente OAuth de Google deben estar registradas las dos URI de redirección de cada entorno: `${APP_URL}/auth/google/callback` y `${APP_URL}/perfil/google-calendar/callback`.

## 3. Primer despliegue
1. Cree el proyecto en el hosting **(según el hosting)** con `docker/compose.yml`, desde la raíz del repositorio y la rama del entorno: `main` para producción.
2. Cargue las variables de la sección 2 y apunte el dominio al servicio `app`. Debe ser un dominio bajo `ues.edu.sv` con TLS ([[RNF01]]); el dominio se tramita con TI.
3. Despliegue. El contenedor `app` espera a PostgreSQL, corre `php artisan migrate --force` y queda sano; los demás arrancan después.
4. Cree los roles y permisos (es idempotente y no siembra datos de prueba):
   ```bash
   php artisan db:seed --class=BaseSystemSeeder --force
   ```
5. Verifique la integración con Google:
   ```bash
   php artisan google:verificar
   ```
6. Entre con la cuenta del primer Administrador. Su cuenta se crea **sin rol** al primer ingreso: asígnele el rol desde la consola (`php artisan tinker`) o con la cuenta de emergencia ([[Operación - Cuenta de emergencia]]).

## 4. Despliegues siguientes
1. Solo se despliega lo que llegó a `main` por un PR desde `test`, después de la verificación local: suite en SQLite y en **PostgreSQL 17 con 4 procesos**, Pint y Larastan ([[ADR-061 Verificación local sin GitHub Actions]]).
2. El hosting reconstruye la imagen y reinicia los contenedores. Las migraciones corren solas al arrancar `app`, y Horizon toma el código nuevo al reiniciarse.
3. Las migraciones deben poder correr con usuarios conectados: agregar columnas y tablas, no renombrarlas ni borrarlas en el mismo despliegue.
4. Si cambió algún manual, `resources/ayuda/` ya viene actualizado en el commit (`php artisan ayuda:sincronizar` antes de fusionar).

## 5. Comprobaciones después de desplegar
- [ ] La página de inicio abre y el inicio de sesión con Google funciona con una cuenta @ues.edu.sv, y rechaza una de Gmail.
- [ ] `php artisan google:verificar` termina sin fallas.
- [ ] Con un docente que tenga Calendar conectado: `php artisan google:calendar-prueba <correo>` crea el evento con Meet.
- [ ] **Monitoreo técnico** (Horizon) muestra los supervisores activos y sin trabajos fallidos.
- [ ] Llega un correo de prueba: por ejemplo, el aviso de una nota publicada.
- [ ] La campana recibe un aviso sin recargar la página (Reverb).

## 6. Límites del servidor
`docker/php.ini` fija `memory_limit = 256M` y `max_execution_time = 120`. Los PDF de tablas se cortan en 500 filas para no pasar ese límite (H-01); el Excel y el CSV llevan todas las filas. Si se sube el límite, mantenga el mismo valor en todos los contenedores.

## 7. Respaldos
Decidido ([[Respaldos y recuperación]]): respaldo diario, 30 días de retención, RTO menor a 4 h y RPO menor a 24 h.

> [!danger] Todavía no está automatizado
> El paquete de respaldos (`spatie/laravel-backup`) **no está instalado**. Mientras tanto, programe en el hosting un volcado diario de la base y de los archivos subidos **(según el hosting)**:
> ```bash
> pg_dump --format=custom --file=lms-$(date +%F).dump "$DB_URL"
> ```
> Haga al menos un **simulacro de restauración** en staging antes del release.

## 8. Monitoreo
- **Colas:** **Monitoreo técnico** (Horizon), solo para el Administrador. Un trabajo fallido en la cola `google` suele ser un permiso de Calendar revocado o una cuota de Google.
- **Errores:** los registros de Laravel en `storage/logs` del contenedor **(según el hosting)**.
- **Auditoría:** cada cambio importante queda en la auditoría de PostgreSQL y se consulta en **Auditoría**.

## 9. Volver a la versión anterior
1. Despliegue de nuevo el commit anterior de `main` (o reviértalo con un PR), según lo permita el hosting **(según el hosting)**.
2. **No** deshaga migraciones en producción con `migrate:rollback` sin un respaldo reciente: pueden borrar columnas con datos. Si una migración salió mal, corríjala con una migración nueva.
