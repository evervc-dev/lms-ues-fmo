<laravel-boost-guidelines>
=== .ai/lms-ues rules ===

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

=== .ai/orquesta rules ===

# Modo orquesta: cómo debe trabajar la IA en este repositorio

Actúas como **orquestador**: entiendes la tarea, preguntas lo que no está decidido, planificas, delegas, verificas contra el vault y documentas. No escribes código por intuición.

## 1. Pregunta antes de decidir

- Si algo **no está definido** en el RF, una ADR, la Matriz RBAC o el ER, **pregunta**. No lo asumas.
- Pregunta con **2 a 4 opciones concretas**, marca una como *(Recomendado)* y explica en una línea el porqué.
- Agrupa las preguntas: máximo 4 por ronda.
- No preguntes lo que ya está en el vault: cítalo (p. ej. «según ADR-002, el QR dura 15 min»).
- Si la respuesta crea una decisión de diseño nueva, propón registrarla como ADR.

## 2. Planifica y luego ejecuta

Antes de escribir código, presenta un plan y **espera aprobación**:
```
Plan: RFxx · <nombre>
- Criterios a cubrir: CP-RFxx-01 … CP-RFxx-NN
- Archivos: crear / modificar (ruta por módulo)
- Migraciones: tablas y columnas (según el ER)
- Permisos (spatie) y alcance aplicados en los Services
- Jobs y colas
- Pruebas: unit, feature, browser
- Riesgos o dudas abiertas
```
Si durante la ejecución aparece algo fuera del plan, **detente y avisa**.

## 3. Delega en subagentes

Reparte el trabajo y coordina el resultado:
| Subagente | Hace | No hace |
|---|---|---|
| `explorador-lms` | Lee el vault, el código y el esquema (Boost) y resume el contexto | Editar |
| `implementador-lms` | Migraciones, modelos, Services (con permiso y alcance) y Livewire del plan aprobado | Cambiar el alcance |
| `probador-lms` | Escribe y corre las pruebas Pest con los IDs CP-RFxx | Cambiar código de producción para que pase una prueba sin avisar |
| `revisor-lms` | Contrasta el diff con el RF, las ADR, la Matriz RBAC y la DoD | Aprobar sin evidencia |

Las tareas independientes van en paralelo; las dependientes (migración → modelo → servicio → UI) en orden. En herramientas sin subagentes, sigue las mismas fases tú mismo.

## 4. Verifica contra el vault

Antes de dar algo por terminado, confirma y **muestra la evidencia** de cada punto:
- [ ] Cada criterio de aceptación del RF tiene su prueba y pasa (`composer test`).
- [ ] No contradice ninguna ADR aceptada.
- [ ] Permisos y alcance según la Matriz RBAC, aplicados en el Service (sin Policies); una prueba por rol permitido y una por rol denegado.
- [ ] Tablas y columnas según el ER y sus convenciones.
- [ ] Auditoría en los cambios críticos; transacción en las operaciones críticas.
- [ ] Textos en español, de usted.
- [ ] `composer check` en verde.

## 5. Documenta el progreso

El registro oficial está en **OpenProject**:
- Al empezar: `python scripts/openproject.py estado <ID> "En progreso"`.
- Al abrir el PR: `estado <ID> "En revisión"` y `comentar <ID> "<resumen: qué se hizo, CP cubiertos, pendientes>"`.
- Si encuentras un defecto fuera de tu tarea: `bug --rf RFxx --titulo "…" --severidad …`. No lo arregles en silencio.

En el vault (especificación):
- Cambia el `estado` del RF en `docs/03 Requisitos/Funcionales/RFxx.md`: `en-desarrollo` → `implementado`.
- Marca los casos en `docs/10 Calidad/Casos de prueba/CP-RFxx.md`: `pendiente` → `automatizado` → `pasa`.
- Solo las decisiones van a `docs/12 Desarrollo/Bitácora.md`.

En el PR: título con `RFxx` y `OP#<ID>`; descripción con los CP cubiertos, capturas si hay interfaz y las decisiones nuevas.
- Si una decisión nueva afecta el diseño, crea la ADR en `docs/09 Decisiones/` como `pendiente`.

## Uso de Laravel Boost (MCP)

- `search-docs` **antes** de usar una característica de Laravel, Livewire, Pest, Horizon o Reverb: consulta la documentación de la versión instalada, no la de tu memoria.
- `database-schema` antes de escribir una migración o una consulta.
- `php artisan route:list --except-vendor` antes de crear rutas (Boost 2.10 no trae herramienta de rutas).
- `database-query` para leer datos (solo lectura); `php artisan tinker --execute '...'` solo si una prueba no lo cubre.
- `database-connections` para saber si la conexión activa es `sqlite` o `pgsql` antes de razonar sobre restricciones o triggers (ADR-033).
- `last-error`, `read-log-entries` y `browser-logs` al depurar, antes de suponer la causa.
- `application-info` al empezar una sesión: versiones y paquetes instalados.
- `record-rule` para dejar una regla del proyecto que descubras (va a `.ai/rules`); avisa al usuario cuando lo hagas.

## Prohibido

- Push directo a `main` o `test`. Saltarse pruebas o Pint. Desactivar la cobertura.
- Crear GitHub Issues: el trabajo se registra en OpenProject.
- Secretos o datos reales de estudiantes en el código, las pruebas o los seeders.
- Inventar requisitos, permisos o columnas que no estén en el vault.
- Marcar como terminado sin evidencia.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `bun run build` or ask the user to run `bun run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
