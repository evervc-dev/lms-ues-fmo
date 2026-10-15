# LMS UES FMO: contexto del proyecto

## Qué es
Plataforma LMS para Ingeniería de Sistemas Informáticos de la UES FMO. Es un monolito **Laravel 12 + Livewire 4 + PHP 8.4**, **sin API pública**, con PostgreSQL 17, Redis 7 + Horizon y Reverb. La interfaz está en español y trata al usuario de **usted**.

## Fuente de verdad: el vault de documentación
La documentación vive en el vault de Obsidian en `docs/`. Antes de implementar o cambiar algo, **léelo**:
- Requisitos: `docs/03 Requisitos/Funcionales/RFxx.md`, con sus criterios de aceptación Dado/Cuando/Entonces.
- Decisiones: `docs/09 Decisiones/ADR-*.md`. **No contradigas una ADR aceptada**; si hace falta, propón una ADR nueva.
- Permisos: `docs/04 Roles/Matriz RBAC.md`.
- Datos: `docs/07 Datos/Modelo entidad-relación.md`.
- Flujos: `docs/08 Flujos/`.
- Calidad: `docs/10 Calidad/Definition of Done.md` y `docs/10 Calidad/Casos de prueba/CP-RFxx.md`.

## Decisiones clave (resumen; el detalle está en las ADR)
- Inicio de sesión **solo con Google `@ues.edu.sv`**, validando `hd` en el servidor; una cuenta institucional que no existe se crea **sin rol** al primer ingreso (ADR-038). Hay una cuenta de emergencia con 2FA (ADR-007).
- Asistencia QR: sesión de **5 a 15 min** configurable con **token que rota cada 10 s**, máximo 3 intentos, GPS dentro del área y token de dispositivo (ADR-028, ADR-045).
- Asistencia virtual: el **docente sube el reporte de Meet**; Presente si es ≥ 70 % de la duración programada (ADR-003).
- Auditoría por **triggers de PostgreSQL**, inmutable. La ve el Administrador (toda) y el Jefe (**solo su departamento**, con RLS) (ADR-027, ADR-029).
- Entregas tardías configurables por tarea (ADR-008); 4 tipos de pregunta, con ensayo (ADR-009).
- Notificaciones con Reverb, más polling de respaldo (ADR-010).
- Matrícula: el estudiante con la **clave de la asignatura** (elige la sección), o el docente, el coordinador y el jefe en su ámbito, y Soporte en cualquier sección. Se valida el choque de horario; **no** los prerrequisitos. La carga masiva la hacen el Coordinador (su carrera) y Soporte (ADR-030, ADR-032, ADR-037).
- Usuarios (ADR-037, `JerarquiaService`): el Administrador crea y asigna todos los roles y es el único que desactiva. Soporte crea estudiantes, y docentes solo con el permiso directo `usuarios.crear-docente`. El Jefe no crea cuentas: da Docente y Coordinador en su departamento, asigna docentes a sus secciones y cambia el cupo.
- Cierre: el docente publica el acta, el Coordinador la valida, el Jefe autoriza su departamento y solo entonces el Administrador cierra el período (RF34).
- Calificación anónima por evaluación (`is_anonymous`); boleta por cómputo y por unidad (ADR-032).
- Parámetros en `system_settings` (provisionales): nota mínima 6.0, 1 decimal mitad hacia arriba, nota faltante = 0, tardanza = 0,5, justificadas no cuentan.

## Convenciones de código
- Módulos de dominio en `app/Modules/<Modulo>/{Models,Services,Livewire,Jobs,Events,Listeners,Notifications}` (ADR-017). Módulos: Identidad, Academica, Contenidos, Evaluaciones, Calificaciones, Asistencia, Comunicacion, Reportes, Auditoria.
- Eventos de dominio en `Events/` del módulo que los emite; otros módulos los escuchan desde sus `Listeners/`. Las notificaciones (`Notifications/`) se envían por los canales database, broadcast y mail, en la cola `notificaciones`.
- La lógica de negocio va en **Services**. Livewire solo orquesta.
- **Componentes Livewire de clase + vista**, nunca de un solo archivo (SFC/MFC) ni con prefijo ⚡. La clase va en `app/Modules/<Modulo>/Livewire/` y la vista en `resources/views/modules/<modulo>/`; se registran en el ServiceProvider del módulo.
- **Validación:** el formato se valida **dentro de la clase del componente** (`#[Validate]` / `$this->validate()`); las reglas de negocio, en el Service. **No crees Form Requests ni controladores de API**: no hay API (RNF09).
- **Autorización sin Policies** (ADR-026): **no crees Policies ni Gates**. Única excepción existente: el Gate `viewHorizon` en `App\Providers\HorizonServiceProvider`, para el panel de Horizon; no agregues otros.
  - La ruta o el componente se protege con el middleware `permission:<permiso>` de spatie.
  - El Service exige el permiso y filtra por alcance con `App\Support\Alcance`: G global, D departamento, C carrera, S sus secciones, M sus matrículas, P lo propio.
  - Sin permiso, el Service lanza `AuthorizationException` (403).
- Base de datos (ADR-031, ADR-027):
  - Tablas y columnas en **inglés snake_case**; modelos en inglés singular (`Enrollment`, `AttendanceRecord`). PK `bigint`, más `ulid` en las entidades expuestas en URL. Fechas `timestamptz` (instante en UTC); la app y la sesión de PostgreSQL trabajan en `APP_TIMEZONE=America/El_Salvador`: no conviertas a mano con `->utc()` (ADR-041).
  - Soft delete **solo** en `units` y `resources`. Notas, asistencia, entregas y progreso se ligan a `enrollment_id`.
  - **Híbrido:** restricciones declarativas (UNIQUE, CHECK, FK compuestas, índices parciales, EXCLUDE) y **triggers solo de auditoría e inmutabilidad** en PostgreSQL. Las operaciones de varios pasos van en los Services, dentro de `App\Support\DbContext::run()`, que fija el actor (`app.user_id`); sin él, el trigger rechaza la escritura (`AC900`). Los errores SQL se traducen con `App\Support\DbError`.
  - **Migraciones adaptativas (ADR-033):** deben correr en SQLite (por defecto) y en PostgreSQL. Usa `App\Support\Database\Adaptive`: `partialUnique()` en ambos; `check()`, `citext()` y `pgsqlFile()` solo en PostgreSQL. El SQL propio de PostgreSQL va en `database/sql/pgsql/` (y su reversa en `99_drop.sql`). Prueba siempre `migrate:fresh` en SQLite.
  - En PostgreSQL toda escritura en tablas auditadas va dentro de `DbContext::run()` (o `DbContext::system()` en seeders y jobs); la lectura de auditoría fija `DbContext::auditScope()`.
  - El modelo de referencia es `docs/07 Datos/Modelo entidad-relación.md`; no inventes tablas ni columnas.
- Permisos con `spatie/laravel-permission`, con los nombres exactos de la Matriz RBAC (p. ej. `asistencia.sesion.abrir`).
- Todo cambio en una entidad crítica se audita con los triggers de PostgreSQL (no uses paquetes de auditoría).
- Llamadas a Google y trabajo pesado **en cola**, en la cola correcta: `notificaciones`, `default`, `google`, `importaciones` o `reportes`. Los jobs deben ser idempotentes.
- Textos de la interfaz en español con `__()`. Sin `env()` fuera de `config/`.

## Pruebas y calidad
- **Pest**. Cada criterio de aceptación tiene una prueba con su ID: `it('CP-RF20-03 rechaza fuera del área', ...)`.
- `composer check` debe pasar: Pint, Larastan nivel 5 y cobertura ≥ 70 %.
- Las pruebas usan **SQLite en memoria** por defecto. No hay CI (ADR-061): antes del PR de `test` a `main` se repiten en local en **PostgreSQL 17** con 4 procesos. Las que dependen de triggers, RLS, CHECK o EXCLUDE se saltan fuera de pgsql con `->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', ...)`.
- Ramas: `<modulo>-rfxx-<corto>` (propiedad `rama` del RF; p. ej. `asistencia-rf20-qr`), creada desde `test` → PR a `test` → PR a `main`. Nunca hagas push a `main` ni a `test`.

## Gestión del trabajo: OpenProject
- **OpenProject** registra tareas, estados, defectos y avance. **No uses GitHub Issues.**
- Cada RF es un Feature con tareas Back, Front y Test. Ubica el `OP#<ID>` correspondiente antes de empezar; si no lo encuentras, pregúntalo.
- Commits y título del PR incluyen `OP#<ID>`.
- Herramienta: `python scripts/openproject.py estado|comentar|bug …` (variables `OP_URL`, `OP_TOKEN` y `OP_PROYECTO` en el entorno; **nunca** escribas el token en archivos).

## Entorno
- Linux o WSL: `composer dev` (Horizon). Windows nativo: `composer dev:win` (`queue:listen`, sin Horizon ni Pail).
- Después de cambiar jobs en Linux: `php artisan horizon:terminate`.
