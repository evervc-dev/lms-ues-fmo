---
tipo: desarrollo
estado: en-curso
---

# Bitácora de desarrollo

El **avance de las tareas** se registra en **OpenProject** ([[OpenProject]]). Esta bitácora recoge solo **decisiones e hitos**: lo que deba quedar junto a la especificación. Las entradas nuevas van **arriba**.

## Formato
```markdown
### AAAA-MM-DD · RFxx · <título corto>
- **Autor:** persona / IA (herramienta)
- **OpenProject:** OP#nn · **PR:** #nn → test
- **Hecho:** …
- **CP cubiertos:** CP-RFxx-01, …
- **Decisiones:** … (ADR nueva si aplica)
- **Pendiente:** …
```

---

### 2026-11-01 · RF06 · Calendario de evaluaciones por ámbito (Jefe y Coordinador)
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#393 · **PR:** → test · **Rama:** `academica-rf06-calendario-ambito`
- **Hecho:** el calendario de evaluaciones deja de ser exclusivo del Administrador. Permiso nuevo `periodos.calendario.gestionar` (G/D/C), columnas `department_id` y `career_id` en `period_calendar_events` —anulables y excluyentes, con `CHECK`— y página propia «Calendario de evaluaciones» (`/calendario-evaluaciones/{periodo?}`), porque el Jefe y el Coordinador no entran al Detalle del período. Cada quien sincroniza con **su propio** Google Calendar e invita solo a los involucrados de su ámbito; todos ven las fechas globales y nadie modifica las de otro. El calendario salió del Detalle del período, que ahora enlaza a la página nueva.
- **CP cubiertos:** CP-RF06-04 (ampliado), CP-RF06-09 (nuevo).
- **Decisiones:** [[ADR-068 Calendario de evaluaciones por ámbito]], ampliada el mismo día de su creación: se eligió que **cada quien sincronice en su calendario** en vez de mandarlo todo al del Administrador, para que el alcance del permiso y el dueño del evento coincidan y nadie necesite el token de otro. Se dejó `periodos.gestionar` intacto a propósito: con ese permiso se cierra el período ([[RF34]]) y el Jefe no debe heredarlo. `periodos.calendario.gestionar` también abre Mi perfil › Google Calendar, porque sin conectar no hay sincronización.
- **Pendiente:** un docente de dos carreras puede recibir dos invitaciones, una por Coordinador; hay que decirlo en el [[Manual Docente]]. ADR-068 sigue sin ratificar.

### 2026-11-01 · RNF13 · Manuales para el usuario final
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** sin OP# · **PR:** → test · **Rama:** `docs-manuales-v1`
- **Hecho:** los 6 manuales de rol, la Guía de bienvenida y Despliegue pasan de `esqueleto` a `revisado`. Se quitó la nota interna que la Ayuda mostraba al usuario (A-03, A-05); cada manual usa los nombres reales del menú y de los botones (sacados de las vistas), explica el alcance del rol y cierra con «Problemas frecuentes» con los mensajes exactos que ve el usuario. Se corrigió lo desactualizado: el Coordinador ya no importa estudiantes ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]), la conexión con Google Calendar, las prórrogas, la revisión de notas y la justificación de faltas. La Guía usaba un correo real como ejemplo: ahora es ficticio.
- **Defecto encontrado y corregido:** el Jefe tenía los permisos de **Carga académica** (asignar docentes, cupo) y **Usuarios** (dar roles), pero esas pantallas no estaban en su menú; se agregaron a «Jefatura» con una prueba en `MenuTest`.
- **Decisiones:** Despliegue documenta lo que existe hoy (imagen Docker, `entrypoint.sh`) y marca lo que depende del hosting (ADR-020). Deja dicho que `spatie/laravel-backup` está decidido pero **no instalado**, con un volcado manual como respaldo provisional.
- **Pendiente:** las capturas de cada sección (`capturas: pendientes`); instalar el respaldo automático.

---

### 2026-11-01 · H-01 · PDF de «Estudiantes en riesgo» sin memoria
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** sin OP# (hallazgo H-01 de la QA del Administrador) · **PR:** → test · **Rama:** `reportes-h01-pdf-memoria`
- **Hecho:** reproducido con los datos de la demo: dompdf pedía **262 MB** para las 822 alertas y el `memory_limit` de staging es **256 MB**; la petición moría y Livewire tapaba la pantalla con su modal de error, el «recuadro negro». `Exportacion::pdf()` corta las tablas en `FILAS_PDF = 500` (142 MB, unas 23 páginas) con un aviso que manda al Excel o al CSV, que llevan todas las filas, y numera las páginas con dompdf («Página X de Y»; el contador CSS `pages` salía «1 / 0»).
- **Decisiones:** cortar el PDF en vez de subir el `memory_limit`: la memoria de dompdf crece con las filas (la fuente no la baja) y un PDF de cientos de páginas no sirve para imprimir.
- **Pendiente:** nada.

---

### 2026-11-01 · RF06 · Calendario de evaluaciones con Google Calendar
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#393 · **PR:** → test (sobre el de RF21) · **Rama:** `academica-rf06-calendario`
- **Hecho:**
  - `CalendarioPeriodoService`: agregar y eliminar fechas del calendario de evaluaciones (`period_calendar_events`) y sincronizarlas, con su estado en `google_sync_refs`.
  - Jobs `SincronizarEventoDePeriodo` y `BorrarEventoDePeriodo` en la cola `google`; `CalendarGateway` suma `sincronizarEvento()` y `cancelarClase()` pasa a `borrarEvento()`.
  - Tarjeta «Calendario de evaluaciones» en el Detalle del período, con el estado de cada fecha.
  - El Administrador conecta su Calendar en Mi perfil › Google Calendar (la página acepta `videoconferencias.gestionar` o `periodos.gestionar`).
- **CP cubiertos:** CP-RF06-04 → `automatizado` (`CalendarioPeriodoTest`). RF06 → `implementado`.
- **Decisiones:** [[ADR-068 Calendario de evaluaciones por ámbito]] (pendiente): un evento por fecha en el calendario del Administrador, invitados ocultos, sin fin = todo el día; no se modifica el calendario de un período cerrado.
- **Pendiente:** el límite de invitados por evento de Google con un período grande; probarlo contra Google real en staging.

---

### 2026-11-01 · RNF09 · Integraciones de solo lectura
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** sin OP# (defecto encontrado al revisar las integraciones del MVP) · **PR:** → test · **Rama:** `identidad-integraciones-env`
- **Hecho:** la pantalla Integraciones guardaba cifradas en `integrations` unas credenciales que ningún código leía. Ahora muestra la configuración en uso de Google Workspace y del correo, sacada de `config/services.php` y `config/mail.php`, con el nombre de cada variable del `.env`; los secretos solo aparecen como «Definido» o «Vacío», y ya no ofrece guardar.
- **Decisiones:** se mantiene la del 17/10 (RF01): las credenciales salen del `.env`. La tabla `integrations` queda en el ER sin uso; `DemoAcademicSeeder` todavía la siembra.
- **Pendiente:** decidir si se elimina la tabla `integrations` del ER.

---

### 2026-11-01 · RF21 · Google Calendar real con el OAuth del docente
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#453 · **PR:** → test · **Rama:** `asistencia-rf21-google`
- **Hecho:**
  - `CalendarConexionService`: autorización incremental `calendar.events` con `access_type=offline`, con el mismo cliente del inicio de sesión; refresh token cifrado en `users.google_calendar_token`. Mi perfil › Google Calendar para conectar y desconectar (revoca en Google).
  - `CalendarGoogle`: `events.insert` con Meet en el calendario del docente e invitación a los matriculados, y `events.delete` cuando se aprueba la cancelación del día (RF32) con el job `CancelarEventoDeClase` en la cola `google`.
  - `php artisan google:calendar-prueba <correo>` y el paso de Calendar en `google:verificar`. Probado contra Google real el 01/11/2026.
  - Flujo de navegador 5 (reporte de Meet) en `tests/Browser/ReporteMeetTest.php`.
- **CP cubiertos:** CP-RF21-01 (Google simulado y prueba manual real), CP-RF21-04 en navegador. RF21 → `implementado`.
- **Decisiones:**
  - **Solo @ues.edu.sv:** el servidor valida `hd`, `email_verified`, la audiencia y que la cuenta autorizada sea la del docente; a los correos de otros dominios no se les invita.
  - Se activa con `GOOGLE_CALENDAR_ENABLED`; apagado, todo sigue como antes. Si el docente no conectó su cuenta, la clase se programa sin evento.
  - Si Google revoca el permiso (`invalid_grant`), se borra el token para pedirlo de nuevo.
  - Al cancelar, el evento se busca en el calendario de cada docente vigente de la sección (primero el responsable), porque `video_sessions` no guarda quién lo creó.
  - Un cambio de modalidad no borra el evento. Reprogramar (`events.patch`) queda fuera: la app no permite editar una clase virtual.
- **Pendiente:**
  - Cliente OAuth de TI para producción (pantalla Interna, Calendar API, URI de producción y staging).
  - CP-RF06-04 (calendario de evaluaciones): decidir el calendario y la pantalla para definirlo.
  - La auditoría de `users` guarda el token cifrado en `old_values`/`new_values`, igual que el hash de la contraseña.

---

### 2026-11-01 · RF18 · Progreso por unidad y reglas de finalización
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#441 · **PR:** [#101](https://github.com/0kev0/lms-ues/pull/101) → test · **Rama:** `calificaciones-rf18-progreso`
- **Hecho:**
  - `ProgresoService` (Contenidos): valida y describe en palabras las cuatro reglas (`opened`, `min_score`, `submission`, `forum_posts`), completa de forma retroactiva a quien ya las cumple y calcula el avance por unidad con el alcance de `progreso.ver`.
  - Conectado a `ContenidoService`: `agregarRecurso()` guarda el modo y la regla, `cambiarRegla()` la edita y `registrarApertura()` solo completa con la regla `opened`. Al importar de un período anterior, la regla que apunta a otra sección no se copia.
  - Eventos nuevos `EntregaRealizada` e `IntentoCalificado` (Evaluaciones) y cuatro listeners en Contenidos (cola `default`) para `NotasPublicadas`, `IntentoCalificado`, `EntregaRealizada` y `RespuestaPublicada`.
  - Interfaz: paso «Cómo se completa» en Nuevo recurso, el texto de la regla para el estudiante y su edición para el docente en la página del recurso, y la pestaña **Progreso** de la Vista de sección.
- **CP cubiertos:** CP-RF18-02 → `automatizado` (`ProgresoTest`, 7 pruebas con las cuatro reglas, la validación y el alcance por rol). RF18 → `implementado`; Plan 5 cerrado.
- **Decisiones:** [[ADR-067 Formato de la regla de finalización de un recurso]]: una regla por recurso, se evalúa cuando ocurre el evento, lo completado **no se desmarca** y una regla que apunta a otra sección no se copia al importar.
- **Pendiente:** capturas de las pantallas nuevas en los manuales; repetir la suite en PostgreSQL antes del PR de `test` a `main`.

---

### 2026-11-01 · RF33 · Indicadores de desempeño docente con las fórmulas del criterio
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#509 (por confirmar) · **PR:** → test · **Rama:** `reportes-rf33-kpis-docentes`
- **Hecho:**
  - `DesempenoService::puntualidad()`: minutos promedio de retraso (abrir antes cuenta 0) y % a tiempo (≤ 15 min). Compara con el bloque más cercano del día; excluye las sesiones de días sin clase. Antes solo daba el % y tomaba el primer bloque del día.
  - `DesempenoService::revision()`: horas desde la entrega (último intento) hasta `grades.published_at`, con las entregas sin nota publicada aparte; en un equipo, la primera publicación entre sus integrantes. Antes medía hasta la *creación* de la nota y sobre una muestra de 500 filas.
  - Panel y exportación: columnas Retraso, A tiempo, Revisión y Sin publicar; el aviso «Indicadores provisionales» pasa a explicar el cálculo.
- **CP cubiertos:** CP-RF33-02, 03 y 04 → `automatizado` (`DesempenoDocenteTest` y una prueba nueva en `ExportacionesTest` que lee el Excel). RF33 → `implementado`.
- **Decisiones (del usuario, 01/11/2026):** minutos y % a tiempo juntos; las entregas sin nota publicada se excluyen del promedio y se cuentan aparte; una sesión en un día sin clase no cuenta. Sin ADR: concretan lo que el criterio ya definía.

### 2026-11-01 · RF13 · Seguimiento de acceso a recursos
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#421 (por confirmar) · **PR:** → test · **Rama:** `contenidos-rf13-seguimiento-recursos`
- **Hecho:**
  - Job `RegistrarAccesoRecurso` (cola `default`): apertura con ventana de 5 minutos, descarga y suma de tiempo en un solo `UPDATE`.
  - `ContenidoService`: `abrir()` pasa a ser `recurso()`, de solo lectura, y se agregan `registrarApertura()`, `registrarDescarga()` y `registrarTiempo()` (tope de 35 s por envío).
  - Corregido: la apertura se registraba en **cada petición Livewire** de la página y otra vez al cargar el PDF incrustado, y las descargas quedaban como `open`. El .zip de una carpeta no se registraba. `duration_seconds` nunca se escribía.
  - Página del recurso: latido con Alpine cada 30 s con la pestaña visible, al ocultarla y antes de salir con `wire:navigate`; solo para el estudiante.
  - Analítica: selector **Aperturas · Tiempo**, el tiempo en cada celda y la tarjeta de tiempo total.
- **CP cubiertos:** CP-RF13-01, 02 y 03 → `automatizado` (8 pruebas en `SeguimientoRecursosTest`). El latido se probó una vez en Chromium: a los 30 s registró 30 segundos, sin errores de JavaScript. RF13 → `implementado`.
- **Decisiones (del usuario, 01/11/2026):** latido cada 30 s, ventana de 5 minutos para no contar dos veces la misma visita, cola `default`. Sin ADR: son parámetros; quedan en las reglas de [[RF13]].
- **Pendiente:** con 500 estudiantes a la vez, el latido son unas 17 peticiones por segundo; conviene incluirlo en el escenario de navegación de k6 ([[Pruebas de carga]]).

### 2026-11-01 · RF14 · Reutilización con evaluaciones sin programar (ADR-066)
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP#425 (por confirmar) · **PR:** → test · **Rama:** `contenidos-rf14-reutilizacion`, sobre `evaluaciones-rf15-rubricas`
- **Hecho:**
  - Migración `2026_11_01_000100_make_assessment_dates_optional`: `opens_at` y `closes_at` aceptan NULL, las dos o ninguna (CHECK en PostgreSQL).
  - `ContenidoService::importar()`: solo de un período anterior, una vez por sección de origen, **copia física** de cada archivo y de cada carpeta (corrige el defecto del `file_path` compartido), y llama a `ReutilizacionService`. Devuelve recursos y evaluaciones copiados.
  - `ReutilizacionService` (Evaluaciones): copia tareas y cuestionarios sin fechas ni cómputo, con sus ajustes; la rúbrica se copia y el banco de otro docente se comparte.
  - `ActividadService::sinProgramar()` y `programar()`; en Actividades, la tarjeta «Sin programar» con el modal «Programar» (fechas, cómputo, peso y, si la tarea tiene gracia, su fecha).
- **CP cubiertos:** CP-RF14-01 y CP-RF14-02 → `automatizado` (6 pruebas en `ReutilizacionTest`, una solo en PostgreSQL). RF14 → `implementado`.
- **Decisiones (del usuario, 01/11/2026):** fechas opcionales, sin cómputo hasta programarla, banco compartido y una sola importación por sección. «Solo de un período anterior» sale del texto de [[RF14]]. Registrado en [[ADR-066 Evaluaciones sin programar al reutilizar]] (pendiente).
- **Hallazgo:** no hizo falta filtrar las 34 consultas que usan las fechas: todas las de tareas y cuestionarios cruzan con `grade_items`, así que una evaluación sin cómputo ya queda fuera para el estudiante. La prueba lo comprueba en Actividades, el detalle de la tarea y el inicio del cuestionario.
- **Pendiente:** aceptar ADR-066. Fusionar antes el PR de rúbricas (#94), del que depende esta rama.

### 2026-11-01 · RF15 · La rúbrica es parte de la tarea (ADR-065)
- **Autor:** IA (Claude Code), a pedido del usuario
- **OpenProject:** OP# pendiente · **PR:** → test · **Rama:** `evaluaciones-rf15-rubricas`
- **Hecho:**
  - `RubricaService` nuevo: `validar()`, `insertar()`, `criterios()`, `editable()` y `guardar()` (agrega, cambia o quita). Exige `tareas.gestionar` con alcance S.
  - «Nueva tarea»: interruptor **Calificar con rúbrica** con el editor de criterios; `ActividadService::crearTarea()` recibe los criterios en vez de un id de rúbrica.
  - Entregas: tarjeta **Rúbrica** para agregarla, cambiarla o quitarla hasta la primera calificación; después queda fija y lo dice.
  - Se quitan la página `/rubricas`, su entrada en el menú, `CuestionarioService::rubricas()` y `crearRubrica()`, y el permiso `rubricas.gestionar` (seeder, Matriz RBAC y la migración `2026_11_01_000000_revoke_rubrics_manage_permission` para las bases ya sembradas).
  - `DemoAcademicSeeder` crea una rúbrica por tarea (antes una por docente, compartida).
- **CP cubiertos:** CP-RF15-06 con 5 pruebas nuevas en `RubricaDeTareaTest`. Se reemplazaron, con el visto bueno del usuario, dos pruebas de `EvaluacionesYNotasTest` que abrían `/rubricas`; `RevisionTest` arma la rúbrica en la tarea.
- **Decisiones (del usuario, 01/11/2026):** la rúbrica se configura en la actividad, no en una vista aparte; la llevan las tareas (reportes, exposiciones, proyectos) y **no los exámenes parciales**; queda fija desde la primera calificación; se quitan la página y el permiso. Registrado en [[ADR-065 La rúbrica es parte de la tarea]] (pendiente), que complementa [[ADR-006 Rúbricas y calificación anónima]].
- **Pendiente:** aceptar ADR-065. En staging, la migración quita el permiso, pero las tareas sembradas antes siguen compartiendo una rúbrica por docente hasta volver a sembrar; el bloqueo de edición las cubre. Después: [[RF14]], que copiará la rúbrica junto con su tarea.

### 2026-10-31 · RF09, RF17 · Lectura por cadena del cambio de grupo (ADR-062)
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP# pendiente · **PR:** → test · **Rama:** `calificaciones-rf09-cadena-notas`
- **Hecho:**
  - `CalificacionService`: `cadena()`, `notasDeCadena()`, `cadenasDeSeccion()`, `heredadasEfectivas()` y `tieneNotasDelComputo()`. La cadena entra en `boleta()`, `libro()`, `misNotas()`, `congelarResultados()` y `recalcularResultado()`, y en `RiesgoService::notaProyectada()`.
  - `enrollment_results.policy_snapshot` gana `inherited_from` **solo** cuando hubo herencia: así el resultado congelado dice de qué grupo salió cada cómputo sin cambiar de formato para los demás.
  - `ActividadService::entregasDeCadena()`: entregas del grupo anterior, de solo lectura, con su grupo de origen.
  - El libro y la boleta marcan el cómputo heredado con su grupo y no lo dejan editar; «Mis notas» también.
- **CP cubiertos:** CP-RF09-08 pasa a `pasa`, verificado en SQLite y en PostgreSQL 17. Refuerza CP-RF17-05 y CP-RF34-08.
- **Decisiones:** [[ADR-062 Notas y entregas en un cambio de grupo]] **corregida** antes de implementarla. Su redacción original pedía encadenar «por actividad», y el esquema lo impide: `grade_items` lleva `section_id` y `grades` tiene dos claves foráneas compuestas que obligan a que la nota, su actividad y la matrícula sean de la misma sección. Lo comparable entre grupos es el **cómputo**, porque los dos copian `grading_component_templates` del período. También se quitó de la ADR la cláusula del progreso: `ProgresoService` no existe y los recursos son propios de cada sección; queda anotado en [[RF18]].
- **Corregido de paso:** [[RF09]] seguía diciendo que Soporte Técnico no inscribe, afirmación que [[ADR-037 Jerarquía de creación y asignación de roles]] (punto 6) reemplazó y que es la que originó el CP-RF09-05 obsoleto. El PHPDoc de `MatriculaService::aprobarCambio()` también seguía declarando el asunto como pendiente.
- **Deuda de tipado:** `phpstan-baseline.neon` baja de 126 a 124. El cambio sacó 6 errores de genéricos y se arreglaron de raíz (patrón `@template TNota of object`, anotación real del retorno de `boleta()` y un `map()` con closure tipada sustituido por `foreach`); dos entradas del baseline quedaron obsoletas y se quitaron.
- **Pendiente:** aceptar ADR-062. El avance por unidad de [[RF18]] tendrá que decidir qué hace con la cadena cuando exista `ProgresoService`.
### 2026-10-31 · RF04, RF07, RF08, RF09, RF25 · Casos que solo esperaban su prueba
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP# pendiente · **PR:** → test
- **Hecho:**
  - Revisión del repositorio contra el vault: [[realeseMVPFaltante|Falta para el MVP 1.0]], con lo que queda para el release del 25/11.
  - Pruebas Pest de 5 casos cuyo código ya estaba: búsqueda sin tildes y prerrequisitos con casillas (RF07), topes de docentes y de horas (RF08), roles incompatibles con Estudiante (RF04) y formato de fecha y envío duplicado de los reportes (RF25). Ningún cambio en código de producción.
- **CP cubiertos:** CP-RF04-22, CP-RF07-05, CP-RF07-07, CP-RF08-06, CP-RF25-04 y CP-RF25-05 pasan a `automatizado`.
- **Decisiones:**
  - **CP-RF09-05 queda `sustituido` por CP-RF09-11.** Pedía rechazar la matrícula de Soporte citando [[ADR-032 Ajustes por el documento de roles]], pero [[ADR-037 Jerarquía de creación y asignación de roles]] **reemplaza expresamente ese punto** y le da `matricula.gestionar` con alcance G, como ya recoge la [[Matriz RBAC]]. Automatizarlo habría exigido revertir ADR-037.
  - [[ADR-062 Notas y entregas en un cambio de grupo]] (pendiente de aceptar): al aprobar un cambio de grupo **no se mueve ni se copia ninguna fila**; la lectura recorre la cadena `transferred_to_id`. Lo decide la clave foránea compuesta `(enrollment_id, section_id)` de `grades`, `submissions` y `attendance_records`: mover una nota obligaría a cambiarle la sección. La asistencia **no** suma la cadena, porque sus sesiones son de la sección anterior.
- **Defecto encontrado:** `phpunit.xml` declara `Browser` como testsuite por defecto, así que `composer test` (`pest --parallel`) y `php artisan test` arrastran Playwright y **se cuelgan sin aviso** (una corrida quedó 29 min sin escribir una línea). Mientras no se arregle, verifique con `vendor/bin/pest --parallel --testsuite=Unit,Feature,Arch` y deje `vendor/bin/pest tests/Browser` como paso aparte, como describe la [[Estrategia de pruebas]].
- **Pendiente:** CP-RF09-08 deja de ser «falta la prueba» y pasa a función: la lectura por cadena vive en [[RF17]] y [[RF18]]. Aceptar ADR-062. Registrar el defecto de `phpunit.xml` en OpenProject.

---

### 2026-10-31 · H2 adelantado · RF32-02, RF19-02, RF15-05 y CalendarGateway
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP# pendiente · **PR:** uno por rama → test
- **Hecho:** cambio de modalidad que decide el método de asistencia (RF32), importación de asistencia por sesión (RF19), entrega grupal (RF15) y `CalendarGateway` con implementación falsa (RF21). También: `composer test` sin navegador, y RF05, RF26, RF09, RF15, RF19 y RF32 a `implementado` (**26 de 35**).
- **CP cubiertos:** CP-RF32-02, CP-RF19-02, CP-RF15-05 y CP-RF21-01 (este, contra el falso), en `automatizado`.
- **Decisiones del usuario (31/10/2026):**
  - **Equipos (RF15):** los arma el docente; la nota del equipo se copia a cada integrante y el docente puede ajustar la de uno, con motivo. Volver a calificar copia otra vez a todos. Un equipo que ya entregó no se elimina.
  - **Importar asistencia (RF19):** un archivo por sesión con «Carné» y «Estado», con vista previa; las filas de quien no está matriculado en el grupo se rechazan y el resto se aplica. Solo con la sesión cerrada.
- **Decisiones de diseño:** una entrega grupal guarda `team_id` y deja `enrollment_id` vacío (CHECK `submissions_owner`); para saber si una matrícula entregó se usa `EquipoService::deMatricula()`. Con un cambio a virtual aprobado no se abre QR ([[ADR-003 Asistencia virtual por reporte de Meet]]). Calendar queda con `CalendarDesactivado` hasta el OAuth ([[ADR-018 Calendar con OAuth del docente]]): no se inventan enlaces de Meet.
- **Pendiente:** la implementación de Google de `CalendarGateway`; pcov para medir la cobertura.

---

### 2026-10-31 · QA sesión 5 · Respuesta al informe de los roles Jefe y Soporte
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP# pendiente · **PR:** #83, #84 y este → test
- **Hecho:** auditoría en palabras, oferta con carrera y ciclo, aviso del cierre, etiquetas del panel, reportes con carrera, selector de período (R-12), gráficos con Chart.js (R-11), Ayuda sin emojis y cabecera común en todo Excel. [[Respuesta al informe de QA (sesión 5, roles Jefe y Soporte)]].
- **CP cubiertos:** CP-RF25-06, CP-RF25-07 y CP-RF25-08, nuevos, en `automatizado`; se ajustaron CP-RF05-02 y CP-RF25-04 a las columnas nuevas.
- **Decisiones:**
  - **Chart.js** como dependencia de Vite (`devDependencies`, versión exacta), igual que Leaflet: funciona sin Internet y el proyecto sigue sin scripts externos.
  - Los gráficos son de **una sola serie** (la medida principal de cada reporte) y la tabla sigue debajo como vista accesible y completa.
  - Los **envíos programados** siguen con el período en curso y todas las carreras del alcance: no guardan los filtros de pantalla.
  - `Alcance::carrerasVisibles()` decide por qué carreras puede filtrar cada rol (G todas, D su departamento, C las que coordina); lo comparten Oferta y Reportes.
  - **Formato estándar del Excel**, decidido por el usuario: tres filas de cabecera (facultad, reporte con período o filtros en palabras, quién y cuándo) en `Exportacion::xlsx()`, para las seis exportaciones; el CSV sin cabecera. Anotado en [[ADR-049 Exportación a Excel y PDF]].
  - [[ADR-063 Reactivar una matrícula retirada por error]] y [[ADR-064 Duración del ciclo académico]] quedan pendientes.
- **Pendiente:** que la QA repruebe en staging.

---

### 2026-10-31 · Calidad · Se quita GitHub Actions
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#375 · **PR:** → test
- **Hecho:** se borró `.github/workflows/ci.yml` y se desactivó Actions en el repositorio. Las 56 ejecuciones habían terminado en `startup_failure`; un workflow mínimo falló igual, así que el bloqueo era de la cuenta y no del archivo. Antes, en el #76, la suite completa quedó en verde en PostgreSQL (997 pasan).
- **Decisiones:** [[ADR-061 Verificación local sin GitHub Actions]] (pendiente): `composer check` en local antes del PR a `test` y, antes de `main`, también PostgreSQL 17 y navegador. Las ramas y los PR de ADR-014 siguen igual.
- **Pendiente:** aceptar ADR-061; decidir si se vuelve a una CI automática (facturación, repositorio público o runner propio).

---

### 2026-10-30 · QA sesión 4 · Respuesta al informe del rol Administrador
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP# pendiente · **PR:** #74 → test
- **Hecho:** cuatro defectos corregidos (auditoría duplicada del catálogo, aviso de correo repetido, salida de la proyección del QR, «Desactivar» oculto por un nombre sin espacios) y [[Respuesta al informe de QA (sesión 4, rol Administrador)]].
- **CP cubiertos:** CP-RF07-06 y el nuevo CP-RF04-23 pasan a `automatizado`; se reforzaron CP-RF04-04 y CP-RF20-02.
- **Decisiones:**
  - [[ADR-060 Formato de los nombres de usuario]] (pendiente de aceptar): dos campos, sin palabras de más de 25 letras ni más de 60 caracteres, guardados como nombre propio.
  - Las áreas de asistencia siguen siendo una geocerca del campus: no se asignan a carrera ni asignatura ([[RF26]]).
  - «Colas» pasa a «Monitoreo técnico» y Horizon abre en otra pestaña, con explicación en Integraciones. No cambian los permisos.
- **Pendiente:** el enlace directo a Mi perfil → Dispositivo en el rechazo `invalid_device`; confirmar en staging el aviso de RF27.

---

### 2026-10-30 · RF06 · Reapertura del período y casos chicos
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#393 · **PR:** → test
- **Hecho:**
  - `PeriodoService::reabrir()`: solo el Administrador, motivo obligatorio (a la auditoría), período cerrado y sin otro en curso. Botón **Reabrir período** con modal en el detalle.
  - Aviso `period.reopened` a los Coordinadores vigentes (evento `PeriodoReabierto`), con migración de datos para el catálogo.
  - El cruce de fechas (H-03) y la acción visible en la lista (P-01) ya estaban hechos desde la sesión 3 de QA: solo faltaban sus pruebas.
- **CP cubiertos:** CP-RF06-02, 05, 07 y 08 pasan a `automatizado`.
- **Decisiones:** [[ADR-059 Reapertura de un período cerrado]] (pendiente de aceptar): reabrir conserva las autorizaciones de cierre y los resultados congelados, que se recalculan al volver a cerrar.
- **Pendiente:** CP-RF06-04 (Google Calendar, con H3). RF06 sigue `en-desarrollo` hasta ese caso.

---

### 2026-10-28 · RF17 · Pesos de los cómputos, riesgo académico y corrección de notas
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#437 · **PR:** → test
- **Hecho:**
  - Al crear la sección se copian los cómputos del período (y una migración los copia a las secciones que no los tenían). El docente ajusta los pesos en **Notas → Pesos**; el acta no se publica si no suman 100 % en cada nivel.
  - Alerta `grade_risk` en vivo con la nota proyectada, con aviso al estudiante; se ve en el panel del docente.
  - `corregir()`: `AuthorizationException`, el Coordinador solo con el período cerrado, motivo validado en el Service, recálculo de `enrollment_results` y aviso al estudiante.
- **CP cubiertos:** CP-RF17-01, 05, 08 y 11 pasan a `automatizado`; los demás siguen en `automatizado` hasta la corrida de la CI en PostgreSQL.
- **Decisiones:**
  - [[ADR-057 Pesos de los cómputos por el docente]] y [[ADR-058 Fórmula del riesgo académico]] (pendientes de aceptar).
  - La corrección de una nota avisa con el evento `grade.published`; no se creó un `event_key` nuevo.
  - El riesgo no se evalúa con el período cerrado: el resultado ya está congelado.
- **Pendiente:**
  - Cobertura y pruebas de PostgreSQL en la CI (en local no hay pcov).
  - Bugs: el permiso `calificaciones.corregir` del Docente no se usa y `calificar()` sobre una nota publicada no avisa al estudiante; `grading_components` y `grade_items` no se auditan.

---

### 2026-10-28 · RF26 · Áreas de asistencia por polígono con Leaflet
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#481 · **PR:** → test
- **Hecho:** el Administrador define un área por centro y radio **o por polígono**, dibujándola sobre un mapa (Leaflet + OpenStreetMap). El QR valida contra los polígonos como contra los círculos y guarda la distancia al borde. CHECK `attendance_areas_shape` en PostgreSQL.
- **CP cubiertos:** CP-RF26-01 y CP-RF26-02 en `automatizado`; pasarán a `pasa` con una corrida de la CI, que no arranca desde el PR #67.
- **Decisiones:**
  - [[ADR-055 Leaflet y OpenStreetMap para las áreas de asistencia]] (pendiente de aceptar): Leaflet 1.9.4 sin plugins y el margen del GPS dentro del dibujo, sin columna nueva.
  - **Restricciones nuevas sobre tablas con datos, `NOT VALID` cuando el motor lo permite.** El 28/10 el EXCLUDE de períodos tumbó el despliegue de staging porque ya había filas que lo violaban (PR #70); un CHECK admite `NOT VALID` y un EXCLUDE no, así que el EXCLUDE comprueba los datos antes de crearse.
- **Pendiente:** RF26 sigue `en-desarrollo` hasta tener la corrida de la CI; revisión visual del mapa en staging.

### 2026-10-27 · RF03, RF05, RF20, RF34 · La CI que faltaba y la primera corrida en PostgreSQL
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#381, OP#389, OP#449, OP#513 · **PR:** #67 → test
- **Hecho:** los cuatro RF estaban implementados pero no se podían cerrar, porque parte de su evidencia vivía en pruebas que se saltan fuera de PostgreSQL **y que no corrían en ninguna parte**: `.github/` no tenía `workflows/`. Se creó la CI con la matriz SQLite + PostgreSQL 17, se instaló Larastan con una línea base de 142 errores heredados, y se corrió la suite por primera vez contra el motor de producción: **843 pasan y 5 se saltan**, frente a 819 y 29 en SQLite. Se corrigieron los 13 fallos que salieron y los huecos de trazabilidad de los cuatro RF.
- **CP cubiertos:** RF03 (7), RF20 (13) y RF34 (10) pasan a `pasa` y los tres RF a `implementado`. RF05 **no**: su criterio de RLS sigue sin evidencia.
- **Decisiones:**
  - **La CI informa y no bloquea mientras el proyecto está en staging** (`continue-on-error` en los cuatro jobs). Hay que quitarlo antes de producción: [[RNF07]] pide que la cobertura mínima del 70 % bloquee el merge.
  - **Larastan entra con línea base**, no corrigiendo los 142 errores de tipado de golpe: así el nivel 5 queda en verde y cualquier error nuevo aparece. La deuda se paga por módulo.
  - **La pata de PostgreSQL corre con `--processes=4`**: con 16, cada worker abre su conexión y su base y el servidor se queda sin clientes ni memoria compartida.
  - **Un aula por sección en los datos de prueba**, porque el `EXCLUDE` prohíbe dos clases en la misma aula a la misma hora; así se sigue probando el choque de horario del estudiante sin chocar en el aula.
- **Hallazgos de implementación:**
  - **La RLS de `audit_logs` nunca se había verificado.** PostgreSQL deja que un rol `SUPERUSER` o con `BYPASSRLS` se salte las políticas incluso con `FORCE ROW LEVEL SECURITY`, y el entorno se conecta con `lms`, que lo es. La política está bien escrita y activa; falta el rol `app_rw` de [[Seguridad]] y una conexión restringida. La precondición quedó explícita en `tests/Pest.php::rlsNoVerificable()`.
  - **`DbContext::within()` borra el contexto en vez de restaurarlo,** así que anidar dos `DbContext::run()` o `system()` rompe el externo. Falla ruidosamente con `AC900`, no corrompe la auditoría, pero es una trampa para cualquier Service que llame a otro dentro de su transacción.
  - **`tests/Browser/FlujoExamenTest.php` se cuelga** y era lo que colgaba `php artisan test` entero.
- **Pendiente:** RF05 espera el rol restringido para su RLS; la discrepancia del área de [[ADR-046 Lector QR en la página y rotación por polling]] §4 sigue sin decidir; siete defectos registrados en OpenProject.

### 2026-10-27 · RF29 · Fecha límite de inscripción y prórrogas
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#493 · **PR:** #66 → test
- **Hecho:** la matrícula tiene **fecha límite por período y departamento** (`enrollment_deadlines`) y **prórrogas por estudiante** (`enrollment_extensions`), ambas auditadas con triggers. «Matrícula · Plazos» (`/matricula/plazos`) fija la fecha, prorroga el departamento y otorga prórrogas. El control vive en el camino común de `MatriculaService`, así que vencido el plazo no inscribe nadie —ni con clave ni manualmente— sin prórroga; la página de inscripción y la de matrícula muestran la fecha que rige.
- **CP cubiertos:** CP-RF29-14 a 20, todos nuevos (37 pruebas en `tests/Feature/Academica/PlazoMatriculaTest.php`).
- **Decisiones (usuario, 26/10/2026):** [[ADR-051 Fecha límite de inscripción por departamento]], **aceptada el 27/10/2026**.
  - **Manda el departamento de la carrera del estudiante**, no el de la asignatura, para que todas sus materias cierren el mismo día.
  - **Sin fecha fijada** rige el inicio del período + `enrollment_deadline_weeks_default` (2 semanas), como ya hacía `group_change_weeks`.
  - **La prórroga del departamento es solo del Jefe** y conserva la fecha original en `deadline`; la prórroga por estudiante la dan el Jefe y el Coordinador. Las dos exigen motivo.
- **Pendiente:** la auditoría de `enrollment_extensions` queda con `department_id` NULL porque `fn_audit_department` no la resuelve, así que por ahora solo la ve el Administrador. `larastan/larastan` sigue sin estar en `require-dev`, así que `composer check` no puede correr Larastan.

### 2026-10-26 · RF29 · Inscripción de materias por catálogo y claves compartidas
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#493
- **Hecho:** «Inscripción de materias» (`/inscripcion`) lista las materias de la carrera del estudiante del ciclo en curso; el detalle (`/inscripcion/{asignatura}`) muestra la descripción, los grupos y «Inscribirme con clave». «Mis materias» pierde su modal. El docente edita la descripción desde Contenidos. El Coordinador y el Jefe escriben o generan la clave, o aplican una misma a todas las materias de su ámbito.
- **CP cubiertos:** CP-RF29-01 a 13 (03 y 08 a 13, nuevos o hasta ahora sin prueba).
- **Decisiones (usuario, 26/10/2026):** [[ADR-050 Inscripción por catálogo y claves compartidas]], **pendiente de aceptar**.
  - **Ciclo del período por el mes central** contra `ciclo_ii_mes_inicio` (8): el ciclo II suele empezar a finales de julio. Ciclo I lista los ciclos impares del plan; ciclo II, los pares.
  - **Descripción en `subjects.description`**, editable por el docente con un grupo de la asignatura (`contenidos.gestionar`, S).
  - **El Jefe gestiona claves (D)**; la clave común guarda un solo hash repetido por asignatura.
- **Pendiente:** la «fecha límite de inscripción» de [[RF29]] contra [[ADR-037 Jerarquía de creación y asignación de roles]]. `larastan/larastan` no está en `require-dev`, así que `composer check` no puede correr Larastan.

### 2026-10-25 · RF17, RF33, RF06, RF25 · Exportación a Excel y PDF
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar
- **Hecho:** el libro de calificaciones, el panel de desempeño docente, el período y los reportes se exportan a **Excel, PDF y CSV**. `App\Support\Exportacion` concentra los tres formatos y `<x-ui.exportar>` es el botón único; los PDF usan una hoja común con el membrete de la facultad, la fecha, quién lo generó y el número de página. Auditoría y Diagnóstico pasan a Excel + CSV.
- **CP cubiertos:** CP-RF17-12, CP-RF33-05, CP-RF06-06, CP-RF25-03 y CP-RF05-08, todos nuevos.
- **Decisiones (usuario, 25/10/2026):** [[ADR-049 Exportación a Excel y PDF]], **pendiente de aceptar**.
  - **`openspout/openspout` en vez de `maatwebsite/excel`:** escribe `.xlsx` en streaming y no arrastra PhpSpreadsheet, que es pesado para producir tablas sin fórmulas. Supera a [[ADR-016 Paquetes]] en la escritura de Excel.
  - **`barryvdh/laravel-dompdf` se instala por fin.** Ya estaba elegido en [[ADR-016 Paquetes]] desde el 10/10 y nunca se había usado; es, de hecho, lo que pidieron con nombre en [[Entrevista E3]] y [[Entrevista E7]] («descarga de calificaciones en PDF para publicarlas»). Nadie pidió `.xlsx`: se agrega porque lo nombran los RF.
  - **El PDF no se ofrece donde la exportación puede traer miles de filas** (Auditoría, Diagnóstico: hasta 10 000). CSV y Excel se escriben en streaming; dompdf arma el documento entero en memoria. Un acta de cuarenta estudiantes es su caso; diez mil eventos de auditoría, no.
- **Hallazgo de implementación:** Livewire solo reconoce como descarga un `StreamedResponse` o un `BinaryFileResponse`. Con la respuesta normal de dompdf intentaba meter el PDF dentro del JSON de la respuesta y fallaba con «Malformed UTF-8 characters». Las tres exportaciones devuelven `StreamedResponse`.
- **Pendiente:** los «gráficos» que menciona [[RF25]] siguen sin resolver: se exporta la tabla, no la gráfica. Y `spatie/laravel-backup` es lo último de [[ADR-016 Paquetes]] sin instalar ni superar.


### 2026-10-25 · RF04 · ADR-048 aceptada: la importación lee Excel
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar
- **Hecho:** `ImportacionService::leer()` pasa a `App\Support\HojaCalculo`, y la pantalla admite `.csv` y `.xlsx`. El Coordinador sube el libro tal como lo guarda Excel, sin convertirlo a mano. La plantilla descargable sigue en CSV, porque `HojaCalculo` solo lee y la pantalla acepta las dos cosas igual.
- **CP cubiertos:** CP-RF04-21, nuevo.
- **Decisiones (usuario, 25/10/2026):**
  - **[[ADR-048 Lector propio de hojas para el reporte de Meet]] queda aceptada**, con el alcance ampliado: el lector no es solo del reporte de Meet, vale para cualquier entrada de hoja de cálculo.
  - **[[ADR-016 Paquetes]] queda marcada como superada en casi todo.** Es el hallazgo de fondo: de sus cinco elecciones solo seguía viva la de la interfaz. WebAuthn lo superó ADR-045, la auditoría ADR-027, la lectura de Excel ADR-048 y la escritura ADR-049; `spatie/laravel-backup` sigue sin instalar y sin ADR que lo supere. Nadie la había contradicho de frente: simplemente se fue por otro lado cuatro veces, y por eso el vault nombraba tres librerías distintas para hojas de cálculo.
- **Pendiente:** decidir qué pasa con `spatie/laravel-backup`, que es lo último de ADR-016 sin resolver.


### 2026-10-25 · RF04 · Plantilla descargable para la importación de estudiantes
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar
- **Hecho:** la pantalla de importación abre con un paso **1. Plantilla** y un botón que descarga `plantilla-importacion-estudiantes.csv`: los ocho encabezados en orden y una fila de ejemplo con una **carrera y un plan reales del alcance de quien la descarga**, de modo que el ejemplo pasa la validación en vez de ser relleno. Con BOM, para que Excel la abra con los acentos bien.
- **CP cubiertos:** CP-RF04-20, nuevo.
- **Decisiones (usuario, 25/10/2026):**
  - La plantilla es **CSV, no `.xlsx`**. `App\Support\HojaCalculo` solo lee; escribir un `.xlsx` de verdad sí necesitaría librería, y CSV es justo el formato que la pantalla pide subir.
  - **Habilitar la lectura de `.xlsx` en RF04 queda anotado en [[ADR-048 Lector propio de hojas para el reporte de Meet]], no implementado.** El lector ya existe y el cambio es de dos líneas, pero amplía el alcance de una ADR que sigue **pendiente** y que se aparta de [[ADR-016 Paquetes]], que está aceptada.
- **Pendiente:** aceptar o rechazar ADR-048. El vault nombra tres librerías distintas para hojas de cálculo (`maatwebsite/excel` en ADR-016 y Stack, `openspout/openspout` en el Plan de implementación, ninguna en ADR-048): al aceptarla hay que dejar un solo nombre y separar leer de escribir.


### 2026-10-25 · — · Revisión de diseño de las pantallas (todos los roles)
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar
- **Hecho:** se aplicó la revisión visual de las pantallas de Administrador, Coordinador, Docente, Soporte y Estudiante («Porbando diseño», 25/10/2026). Lo transversal se corrigió en los componentes, no pantalla por pantalla: el fondo y el borde del tema, el alto mínimo de tarjetas y tablas, la alineación de los indicadores, el estado de carga y el aviso de tabla vacía, los botones de fila, la sangría y el resaltado del menú. Lo propio de cada pantalla: filtros que se limpian, buscadores que faltaban, el panel de Soporte, la ayuda y el rendimiento de Auditoría.
- **CP cubiertos:** CP-RF03-06, CP-RF04-07, CP-RF05-05, CP-RF05-06, CP-RF09-10, CP-RF29-04 y CP-RF34-07, todos nuevos.
- **Decisiones (usuario, 25/10/2026):**
  - **El fondo del tema pasa de `#F8F8F8` a `#F1F1F2` y el borde de `#E2E2E2` a `#DCDCDE`.** Era la queja que más se repitió («el div contenedor blanco», en cuatro pantallas distintas): una tarjeta blanca con 1 px de borde sobre un lienzo casi blanco no se leía como tarjeta. Se cambia el token, no las pantallas.
  - **La tarjeta y la tabla con `ajustar` tienen alto mínimo** (36 rem y 26 rem). El alto fijo seguía siendo lo correcto —el título y los indicadores deben quedar a la vista—, pero sin tope se encogía hasta dejar dos filas («tabla re pequeña», «visión limitada»). Cuando la ventana no da, se desplaza `<main>`.
  - **Dentro de una tabla hay un solo juego de botones:** fantasma para lo neutro, peligro para lo destructivo. La acción principal de la pantalla se queda en el encabezado. Antes el mismo tipo de acción cambiaba de color entre tablas.
  - **`/ayuda` no carga mermaid.js.** Los diagramas del vault se traducen a una lista numerada al dibujar el manual. Son 2 MB de JavaScript para dos diagramas de dos manuales, y la lista se lee igual, se imprime y la leen los lectores de pantalla. El vault conserva el Mermaid, que Obsidian sí dibuja.
  - **Colas (Horizon) se abre sin `wire:navigate`.** Con él, el navegador se quedaba con los estilos oscuros del panel y al volver la pantalla salía en negro. Se agregó la marca `externa` al menú para cualquier pantalla fuera de la aplicación Livewire.
  - **Auditoría pagina sin contar** (`simplePaginate`) y guarda media hora la lista de entidades del filtro. La tabla es particionada y de varios millones de filas: el `count(*)` y el `distinct` la recorrían entera en cada render y la pantalla no llegaba a cargar. Se agregó además el índice `audit_logs (occurred_at desc)`, que era el que pedía el orden de la pantalla y no existía.
- **Defecto encontrado fuera de la revisión:** `<x-ui.table vacio="…">` comprobaba el hueco con `isEmpty()`, que cuenta los saltos de línea de un `@foreach` sin elementos como contenido. **El aviso de tabla vacía no salía en ninguna pantalla**; por eso «Actas por validar» parecía trabada en blanco. Ahora usa `hasActualContent()`.
- **Pendiente:** confirmar en producción que el menú ya no salta al navegar (el arreglo trae a la vista la opción activa; en local el `@persist` ya conservaba el desplazamiento) y revisar si la cuenta de emergencia con 2FA de [[ADR-007 Cuenta de emergencia]] entra en el alcance de noviembre.


### 2026-10-25 · RF23 · Los avisos llevan a la pantalla del evento (todos los roles)
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar, igual que la entrada anterior
- **Hecho:** abrir un aviso ahora lo marca leído y lleva al evento que avisa, con un botón **«Ir al evento»** visible. `NotificacionService::abrir()` concentra «marcar leído y devolver la pantalla», y la campana y la bandeja lo usan igual. En la bandeja la fila dejó de ser texto muerto con un enlace «Ver» diminuto: el bloque completo es pulsable y el botón queda a la derecha; en la campana, cada aviso con destino lo anuncia. El mapa de iconos cubre los 19 eventos, no 8.
- **CP cubiertos:** CP-RF23-04, nuevo (`pasa`), y la prueba de RF32 comprueba el destino de la cancelación.
- **Decisiones (usuario, 25/10/2026):**
  - El destino vive en el aviso (`data.url`), que ya existía: no se agregó tabla ni columna. Vale para todos los roles porque la bandeja es la misma para todos.
  - `class.cancelled` era el único evento sin destino: el de una **sección** lleva a esa sección. El de una carrera o de toda la facultad **se queda sin destino a propósito**: no hay una pantalla única y el texto ya trae fecha, alcance y motivo. La bandeja no le muestra el botón en vez de ofrecer un enlace que no explica nada.
  - Un aviso sin destino igual se marca leído al pulsarlo: abrirlo es haberlo visto.
- **Pendiente:** `assessment.created`, `resource.published`, `video_session.upcoming` y `deadline.upcoming` ya tienen destino previsto en su emisor, pero esos avisos todavía no se emiten (los RF correspondientes no llegaron a esa parte).

### 2026-10-25 · RF27, RF20, RF04, RF23 · Retroalimentación del rol Soporte Técnico
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** pendiente de registrar: la retroalimentación llegó fuera de una tarea
- **Hecho:**
  - **Alcance y etiquetas.** El menú de Soporte dice **Usuarios** (ya no «Usuarios (consulta)»): el rol sí crea estudiantes e importa ([[ADR-037 Jerarquía de creación y asignación de roles]], [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]); se documentó en [[Manual Soporte Técnico]] (apartados 4 y 5) y en [[Layout Soporte Técnico]]. El botón «Importar Excel» pasó a **«Importar estudiantes (CSV)»**, que es lo que acepta hoy.
  - **Preferencias de aviso por rol.** `config/notificaciones.php` declara la audiencia de cada evento; «Mi perfil → Notificaciones» solo lista los avisos que el rol puede recibir y la pestaña se oculta cuando no queda ninguno. Los de correo obligatorio (`mass_message.sent`) se muestran como «Siempre por correo», sin interruptor que engañe.
  - **Copy del perfil.** Los datos personales ya no remiten a «la coordinación de su carrera» a quien no es estudiante.
  - **Búsqueda por nombre.** `App\Support\Busqueda` exige que cada palabra escrita empiece una palabra del nombre («jan» ya no trae a «Alejandra»), neutraliza los comodines de LIKE y se usa en el diagnóstico y en la lista de usuarios.
  - **Diagnóstico de asistencia.** Rango de fechas, filtro por resultado, atajos «Hoy» y «Limpiar filtros» y exportación a CSV (sin coordenadas, solo la distancia).
  - **Solicitudes de dispositivo.** El motivo del rechazo se guarda y se ve en «Resueltas»; rechazar pide confirmación y marca el motivo como obligatorio; desde la solicitud se salta al diagnóstico del mismo estudiante.
  - **Menú y panel.** Contador de pendientes junto a «Solicitudes de dispositivo» (`App\Support\Navegacion\Contadores`), y la tarjeta «Estudiantes sin teléfono» del Inicio enlaza a `/usuarios?rol=estudiante&telefono=sin`, un filtro nuevo de la lista.
  - **Avisos de la bandeja de Soporte.** Evento nuevo `device_request.pending`: cuando un estudiante pide el cambio, avisa a quien tiene `dispositivos.gestionar` (`DispositivoCambioSolicitado`), que es la entrada «Aviso o contador del menú» del Layout del rol. Al resolver, el cierre se avisa al **resto** del equipo con `device_request.resolved` (`DispositivoCambioResuelto`), nunca a quien la resolvió; el estudiante sigue recibiendo el suyo. Criterio nuevo en [[RF27]] y [[Flujo Registro y cambio de dispositivo]] actualizado.
- **CP cubiertos:** CP-RF27-06, CP-RF27-07 y CP-RF20-13, nuevos (`pasa`, `pasa` y `automatizado`), más pruebas de regresión del menú, del perfil y de la lista de usuarios.
- **Decisiones (usuario, 25/10/2026):**
  - El motivo del rechazo se **persiste** en `device_change_requests.resolution_note` (migración nueva y [[Modelo entidad-relación]] actualizado), en vez de leerlo de la auditoría: así funciona también en SQLite.
  - El detalle de «Estudiantes sin teléfono» es un **filtro en `/usuarios`**, no una vista nueva; cuenta lo mismo que la tarjeta (estudiantes con carrera activa sin teléfono activo).
  - Si a un rol no le corresponde ningún aviso configurable, la pestaña **Notificaciones se oculta**; entrando por URL se explica con un estado vacío. Hoy es el caso del **Administrador**: ningún evento lo tiene como audiencia.
  - A Soporte **sí** se le avisa de la bandeja de dispositivos: al llegar la solicitud y al cierre que haga otra persona del equipo. Los destinatarios se buscan por el permiso `dispositivos.gestionar`, no por el rol, para que sirva igual si el permiso se concede a alguien más.
- **Pendiente:** sigue sin implementarse «Dispositivos de un estudiante → Revocar con motivo» del Layout del rol. Queda por registrar el `OP#` de esta retroalimentación.

### 2026-10-25 · RF04 · Carné y correo desde la parte antes de la @, y aviso de relevo
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385, sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** el carné del estudiante sale del correo institucional (`AsignacionService::carneDeCorreo()`: `ll22017@ues.edu.sv` → `ll22017`), al dar el rol Estudiante y en la carga masiva. Al crear una cuenta se escribe solo la parte antes de la @ y el formulario agrega `@ues.edu.sv`. Antes de dar una jefatura o una coordinación que ya tiene titular, el formulario avisa a quién se reemplazará (pasos 2 y 3).
- **CP cubiertos:** CP-RF04-02, 03 y 12 (pruebas nuevas y ajustadas).
- **Decisiones (usuario, 25/10/2026):**
  - El carné se guarda en minúsculas, igual que el correo; los ya guardados en mayúsculas no se tocan y la unicidad no distingue mayúsculas.
  - Solo se escribe a mano si el correo no da un carné válido (más de 20 caracteres). Un carné distinto del correo se rechaza; en la carga masiva la columna `carnet` es opcional.
  - Un correo de otro dominio pegado en el alta no se recorta: se rechaza.
- **Pendiente:** nada.

### 2026-10-25 · RF21 · Asistencia virtual con el reporte de Meet (entrega 1 de 2)
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#453, sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** el docente programa la clase virtual (tema, inicio, duración y enlace de Meet a mano), sube el reporte de Meet en CSV o `.xlsx`, revisa la vista previa (Presentes, Ausentes y Sin asociar), asigna o descarta las filas sin asociar y confirma. Se crea la sesión de asistencia virtual cerrada y los registros con `method = meet` y `meet_percent`. `ReporteMeetService`, `App\Support\HojaCalculo`, el componente `ClasesVirtuales` en la pestaña Asistencia de la sección y la auditoría de `video_sessions` y `meet_reports` (`11_audit_meet.sql`).
- **CP cubiertos:** CP-RF21-02 a 08 (`automatizado`).
- **Decisiones (usuario, 24/10/2026):**
  - RF21 va en dos entregas: esta, y Calendar con el OAuth del docente (CP-RF21-01, pestaña 52 de Mi perfil) cuando TI agregue el scope `calendar.events`.
  - Lector propio de `.xlsx` sin dependencias ([[ADR-048 Lector propio de hojas para el reporte de Meet]], **pendiente**: se aparta de [[ADR-016 Paquetes]]).
  - Columnas del reporte con alias en español e inglés, y la fecha de la primera hora de entrada (o de los metadatos) debe ser el día de la clase. Se ajustan cuando haya un reporte real.
  - El estado de la clase (programada, en curso, finalizada) se calcula por la hora; el reporte solo se acepta en una clase finalizada.
  - Un reporte nuevo reemplaza al vigente y recalcula a todos, salvo los registros `justified`; las correcciones manuales previas se sobrescriben y quedan en la auditoría. Para confirmar no deben quedar filas sin asociar.
  - Una duración numérica menor que 1 es fracción de día de Excel; desde 1, minutos.
- **Pendiente:** entrega 2 (Calendar, CP-RF21-01), recordatorio de sesiones sin reporte en el panel (RF24), cambio de modalidad → método (CP-RF32-02), probar los triggers nuevos en PostgreSQL (CI) y confirmar con TI el reporte de asistencia de Meet.

### 2026-10-24 · RF22, RF25, RF18, RF33 · Alertas de inasistencia, reportes por correo, avance por unidad y período
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** Plan H3-a.
  - **RF22:** `AsistenciaService::evaluarAlertas()` abre y cierra las alertas de `academic_alerts` por matrícula y cómputo, con los umbrales de `system_settings` (85 y 78, y el mínimo de 75). Lo llama el job `EvaluarAlertasAsistencia` (cola `default`, único por sección hasta que corre) al cerrar una sesión, al corregir un registro, al aceptar una justificación y al aprobar una cancelación. El aviso `academic_alert.opened` sale por el evento `AlertaAsistenciaAbierta`. Antes **nadie escribía** en esa tabla, así que los paneles y el reporte de bajo rendimiento siempre estaban vacíos.
  - **RF25:** el job `EnviarReportesProgramados` (cola `reportes`, cada hora) genera los reportes programados que ya tocan y los manda por correo con el CSV adjunto (`ReporteProgramado`). La exportación y el correo comparten `ReporteService::csv()`.
  - **RF18:** barra de avance por unidad (slot `resumen` de `x-ui.accordion.item`).
  - **RF33:** filtro por período (el activo y los cerrados) en desempeño docente y su exportación.
- **CP cubiertos:** CP-RF18-01 y 03; CP-RF22-01 a 06; CP-RF25-01 y 02; CP-RF33-01. **RF22 y RF25 quedan `implementado`.**
- **Decisiones:**
  - El aviso de cada umbral sale una sola vez por cómputo: si la alerta se cierra porque el estudiante se recupera y luego se vuelve a abrir, ya no avisa (`notified_at` de la alerta anterior). Si cruza 85 % y 78 % de una vez, sale solo el crítico.
  - Bajo 75 % no sale un aviso aparte: se marca «en riesgo» en los paneles (Flujo Alerta de inasistencias).
  - El panel del docente muestra las alertas desde el umbral crítico (no el preventivo de 85 %), y el «en riesgo» del Coordinador cuenta solo `attendance_75` y `grade_risk`.
  - El reporte programado es una notificación solo por correo, no un aviso de la bandeja: no hay clave para él en el catálogo de avisos. No se envía a una cuenta desactivada ni a quien perdió el permiso. Su próxima fecha avanza al tomarlo, así que el job no lo envía dos veces.
- **Pendiente (decisiones de H3):** RF33-02 y 03 (el código mide la puntualidad y el tiempo de revisión distinto de lo que piden los casos; la pantalla ya los marca como provisionales), RF33-04 (Excel o PDF), RF10-01 y 02 (qué son «ingresados» y «egresados», y dónde se guarda el ajuste de grupos), RF18-02 (`completion_rule`, Plan 5) y RF21 (Meet: Calendar y lectura de Excel).

### 2026-10-24 · H2 · Pruebas de matrícula, notas y foro; avisos, cupo bloqueado y días cancelados
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:**
  - **H2-a:** se escribieron o se renombraron con su ID las pruebas de 16 casos que ya tenían código. `MatriculaService` y `ForoService` lanzan `AuthorizationException` en lugar de `abort(403)`.
  - **H2-b:** la matrícula bloquea la sección (`lockForUpdate`) mientras cuenta el cupo, también al aprobar un cambio de grupo. Hay avisos de matrícula, retiro y cambio de grupo (`MatriculaCambiada` → `enrollment.changed`), de respuesta en el foro (`RespuestaPublicada` → `forum.reply`) y de cancelación o cambio de modalidad aprobados (`ClaseCancelada` → `class.cancelled`). `porcentajeSql()` ignora los registros de un día cancelado (sección, carrera o facultad), y con él todos los reportes que lo usan. Se agregó un trigger de auditoría para `class_cancellations` (`10_audit_class_cancellations.sql`), y la solicitud, la aprobación y la cancelación directa guardan su motivo en `app.reason`.
- **CP cubiertos:** CP-RF09-01, 02, 03, 04, 06, 09 y 10; CP-RF17-02, 03, 06, 07, 09 y 10; CP-RF24-03; CP-RF29-01, 02, 04 y 06; CP-RF30-02 y 04; CP-RF32-01 y 03. **RF30 queda `implementado`.**
- **Decisiones:**
  - `enrollment.changed` y `class.cancelled` se agregan a `NotificacionService::SIN_AGRUPAR`: llevan un motivo (retiro, cambio rechazado, cancelación) que «N avisos nuevos» perdería. Es la misma regla que ya seguía la devolución de un acta.
  - La respuesta en el foro avisa al autor del mensaje respondido o, si responde al hilo, al autor del hilo. No avisa a quien responde ni si el mensaje está oculto.
  - Un cambio de modalidad no saca la clase del porcentaje; solo una cancelación aprobada lo hace.
- **Pendiente:**
  - No se ejecutaron porque solo corren en PostgreSQL: CP-RF09-09 (la consulta `FOR UPDATE` antes del conteo) y CP-RF32-03 (la auditoría).
  - Una cancelación de toda una carrera queda en la auditoría sin `department_id` (`fn_audit_department` solo lo deduce de `section_id`): la ve el Administrador, no el Jefe.
  - Decisiones abiertas de H2: CP-RF09-05 contradice la Matriz RBAC y ADR-037 (Soporte sí inscribe); CP-RF09-08 (cómo se trasladan las entregas y notas al cambiar de grupo); CP-RF29-03 (validar el ciclo); CP-RF24-02 (medir los 3 s); CP-RF32-02 (depende de RF21); Planes 2, 3, 4, 8 y 9.
  - El mensaje de choque es «Choca con el horario de PRN115 · 01»; CP-RF09-02 pide «La sección choca en horario con otra matrícula».

### 2026-10-24 · RF06, RF15, RF17 · Pesos y puntos enteros mostrados sin sus ceros
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** sin tarea (defecto encontrado en el Plan H1-a)
- **Hecho:** se corrigieron 11 usos de `rtrim(rtrim($valor, '0'), '.')` en 7 vistas: detalle del período, libro y boleta de notas, actividades, nueva tarea, detalle de la tarea, entregas y rúbricas. Sin `number_format`, un decimal que llegaba sin decimales (SQLite) perdía sus ceros: un peso de 30 % se mostraba como «3 %» y un criterio de 10 puntos como «1». En PostgreSQL ya se veían bien, porque `numeric` siempre trae decimales. Hay dos pruebas de regresión que fallan sin la corrección.
- **Pendiente:** `CalificacionService::corregir()` todavía usa `abort_unless(..., 403)`.

### 2026-10-24 · H1 · Pruebas con ID de los casos que ya tenían código
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** Plan H1-a. Se escribieron o se renombraron con su ID las pruebas de 24 casos de H1 que ya tenían código. Donde ya había una prueba con otro ID para el mismo criterio, se le agregó el segundo ID en lugar de duplicarla (CP-RF06-03 en CP-RF34-07, CP-RF26-02 en CP-RF20-07 y CP-RF19-04 en CP-RF03-05). `CargaAcademicaService` lanza `AuthorizationException` en lugar de `abort(403)`.
- **CP cubiertos:** CP-RF03-01, 02, 04 y 05; CP-RF05-01, 03, 04 y 05; CP-RF06-01 y 03; CP-RF07-01 y 03; CP-RF08-01 y 02; CP-RF15-01, 03 y 04; CP-RF16-01; CP-RF19-01, 03 y 04; CP-RF26-02; CP-RF28-02 y 04. **RF07, RF08, RF16 y RF28 quedan `implementado`.**
- **Decisiones:** RF03 y RF05 tienen todos sus casos automatizados, pero siguen `en-desarrollo`: CP-RF03-05, CP-RF05-01, CP-RF05-05 y la parte de auditoría o RLS de CP-RF03-01, CP-RF05-03 y CP-RF19-04 solo corren en PostgreSQL y no se pudieron ejecutar en esta sesión.
- **Defectos:**
  - **Corregido:** la penalización de una entrega tardía se mostraba como «2 %» en lugar de «20 %» cuando el valor llegaba sin decimales (SQLite): `rtrim(rtrim('20', '0'), '.')` da `'2'`.
  - **Pendiente:** el mismo patrón (`rtrim` sin `number_format`) sigue en unas 10 vistas: pesos de cómputos y actividades, puntos de rúbricas y criterios. En PostgreSQL se ven bien porque `numeric` siempre trae decimales; en SQLite un peso de 30 % se mostraría como «3 %».
  - **Pendiente:** `CalificacionService::corregir()` todavía usa `abort_unless(..., 403)`.

### 2026-10-24 · RF07, RF08 · Eliminar o desactivar asignaturas, filtro por créditos y avisos de horario
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#397 (RF07) y OP#401 (RF08), sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** Plan H1-b de la revisión de H1. Catálogo: eliminar una asignatura solo si nada la usa; si no, se ofrece desactivarla, y la inactiva deja de ofrecerse al crear secciones. Filtro por créditos en el catálogo. Carga académica: quitar un bloque del horario y cambiar su aula (sin choques); agregar, quitar o cambiar avisa a los estudiantes matriculados y a los docentes vigentes con el evento `schedule.changed` (`HorarioCambiado` → `EnviarAvisos`). `AsistenciaService` lanza `AuthorizationException` en lugar de `abort(403)`, como exige AGENTS.md.
- **CP cubiertos:** CP-RF07-02 y 04; CP-RF08-03.
- **Decisiones:**
  - Una asignatura sin secciones tampoco se elimina si está en un plan de estudios, es prerrequisito de otra o tiene claves de matrícula o bancos de preguntas: el mensaje dice qué la usa y sugiere desactivarla. Sus propios prerrequisitos se borran con ella.
  - Como antes solo se podía agregar bloques de horario, se agregaron quitar un bloque y cambiar su aula, que son los cambios que RF08 manda notificar.
- **Pendiente:**
  - **Defecto conocido:** `CargaAcademicaService` todavía usa `abort(403)` en `exigirSeccion()` y `exigirAsignaturaEnAlcance()`. Hay pruebas de CP-RF08-04 y 05 que esperan `HttpException`, así que corregirlo también implica actualizar esas pruebas.
  - CP-RF07-01, CP-RF07-03, CP-RF08-01 y CP-RF08-02 tienen código, pero les faltan las pruebas con su ID (Plan H1-a).

### 2026-10-24 · RF11 · Editor de páginas y carpetas en .zip
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#413, sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** Plan 7. Editor de páginas con **Quill 2.0.3** (carga diferida, barra en español) y HTML saneado en el servidor con **mews/purifier 3.4** (perfil `contenidos` de `config/purifier.php`), al crear y al editar una página ya publicada; la página se muestra con su formato. Tipo `folder`: el docente elige una carpeta en el navegador (`webkitdirectory`), cada archivo se guarda en `resource_files` con su ruta relativa y el estudiante la descarga completa en un .zip que se arma al vuelo con **ZipStream 3.2**, sin archivo temporal. La purga de la papelera borra el directorio de la carpeta.
- **CP cubiertos:** CP-RF11-06 y 07. **RF11 queda `implementado`** con sus 8 criterios.
- **Decisiones:**
  - Editor Quill, porque trae alineación de serie y RF11 pide «centrado».
  - Lista blanca: `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `s`, `h2`, `h3`, `ul`, `ol`, `li`, `blockquote` y `a`. Enlaces solo `http`/`https`, con `target="_blank"` y `rel="noopener noreferrer"`. La alineación viaja solo como clase `ql-align-*`. Fuera: `script`, `style`, `iframe`, `img`, `table`, los `on*` y `javascript:`.
  - Carpeta: hasta 50 archivos y 80 MB en total, con los mismos tipos y el mismo máximo por archivo que un archivo suelto. Las rutas se normalizan contra *zip slip*: se rechazan rutas absolutas, `..`, `\`, unidades de Windows y rutas repetidas.
- **Pendiente:**
  - El disco `archivos` de ADR-020 sigue pendiente; las carpetas usan el disco por defecto, como los archivos sueltos.
  - Larastan no está en `composer.json` y el entorno no tiene driver de cobertura: `composer check` solo corrió Pint y las pruebas.

### 2026-10-24 · RF11, RF12 · Materiales: validación, PDF, búsqueda, papelera y video
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#413 (RF11) y OP#417 (RF12), sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno
- **Hecho:** Plan 6 del documento de planes pendientes. Validación de archivo por extensión y mime real con `ContenidoService::EXTENSIONES` (PDF, Word, Excel, PowerPoint, imágenes, texto y ZIP) y 50 MB, con zona de arrastre. El docente cambia `pdf_mode` después de crear el recurso y el PDF se ve incrustado o se descarga. Búsqueda con filtro por tipo en **español** (antes solo coincidía en inglés) e índices nuevos en una migración adaptativa. Papelera: ahora también se eliminan y restauran **unidades**, restaurar un recurso restaura su unidad, restaurar pasados los 30 días se rechaza, y el job `PurgarPapelera` (cola `default`, diario) purga lo vencido. RF12: `embebido()` salió de Livewire al Service y reconoce `embed/`, `shorts/`, `live/`, `m.youtube.com`, `v=` en cualquier posición y Drive `open?id=`; las URL solo aceptan `http`/`https`.
- **CP cubiertos:** CP-RF11-01, 02, 03, 04, 05 y 08; CP-RF12-01 y 02. Además se renombraron con su ID las pruebas de Contenidos que ya existían y no contaban (CP-RF13-01 y CP-RF18-01). **RF12 queda `implementado`.**
- **Decisiones:**
  - **Qué borra la purga:** se borra el archivo del disco y se vacía el contenido del recurso, pero **la fila se conserva**. El ER exige que los logs de acceso nunca se borren (FK `RESTRICT`) y borrar el recurso obligaría a borrarlos; así desaparece el material y se conserva la analítica de [[RF13]]. Queda anotado en las reglas de negocio de [[RF11]].
  - Tipos admitidos y 50 MB; restaurar un recurso restaura su unidad; filtro por tipo en español con índice, sin medir tiempos en CP-RF11-04.
  - El módulo dejó de usar `abort_unless(..., 403)` y ahora lanza `AuthorizationException`, como exige AGENTS.md.
- **Pendiente:**
  - CP-RF11-06 (carpetas y .zip) y CP-RF11-07 (editor con HTML sanitizado) son el **Plan 7** y necesitan aprobar `mews/purifier` y ZipStream.
  - **Defecto conocido:** el visor de PDF incrustado carga la ruta del archivo desde el `<iframe>`, que llama a `abrir()`, así que una apertura cuenta dos veces. Lo corrige el **Plan 8** (separar «abrir» de «registrar»).
  - Larastan no se pudo correr: no está instalado en el entorno.

### 2026-10-24 · RF04, RF07, RF08 · Carreras visibles, carga masiva sin el Coordinador y fuga de notas
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** sin tarea (funcionalidad pedida en sesión; `OP_URL` y `OP_TOKEN` tampoco están en el entorno)
- **Hecho:**
  1. **Defecto de seguridad corregido:** `CalificacionService::libro()` dejaba a un estudiante matriculado leer las notas de toda su sección, porque `calificaciones.ver` es de alcance P y `Alcance::secciones()` lo trata como M. Ahora el libro exige alcance G, D, C o S; el estudiante sigue con `boleta()` y `misNotas()`. Arrastraba también a `acta()`.
  2. **Carreras para el Jefe y el Coordinador:** permiso nuevo `carreras.ver` (D para el Jefe, C para el Coordinador), `CarreraService` con `listar()` y `detalle()`, y las pantallas `/carreras` y `/carreras/{carrera}`: Coordinador vigente, docentes, y las materias con sus grupos y horarios del período en curso.
  3. **La carga masiva deja de ser del Coordinador:** `usuarios.importar` queda en el Administrador y en Soporte. La opción «Importar estudiantes» pasó del menú de Coordinación a los de Administración y Soporte, donde antes no existía.
- **CP cubiertos:** CP-RF04-01 reescrito (el Coordinador recibe 403 en el Service y en la ruta; el Administrador y Soporte importan de cualquier carrera) y CP-RF04-02 pasa a Soporte. La fuga de notas se cubre con dos pruebas en `ActasTest.php`. La vista de carreras se prueba en `tests/Feature/Academica/CarrerasTest.php`, sin ID porque todavía no tiene criterios en el vault.
- **Decisiones:** [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]] (**pendiente de aceptar**): registra el permiso nuevo y modifica [[ADR-030 Matrícula ampliada]], que estaba aceptada y daba la carga masiva al Coordinador. Se reescribieron el criterio correspondiente de [[RF04]] y CP-RF04-01, que ya no describen el comportamiento acordado.
- **Pendiente:**
  - **ADR-047 necesita que el equipo la acepte**: modifica una decisión aceptada.
  - `carreras.ver` no pertenece a ningún RF: conviene reflejarlo en [[RF07]] o [[RF08]], o abrir un RF propio, con sus criterios y casos de prueba.
  - [[Flujo Carga masiva de usuarios]] todavía nombra al Coordinador.
  - El detalle de carrera usa el período en curso; sin período activo muestra las materias del plan y cero grupos.

### 2026-10-24 · RF34 · Actas: bloqueo de notas, aviso de faltantes y devoluciones
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#513 (sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno)
- **Hecho:** `actaBloqueada()` / `exigirActaAbierta()` cierran todas las vías del docente para cambiar notas con el acta `published` o `validated` (`calificar`, `publicar`, `ActividadService::calificarEntrega`, `CuestionarioService::calificarEnsayo`), y las cuatro pantallas afectadas muestran el aviso en vez de fallar en silencio. `acta()` devuelve `faltantes` y la pantalla **solo advierte**. `devolverActa()` acepta actas `validated` (salvo período cerrado) y limpia validador y fecha. Eventos `ActaPublicada` y `ActaDevuelta` con avisos a los Coordinadores con alcance y a los docentes de la sección. El Jefe ve las secciones pendientes por «CÓDIGO-grupo».
- **CP cubiertos:** CP-RF34-01, 02, 03, 04, 05, 06 y 09 (`tests/Feature/Calificaciones/ActasTest.php`, 80 pruebas; CP-RF34-03 y 06 también en `EvaluacionesYNotasTest`). Con 07 y 08 del cierre, RF34 queda con sus 9 criterios automatizados.
- **Decisiones:** las notas faltantes **solo se advierten**, nunca bloquean (decisión del 24/10/2026). No se bloquean las escrituras automáticas del sistema (autocalificación, `CerrarIntentosVencidos`, `cerrarIntento`) ni la corrección del Coordinador. `grade_sheet.returned` se agrega a `NotificacionService::SIN_AGRUPAR` para que la agrupación de [[ADR-043 Agrupación y correo de las notificaciones]] no pierda las observaciones. RF35 reutiliza `actaBloqueada()` en vez de duplicar la lista de estados.
- **Pendiente:**
  - **Defecto previo, ajeno a este cambio:** `libro()` y `acta()` dejan a un estudiante matriculado leer las notas de toda la sección (`calificaciones.ver` es P y `Alcance::secciones()` lo trata como M). Documentado con `->skip()` en `ActasTest.php`. Sin corregir, a la espera de decisión.
  - `publicarActa()` no comprueba el período cerrado; `resolverActa` solo lo comprueba para actas `validated`.
  - La auditoría de la validación y de la devolución (CP-RF34-03 y 04) nunca se ejecutó: requiere PostgreSQL.
  - `composer check` no pasa en el entorno: Pint falla en `bootstrap/providers.php` (ya rojo en `test`), larastan no está en `require-dev` y no hay driver de cobertura ni workflow de CI.

### 2026-10-24 · RF34 · Cierre del período por el Administrador
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#513 (sin actualizar: faltan `OP_URL` y `OP_TOKEN` en el entorno)
- **Hecho:** `PeriodoService::cerrar()` (período bloqueado dentro de la transacción, pendientes por nombre y cierre en una sola `DbContext::run`), `CalificacionService::congelarResultados()` (una fila por matrícula activa en `enrollment_results`, idempotente) y el botón «Cerrar período» en el detalle del período.
- **CP cubiertos:** CP-RF34-07, CP-RF34-08 y CP-RF17-04 (`tests/Feature/Calificaciones/CierrePeriodoTest.php`). Las de triggers y auditoría se saltan fuera de PostgreSQL.
- **Decisiones:** permiso `periodos.gestionar` (sin permiso nuevo en la Matriz RBAC). Solo los departamentos con secciones en el período deben haber autorizado; un departamento sin secciones no bloquea el cierre. El `policy_snapshot` usa el formato de `system_settings` (`passing_score`, `grade_rounding`, `missing_grade_policy`) más `components` y `confirmed`. Las notas no publicadas cuentan, igual que en el libro y el acta.
- **Pendiente:**
  - Período sin secciones o sección sin cómputos: hoy se cierra, y la sección sin cómputos congela a sus estudiantes como `failed` con 0.0. Falta decidir si se bloquea o se avisa.
  - Reabrir el período, el histórico exportable y el aviso de acta publicada o devuelta.
  - Probar el cierre en PostgreSQL (trigger `AC203`, auditoría) y la concurrencia de dos cierres.
  - RF34-01, 02, 04 y 09 siguen abiertos; por eso RF34 sigue `en-desarrollo`.

### 2026-10-23 · RF20 · QR rotativo con lector en la página
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#449 (sin registrar: no se usa OpenProject en esta tarea)
- **Hecho:** `AsistenciaQrService` (proyección del QR, marcación con las 9 validaciones del flujo y purga de tokens), las pantallas «Proyectar QR» y «Marcar asistencia», y el job diario `PurgarTokensQr`. `abrir()` lee la rotación de `system_settings`. El diagnóstico usa `scan_limit` (antes `no_attempts`, que no existía en el CHECK).
- **CP cubiertos:** CP-RF20-01 a CP-RF20-12 (`tests/Feature/Asistencia/AsistenciaQrTest.php`).
- **Decisiones:** [[ADR-046 Lector QR en la página y rotación por polling]] (aceptada el 24/10/2026): lector en la página con `BarcodeDetector` y `barcode-detector`, QR en SVG con `chillerlan/php-qrcode`, proyección por polling cada 2 s aceptando el token vigente y el anterior, sin tardanza con QR y ubicación por distancia ≤ radio.
- **Pendiente:**
  - Áreas de polígono ([[RF26]]): hoy se rechazan como `out_of_range`.
  - La sesión toma la primera área activa, sin orden ([[RF26]]).
  - `usarCodigo()` de [[RF28]] no registra intentos en `attendance_scan_attempts`.
  - **Prueba de navegador del flujo crítico 2** (DoD, [[Estrategia de pruebas]]): mientras falte, RF20 queda `en-desarrollo` y sus CP en `automatizado`.
  - Probar la cámara en teléfonos reales (Android e iOS), de preferencia en un despliegue con https y PostgreSQL.

---

### 2026-10-23 · RF27 · Token de dispositivo en lugar de WebAuthn
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#485 (sin registrar: `OP_URL`, `OP_TOKEN` y `OP_PROYECTO` no están en el entorno)
- **Hecho:** el vault deja de hablar de Passkey y WebAuthn. [[RF27]], [[RF20]] (y CP-RF20-08), los flujos de asistencia con QR y de registro y cambio de dispositivo, el ER (solo el comentario de `credential_id`), la Seguridad, el Stack y los manuales hablan de un **token de dispositivo**. No cambian tablas ni columnas.
- **Decisiones:** [[ADR-045 Token de dispositivo]] (aceptada el 23/10/2026): token aleatorio de 32 bytes; el servidor guarda solo su SHA-256 en `student_devices.credential_id`; cookie `device_token` (HttpOnly, Secure, SameSite=Lax, 1 año) más copia en `localStorage`; vale si coincide cualquiera de los dos. Reemplaza la parte WebAuthn de [[ADR-016 Paquetes]] y [[ADR-028 QR rotativo]]. **Riesgo aceptado:** el token en el cliente se puede copiar o borrar y es más débil que WebAuthn; lo mitigan el dispositivo único, el GPS, los 3 intentos, el token del QR de 10 s, la sesión de Google y el cambio autorizado por Soporte.
  - Al integrar (23/10/2026): la cookie `device_token` es siempre `Secure`, sin depender de `SESSION_SECURE_COOKIE`.
  - El rechazo de una solicitud de cambio no se audita: [[RF27]] solo pide auditar la aprobación y `device_change_requests` no tiene trigger. El motivo llega al estudiante en la notificación.
  - El último uso (`last_used_at`, evento `used`) y la reescritura del token que falte los hace [[RF20]] al marcar.
- **Pendiente:**
  - Registrar el avance en OpenProject (OP#485) cuando `OP_URL`, `OP_TOKEN` y `OP_PROYECTO` estén en el entorno.
  - Las maquetas HTML de `docs/13 Layout/Maquetas/html/` conservan el diseño anterior donde el cambio no es de texto simple. En la 51 Integraciones ya se quitó la tarjeta WebAuthn y su fila de la bitácora.

---

### 2026-10-22 · BASE · `php artisan dev --reverb` comprueba el puerto antes de levantar nada
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:** el comando abre el puerto de Reverb antes de arrancar ningún proceso y, si está ocupado, **no levanta nada** y explica cómo ver quién lo usa y cómo cambiarlo. Antes arrancaba todo, dejaba que Reverb muriera con un stack trace de 16 líneas y solo entonces lo apagaba.
- **Causa del caso real:** en una máquina con **Coolify**, el proxy Traefik publica el 8080 para el Reverb de staging, así que `REVERB_SERVER_PORT=8080` choca siempre. Se verificó que el Redis del staging **no** se comparte con el de desarrollo: no hay dos Horizon consumiendo los mismos trabajos.
- **Decisiones:** no se cambia el puerto solo: el servidor (`REVERB_SERVER_PORT`) y el navegador (`REVERB_PORT`) tienen que coincidir, y cambiar uno a escondidas dejaría la campana sin conectar sin avisar. Se documenta en [[Setup del entorno]].
- **Pendiente:** ninguno.

---

### 2026-10-22 · RF31 · Mensajería condicional a estudiantes
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#501 (sin registrar: `OP_URL`, `OP_TOKEN` y `OP_PROYECTO` no están en el entorno)
- **Hecho:**
  - Desde **Notas**, «Mensajear a estudiantes que…»: un asistente de 3 pasos (condición · destinatarios · mensaje y revisión) que filtra a quienes **no entregaron** una actividad o sacaron **menos de X** (solo nota publicada), deja **quitar a alguien** y envía.
  - Cada estudiante recibe un **correo individual** (sin copia a nadie, con `Reply-To` al docente) y un aviso en su campana. El envío va en cola (`EnviarMensajeMasivo`, idempotente).
  - **Notas › Mensajes enviados:** el historial con cada mensaje, a quién y si ya se entregó.
  - El ER recoge `mass_messages.section_id`, que la tabla real ya tenía.
- **CP cubiertos:** CP-RF31-01 a CP-RF31-04, en `pasa`.
- **Decisiones:** [[ADR-044 Mensajes del docente, correo obligatorio y sin agrupar]] (pendiente): el correo **no se puede silenciar**, los mensajes **no se agrupan** y solo hay dos condiciones. `FormularioPorPasos` gana un gancho `validarPaso()` para comprobaciones que no son de formato («debe quedar al menos un destinatario»).
- **Pendiente:**
  - La condición «no han abierto el recurso» de la descripción del RF: depende de `resource_access_logs`, solo existente en PostgreSQL.
  - No hay tope de destinatarios por mensaje: el vault no lo define.

---

### 2026-10-22 · RF23 · Notificaciones: push, correo, preferencias y agrupación
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#461 (sin registrar: `OP_URL`, `OP_TOKEN` y `OP_PROYECTO` no están en el entorno)
- **Hecho:**
  - **Push por Reverb** al canal privado del usuario y **correo** al correo institucional, ambos en la cola `notificaciones`. Antes los avisos solo llegaban a la bandeja.
  - **Preferencias respetadas:** si el usuario apagó el correo para un tipo de aviso, solo lo recibe en la plataforma. Sin fila de preferencia se asume que sí.
  - **Agrupación:** los avisos del mismo tipo, sin leer, de menos de 10 minutos se suman en uno con contador y un solo correo.
  - **Entregas registradas** en `notification_deliveries` por canal con su `sent_at`: la medición de [[RNF12]].
  - **Campana en tiempo real** (componente Livewire `Campana`): contador, panel con los últimos 10 y respaldo de 15 s solo mientras el WebSocket no esté conectado.
  - `User::routeNotificationForMail()`: el modelo no tenía `email` y el correo habría ido a `null`.
- **CP cubiertos:** CP-RF23-02 y CP-RF23-03 en `pasa`; CP-RF23-01 en `automatizado` (ver la nota del CP).
- **Decisiones:** [[ADR-043 Agrupación y correo de las notificaciones]] (pendiente). **Se desvió un punto del plan aprobado:** la fila de la bandeja ya no la crea el canal `database` dentro de la cola, sino `NotificacionService::enviar()` en el momento. Con la cola real, el correo y la bandeja corrían en trabajos separados y `notification_deliveries` tiene FK a `notifications`, así que una entrega podía intentar registrarse antes de que existiera su fila. `CanalAvisos` se elimina por innecesario.
- **Pendiente:**
  - Medir en staging los 30 s y los 5 min con la tabla de entregas.
  - Los 13 eventos del catálogo que aún no tienen quién los emita (`assessment.created`, `resource.published`, `class.cancelled`, `enrollment.changed`, `forum.reply`…): cada uno se conecta cuando se implemente su RF.
  - Los avisos agrupados no tienen entrega propia en `notification_deliveries`.

---

### 2026-10-22 · RF24 · Panel del estudiante rehecho
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - **Mis materias** en recuadros grandes: código y grupo, docente responsable, horario, asistencia y **avance del curso**.
  - **Agenda de la semana**: una sola línea de tiempo con las clases del horario, las entregas que vencen y las videoconferencias de los próximos 7 días, agrupada por día y con Hoy y Mañana destacados. Cierra **CP-RF24-01**, que exigía «la lista de pendientes ordenada por fecha y el calendario».
  - Tarjeta de **sesión de asistencia abierta** al inicio del panel, que RF24 pedía y solo estaba en la cabecera.
  - El **acordeón por rol aparece solo con más de un rol**; con uno, el bloque va directo.
- **Decisiones:**
  - El **avance del curso** mide el porcentaje de recursos completados (`resource_progress`, RF13): es el único dato de avance que existe hoy.
  - «El calendario» de RF24 se resuelve como **agenda de 7 días**, no como rejilla mensual: cubre el criterio, no añade dependencias y se lee en 360 px ([[RNF05]]).
  - El **escáner QR solo se ofrece en dispositivos táctiles** (`pointer-coarse`); en un equipo de escritorio no hay cámara que apuntar al código, así que se indica abrir la pantalla en el teléfono.
  - El bloque de cada rol vive en `partials/bloque-rol.blade.php`: **un componente Blade no se puede partir entre dos `@if`**, porque las etiquetas se emparejan al compilar y el contenido se pierde sin avisar.
- **Pendiente:** las videoconferencias solo las siembra la demo hasta que se implemente RF21; el CUM de la maqueta 02 no tiene columna en el modelo y queda fuera.

---

### 2026-10-22 · BUG · Los mensajes de validación salían sin traducir
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - En toda la aplicación los mensajes del validador se veían como su clave: «validation.required» en vez de «El campo correo es obligatorio.». Se detectó en una prueba de navegador y se confirmó en un formulario sin tocar (Nuevo período).
  - **Causa:** `APP_LOCALE` y `APP_FALLBACK_LOCALE` son ambos `es`, y `lang/` solo tenía `es/roles.php`. Sin `lang/es/validation.php` y sin otro idioma de respaldo, Laravel devuelve la clave tal cual.
  - Se agregó `lang/es/validation.php` con las **135 claves** traducidas, generado a partir del archivo del framework para no dejar ninguna fuera, y `lang/es/auth.php` para la cuenta de emergencia ([[ADR-007 Inicio de sesión con Google]]), que todavía no está implementada.
- **Decisiones:** no se publican los archivos en inglés: la interfaz es solo en español y dos copias se desincronizan. El texto de la interfaz sigue escribiéndose en español dentro de `__()`, que devuelve la propia clave; aquí solo viven los mensajes del validador. `attributes` queda vacío a propósito, porque cada componente pasa el nombre de sus campos a `validate()`.
- **Pendiente:** una prueba vigila que ninguna regla quede sin traducir; al subir de versión mayor de Laravel conviene volver a correr `lang:publish` y comparar si aparecieron reglas nuevas.

---

### 2026-10-22 · UI · Listados con la tabla de alto fijo y desplazamiento interno
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Desde 1024 px el alto lo fija la ventana y el desplazamiento vive en `<main>`; en los listados la tabla ocupa el alto que queda y se desplaza por dentro, con los nombres de columna fijos. El título, los indicadores, los filtros y la paginación quedan siempre a la vista.
  - Propiedad `ajustar` en `<x-ui.card>` y `<x-ui.table>`, apagada por omisión: ninguna pantalla cambia si no la pide.
  - Aplicado a **24 listados** de una sola tabla. La convención queda en [[Layout base]].
- **Decisiones:** por debajo de 1024 px no cambia nada, porque anidar desplazamientos en 360 px estorba y el vault exige esa resolución ([[RNF05]]). Quedan fuera 8 pantallas cuyo bloque principal no es una sola tabla: `periodo-detalle`, `sesion`, `tarea` y `usuario-detalle` llevan la tabla dentro de una rejilla de dos columnas; `mis-notas`, `rubricas`, `solicitudes-dispositivo` y `prorrogas` tienen otra estructura.
- **Pendiente:** sigue sin cumplirse que en móvil las tablas se vuelvan tarjetas ([[Layout base]]); hoy se desplazan en horizontal. Es un hueco anterior a este cambio.

---

### 2026-10-22 · UI · Los formularios en página se recorren por pasos
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - `<x-ui.formulario>` lleva el formulario **por pasos**, con indicador, «Atrás», «Siguiente» y un último paso de **resumen para revisar y confirmar**. El estado y la validación por paso los pone el trait `App\Support\Navegacion\FormularioPorPasos`.
  - Aplicado a las 5 pantallas: nuevo usuario (datos · rol y asignación · revisar), roles y asignaciones (roles · asignaciones · revisar), nueva pregunta (enunciado · respuesta · revisar), agregar recurso (qué es · contenido · revisar) y solicitud de revisión (la solicitud · su decisión · revisar).
- **Decisiones:** un paso no deja avanzar hasta que valida, pero **al enviar se valida todo otra vez** y se lleva al paso del primer error, porque el formulario puede enviarse sin pasar por «Siguiente». Hasta el último paso, Intro avanza en vez de enviar. Desde el indicador se vuelve atrás, nunca se salta adelante. La solicitud de revisión **en solo lectura no usa pasos** (`:paso="0"`): no hay nada que recorrer.
- **Pendiente:** ninguno de los formularios por pasos guarda borrador: si se abandona la página, se pierde lo escrito.

---

### 2026-10-22 · UI · Cinco formularios pasan de modal a página
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Se midieron los **38 modales** contra la regla de [[Layout base]] (modal hasta 6 campos). Pasan a página los 5 que la rompen o cuyos campos crecen con los datos:
    - `usuarios.crear` · Nuevo usuario (4 campos propios más el partial de asignación, de 14).
    - `usuarios.roles` · Roles y asignaciones (un bloque de campos por rol marcado).
    - `bancos-de-preguntas.pregunta` · Nueva pregunta (formulario distinto por cada uno de los 4 tipos, [[ADR-009 Tipos de pregunta]]).
    - `secciones.revisiones.show` · Solicitud de revisión (3 campos por ítem revisado, sin tope).
    - `secciones.recursos.crear` · Agregar recurso (7 campos más subida de archivo).
  - **Componentes base para no repetir:** `<x-ui.formulario>` pone el pie con Cancelar y guardar, y con `:enviar="false"` queda de solo lectura; el trait `ConAsignacion` reúne los campos de asignación que estaban duplicados en dos pantallas.
  - Las 5 rutas quedan en `config/vistas.php` y en [[Mapa de vistas]], que además recupera las dos filas de Revisiones que le faltaban.
- **Decisiones:** al guardar se vuelve a la pantalla de origen con el aviso por `session()->flash`, como ya hacían `NuevaTarea` y `NuevoCuestionario`. Los **28 modales de confirmación o de 1 a 3 campos se quedan como están**: convertirlos sería empeorarlos.
- **Pendiente:** quedan en el borde `asignatura` (catálogo: 5 campos más prerrequisitos en bucle) y `prorrogas`, que en realidad es una pantalla de gestión dentro de un modal. No se tocaron.

---

### 2026-10-22 · Calidad · Comando `dev` y pruebas de navegador en lento
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - `php artisan dev`: servidor, workers, scheduler y Vite en una terminal, con `--reverb` y `--dry-run`. Los workers son Horizon en Linux con Redis y `queue:listen` en los demás casos, como los scripts `dev` y `dev:win` de Composer. Si un proceso muere, se detienen todos.
  - `php scripts/navegador.php … --speed=N` (y `composer test:browser`): pausa de N segundos antes de cada paso para ver las pruebas de navegador. Las pruebas pasan a usar `navegar()` en vez de `visit()`.
- **Decisiones:** la pausa va del lado de PHP con `Amp\delay` y no con un temporizador del navegador, que Chrome frena si la ventana no tiene el foco (69 s frente a 9 s en la misma prueba). El plugin no expone `slowMo` de Playwright.
- **Pendiente:**
  - Con ventana, una corrida de más de ~1 min muere con «Target page, context or browser has been closed» (reproducido dos veces). Sin ventana no ocurre. Falta la causa.
  - `FlujoExamenTest` sigue inestable aparte de esto: el autoguardado de la respuesta corta no siempre llega a la base en 10 s, y la lectura tras «Resolver» no espera a Livewire (línea 171).

---

### 2026-10-22 · Transversal · Corte del Roadmap y PHP 8.4.27
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Corte del [[Roadmap]] al 22/10 (commit `5e7beed`): funcional 72,0 %, 192 casos de prueba, 47 en `pasa`.
  - PHP fijado en **8.4.27**, la versión de seguridad publicada el 21/10/2026 en php.net: `composer.json` (`config.platform.php`), `.tool-versions` y [[Setup del entorno]].
  - `composer update` dentro de los rangos actuales (Laravel 12.70.1, Livewire 4.4.8, Horizon 5.50.1, spatie/laravel-permission 8.3.2, laravel/mcp 1.1.1, Symfony 8.1); cierra el aviso de `symfony/yaml` (CVE-2026-109413) y `composer audit` queda limpio. Suite sin Browser: 244 pasan, 8 saltadas.
- **Decisiones:** se mantiene la rama 8.4 de [[ADR-015 Plataforma base]]; solo sube el parche.
- **Pendiente:**
  - La máquina de desarrollo corre PHP 8.5.11 (paquete de Fedora), fuera de la rama 8.4 de la ADR-015. Alinearla con 8.4.27 o decidir en una ADR nueva pasar a 8.5.
  - La imagen Docker toma `php84` de Alpine 3.22; reconstruirla con `--pull --no-cache` y confirmar la versión de PHP que instala.
  - Documentación del Roadmap no recalculada: ADR-036, 039, 040, 041 y 042 siguen `pendiente`.

---

### 2026-10-18 · Calidad · Pruebas de navegador con Pest 4
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385 · **PR:** → test
- **Hecho:**
  - Pest 3.8 → 4.7 y PHPUnit 11 → 12; suite completa en verde tras la actualización.
  - `pestphp/pest-plugin-browser` y Playwright con Chromium.
  - Suite `Browser` con `Tests\BrowserTestCase`, que usa el build real de Vite.
  - Primera prueba: el estudiante resuelve el examen que subió el docente.
  - Terreno de prueba manual en la base local con tinker:
    - docente `docente.examen@ues.edu.sv`;
    - estudiantes `est.examen1` a `est.examen3@ues.edu.sv`;
    - materia PRB101 con el «Examen de prueba».
- **Decisiones:** los diálogos `wire:confirm` se aceptan en la prueba con `window.confirm`, porque el plugin no los maneja.
- **Pendiente:**
  - Pasos de CI (`bun run build`, `playwright install`).
  - Corte de red del flujo 4.
  - Flujos 1 y 3 en navegador.

### 2026-10-18 · RF16 · Flujo del examen: cierre automático, autoguardado y monitor
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385 · **PR:** → test
- **Hecho:**
  - Job `CerrarIntentosVencidos` cada minuto.
  - Autoguardado de cada respuesta al cambiarla, aviso sin conexión y advertencia al salir.
  - Contador que se resincroniza y pregunta al servidor antes de enviar.
  - Monitor del docente con `wire:poll.15s`: cerrar intento y minutos extra a uno, varios o todos (hasta 30 min tras el cierre).
  - Corrección a los 10 min del fin efectivo.
  - Auditoría de `quiz_attempts`.
- **CP cubiertos:** CP-RF16-03, CP-RF16-06, CP-RF16-07.
- **Decisiones:** [[ADR-042 Flujo del examen sin depender de Reverb]] (pendiente); nota en ADR-010.
- **Pendiente:** eventos por Reverb para el monitor cuando se implemente RF23.

### 2026-10-18 · Transversal · Zona horaria de El Salvador
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385 · **PR:** → test
- **Hecho:**
  - `config/app.php` usa `APP_TIMEZONE` (antes estaba fijo en UTC) y la conexión `pgsql` fija la misma zona en la sesión.
  - Se quitó la conversión manual a UTC en Prórrogas y en el seeder demo.
  - Prueba nueva: la hora que escribe el docente es la que ve el estudiante.
- **Decisiones:** [[ADR-041 Zona horaria de El Salvador]] (pendiente).
- **Pendiente:**
  - `APP_TIMEZONE=America/El_Salvador` en Coolify y producción.
  - `migrate:fresh --seed` en las bases SQLite locales creadas antes.

### 2026-10-18 · RF35 RF15 RF16 · Revisión de actividades y solicitud de revisión
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385 · **PR:** → test
- **Hecho:**
  - **Revisión visible:**
    - La retroalimentación se ve solo con la nota publicada y ya no se duplica al recalificar.
    - El estudiante ve el desglose de la rúbrica y la nota antes de la penalización.
    - Botón «Publicar revisión» del cuestionario: corrección por pregunta y navegación libre en el intento terminado.
  - **RF35 (nuevo):**
    - El estudiante pide revisión de preguntas, criterios o la nota general.
    - El docente resuelve ítem por ítem en la pestaña «Revisiones» y la nota se recalcula; el Coordinador consulta.
    - Pendientes en la bandeja del docente.
  - **Avisos en la bandeja** (`Aviso` + `CanalAvisos`, cola `notificaciones`): nota publicada, revisión publicada, solicitud nueva y resuelta.
- **CP cubiertos:** CP-RF35-01 a 07, CP-RF15-06, CP-RF16-04.
- **Decisiones:** [[ADR-040 Solicitud de revisión de calificaciones]] (pendiente): plazo de 5 días configurable, una solicitud por actividad sin retiro, resuelve solo el docente, avisos solo en la bandeja. La corrección del cuestionario se muestra sola 10 minutos después del cierre (job programado para el aviso). Prórrogas del docente por estudiante: de entrega (a tiempo, sin penalización) y del plazo de revisión.
- **Pendiente:**
  - Aceptar ADR-040.
  - Envío de avisos por Reverb y correo (RF23).
  - Entregas grupales (CP-RF15-05).
  - En instalaciones existentes, correr `php artisan db:seed --class=BaseSystemSeeder` para crear los permisos y el parámetro nuevos.

### 2026-10-17 · RF01 RF04 · Cierre de pendientes: ADR aceptadas, auditoría de permisos y foto de Google
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#377, OP#385 · **PR:** → test
- **Hecho:**
  - ADR-037 y ADR-038 aceptadas.
  - Trigger de auditoría en `model_has_permissions` (`07_audit_permissions.sql`).
  - Foto de perfil enlazada desde Google (`users.avatar_url`): menú, Mi perfil, Usuarios y ficha. Si no carga, se muestran las iniciales.
- **Decisiones:** [[ADR-039 Foto de perfil enlazada desde Google]] (pendiente): la imagen no se descarga ni se aloja, para no servir contenido ajeno. Solo se aceptan enlaces https de `googleusercontent.com`.
- **Pendiente:** aceptar ADR-039; registrar en OpenProject el defecto «importar docentes no crea su perfil».

### 2026-10-17 · RF01 · Autorregistro sin rol
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#377 · **PR:** → test
- **Hecho:**
  - Una cuenta `@ues.edu.sv` válida que no existe se crea sin rol al primer ingreso y entra a un inicio con aviso.
  - Soporte da el primer rol. Filtro «Sin rol» en Usuarios.
  - El botón de Google ya no usa `wire:navigate`: el XHR de Livewire chocaba con el CORS de Google.
- **CP cubiertos:** CP-RF01-03 (reescrito), CP-RF04-18.
- **Decisiones:** [[ADR-038 Autorregistro sin rol]] (aceptada el 17/10/2026). Reemplaza «No hay autorregistro» de ADR-007 y ajusta el punto 2 de ADR-037.
- **Pendiente:** aceptar ADR-038.

### 2026-10-17 · RF04 · Jerarquía de creación y asignación de roles
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#¿? · **PR:** → test
- **Hecho:**
  - `JerarquiaService`: qué roles asigna cada rol. El Administrador, todos; Soporte, estudiantes y docentes con permiso; el Jefe, Docente y Coordinador en su departamento.
  - Soporte crea y corrige estudiantes, importa y matricula en cualquier sección.
  - El Jefe asigna roles, docentes a secciones y cupos en su departamento.
  - Solo el Administrador desactiva y concede `usuarios.crear-docente`.
  - Permisos nuevos `usuarios.crear-docente`, `usuarios.roles.asignar` y `secciones.cupo`; cambiar el cupo de una sección (no existía).
- **CP cubiertos:** CP-RF04-13 (actualizado), CP-RF04-15 a 17, CP-RF08-04 y 05, CP-RF09-11.
- **Decisiones:** [[ADR-037 Jerarquía de creación y asignación de roles]] (aceptada el 17/10/2026). Reemplaza en parte a ADR-032.
- **Pendiente:** aceptar ADR-037; `enrolled_via` propio para Soporte (CHECK); la importación de docentes no crea su perfil (defecto previo).

### 2026-10-17 · RF01 · Inicio de sesión con Google @ues.edu.sv
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#377 · **PR:** → test
- **Hecho:**
  - Socialite (Google) con `hd` en el redirect y validación en el servidor de `hd`, `email_verified` y el sufijo del correo (`GoogleAuthService`).
  - Vínculo del `sub` en el primer ingreso; cada intento queda en `login_history` con IP, user agent, resultado y motivo.
  - `php artisan google:verificar`: revisa la configuración, la conexión con Google y la validez del cliente OAuth.
- **CP cubiertos:** CP-RF01-01 a CP-RF01-06.
- **Decisiones:**
  - Las credenciales salen del `.env` (`config/services.php`), no de la pantalla Integraciones.
  - Si el usuario ya tiene un `sub` vinculado y Google envía otro (cuenta reciclada), se rechaza con motivo `sub_mismatch`.
  - Motivos de `login_history` en inglés, como en los seeders: `domain`, `not_registered`, `inactive`, `sub_mismatch`.
  - El comando valida el cliente canjeando un código inválido: `invalid_grant` = credenciales válidas; `invalid_client` = inválidas. La URI de redirección solo se comprueba en un ingreso real.
  - El valor por defecto de `session.lifetime` pasa de 120 a 30 min (RNF01).
- **Pendiente:**
  - Cuenta de emergencia con contraseña y TOTP (tarea aparte en OP#377).
  - Cliente OAuth de TI y prueba manual del ingreso real.

### 2026-10-13 · RF04 · Gestión de usuarios completa: edición, varios roles y asignaciones
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#385 · **PR:** → test → main
- **Hecho:**
  - El Administrador edita nombres y apellidos y los roles del usuario, que puede tener varios.
  - Asigna desde la ficha (y al crear) la carrera que coordina, el departamento que dirige, el perfil docente y la carrera del estudiante.
  - Auditoría por triggers de las cinco tablas de asignación (`06_audit_assignments.sql`).
  - «Asignar docente» (RF08) solo ofrece y acepta docentes con el rol y la cuenta activos.
  - Al desactivar una cuenta se cierran sus sesiones.
- **CP cubiertos:** CP-RF04-01 a CP-RF04-14 (06 y 14 solo en PostgreSQL). Suite completa en verde en SQLite y en PostgreSQL 17.
- **Decisiones:** [[ADR-036 Auditoría de asignaciones académicas]] (pendiente de aceptación):
  - Todo con `usuarios.gestionar` y alcance G; nadie edita sus propios roles.
  - Cada rol va atado a su asignación.
  - Relevo con histórico; el relevado pierde el rol.
  - El carné no se modifica.
  - El ciclo va de 1 a 12.
  - No se quita Docente con secciones abiertas ni Estudiante con matrículas activas.
  - El Jefe no cambia.
- **Pendiente:**
  - Aceptar ADR-036.
  - `composer check` no puede quedar en verde por causas anteriores a este cambio: Pint en `bootstrap/providers.php`, Larastan sin instalar y sin driver de cobertura.
  - Defectos aparte:
    - la importación permite roles sin su asignación y deja `student_code` nulo sin carné, y no va en cola;
    - `DbError` no se usa (errores 23505 llegan como 500);
    - el Mapa de vistas nombra mal `identidad.usuario-detalle`.

### 2026-10-11 · VARIOS · Cierres rápidos (RF03, RF05, RF09, RF16, RF29) y bug del disco
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#381 · OP#389 · OP#405 · OP#433 · OP#493 · bug OP#519 · **PR:** → test
- **Hecho:**
  - Exportar la auditoría a CSV con los mismos filtros y alcance (RLS) que la consulta, hasta 10 000 filas.
  - Porcentaje de aciertos por pregunta para el docente cuando el cuestionario cierra.
  - Bloqueo de 15 minutos tras 5 claves de auto-matrícula fallidas seguidas.
  - No se puede desactivar al último Administrador activo.
  - El estudiante pide cambio de grupo desde «Mis materias» con justificación, dentro del plazo del período.
  - `.env.example` usa el disco `local`: el disco `archivos` no existía y rompía toda subida.
- **CP cubiertos:** CP-RF03-03, CP-RF05-02, CP-RF09-07, CP-RF16-05, CP-RF29-05 (primeras pruebas con el ID del caso).
- **Decisiones:**
  - Acierto = la respuesta obtuvo el valor completo de la pregunta; los ensayos cuentan cuando tienen nota.
  - El plazo de cambio de grupo es `start_date` + `group_change_weeks` del período, hasta el fin de ese día.
- **Pendiente:**
  - Llevar notas y entregas a la nueva matrícula al aprobar un cambio de grupo.
  - Larastan no está en `require-dev`, aunque `composer check` lo usa.

### 2026-10-11 · BASE · Docker y despliegue en Coolify
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#518 · **PR:** → test → main
- **Hecho:** carpeta `docker/` (imagen única con roles web, horizon, scheduler y reverb) y `docker/compose.yml` con PostgreSQL 17 y Redis 7, para publicar `main` en Coolify como demo con «Entrar como…». Ver [[Setup del entorno#Despliegue con Docker (staging en Coolify)]].
- **Decisiones:**
  - Staging corre con `APP_ENV=staging`, dependencias de desarrollo (los seeders usan Faker) y la demo sembrada solo si la base está vacía.
  - Echo toma el host y el puerto de la página cuando no hay `VITE_REVERB_HOST`; nginx pasa `/app/` a Reverb.
  - Horizon atiende las 5 colas del proyecto y tiene entorno `staging`.
- **Pendiente:** archivos en disco local (volumen `archivos`) en vez de S3/MinIO; HTTPS y `trustProxies` cuando haya dominio real.

### 2026-10-11 · VISTAS · 58 de 60 vistas implementadas (sin commit; lo integra otro equipo)
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - `App\Support\Alcance` (G/D/C/S/M/P).
  - ServiceProvider por módulo (`ModuloServiceProvider`).
  - Traits `PaginaLivewire` y `PaginaDeSeccion`, y paginación con estilo.
  - Services, componentes Livewire y vistas de los 9 módulos:
    - Identidad: usuarios, roles, perfil, dispositivos, importación CSV e integraciones.
    - Auditoría.
    - Gestión académica: períodos, catálogo, secciones, carga académica, matrícula, claves, cambios de grupo y oferta.
    - Contenidos.
    - Foro.
    - Evaluaciones: tareas, entregas, rúbricas, bancos, cuestionarios e intentos.
    - Calificaciones: libro, boleta, acta, validación y cierre.
    - Asistencia: sesiones, código temporal, justificaciones, cancelaciones y áreas.
    - Inicio por rol, desempeño docente, reportes y ayuda (`php artisan ayuda:sincronizar`).
- **Defectos corregidos:**
  - `DbContext::within()` ocultaba el error original cuando fallaba una sentencia: ahora limpia el contexto solo si el trabajo termina bien.
  - Dos `each()` cortaban listas.
  - Conteos no portables a PostgreSQL.
- **Verificado:** 115 pruebas en SQLite y en PostgreSQL 17, incluida la de humo con la demo completa y las 6 cuentas de desarrollo.
- **Pendiente:** ver [[Plan de implementación#9. Estado de las vistas (11/10/2026)]]: QR y cámara, Meet, mensajes condicionales, envío de notificaciones, alertas, cierre del período, Excel/PDF y fórmulas provisionales de RF10 y RF33.

### 2026-10-11 · BASE · Esqueleto navegable y layouts según las maquetas
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#370 (complemento)
- **Hecho:** fase 0 de [[Plan de implementación]] ([[ADR-035 Vistas con Livewire]]):
  - Tema con la paleta UES oficial.
  - Layout Aplicación según la maqueta 17.
  - Navegación con `wire:navigate`, `@persist` y `wire:current`.
  - Registro de vistas con 60 rutas «En construcción», que muestran su ficha y su maqueta.
  - Middlewares de spatie.
  - Base de la Vista de sección y 7 componentes nuevos.
  - 31 maquetas de Stitch importadas en [[Maquetas]].
- **Verificado:**
  - Matriz de las 60 rutas con los 6 roles (200 o 403 según la [[Matriz RBAC]]).
  - 46 pruebas + 1 omitida en SQLite; 47 en PostgreSQL 17.
  - Pint y el build pasan.
- **Pendiente:** `App\Support\Alcance`, `x-ui.pagination`, `x-ui.dropzone`, `x-ui.week` y los contadores del menú ([[Plan de implementación#8.2 Lo que falta de la base (Equipo 1, antes de H1)]]).

### 2026-10-11 · BASE · Layouts, menú y componentes UI
- **Autor:** IA (Claude Code), a pedido del equipo
- **OpenProject:** OP#370 (componentes UI y layout), OP#374 (inicio de sesión de desarrollo)
- **Hecho:**
  - 4 layouts Blade (Invitado, Aplicación, Examen y Proyección) y el menú unificado de [[ADR-034 Navegación y layouts]] (`config/navegacion.php` + `App\Support\Navegacion`).
  - 15 componentes `x-ui.*`, páginas de error en español y traducción de los roles (`lang/es/roles.php`).
  - **Entrar como…** (`/dev/entrar-como`, solo con `LMS_DEV_LOGIN` y fuera de producción), `/login`, `/logout` y un `/inicio` con un bloque por rol.
  - ServiceProvider de los módulos Identidad y Reportes, que cargan `app/Modules/<Modulo>/routes.php`.
- **Decisiones:** los layouts piden sus datos a `Cabecera` en lugar de usar un view composer, porque Blade compila los componentes anónimos con namespace bajo un nombre con hash y el composer nunca se ejecutaba. Íconos de Heroicons copiados como SVG, sin dependencia nueva. Sin modo oscuro: los colores son variables `@theme`.
- **Verificado:** 32 pruebas + 1 omitida en SQLite; 33 en PostgreSQL 17; Pint; el CSS compila con todas las clases.
- **Defecto encontrado:** `bun run build` fallaba porque `resources/js/echo.js` importa `laravel-echo` y `pusher-js`, que no estaban en `package.json`. **Corregido en OP#517**: se agregaron como dependencias de desarrollo y se versiona `bun.lock`.
- **Pendiente:** contadores del menú, aviso de «Asistencia abierta» (con RF20), ayuda en `/ayuda` y panel real (RF24).

### 2026-10-11 · DOC · Layouts y navegación por rol
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:** nueva carpeta `13 Layout/`:
  - [[Layout base]]: 4 layouts, cabecera, menú unificado y vistas comunes (Inicio, Notificaciones, Mi perfil).
  - [[Vista de sección]] con sus pestañas por permiso.
  - Una nota por rol, con menú, árbol de vistas y flujos en Mermaid.
  - [[Mapa de vistas]]: ruta, layout, componente Livewire, permiso y RF de 68 vistas.
- **Decisiones:** [[ADR-034 Navegación y layouts]]: menú unificado por grupos para usuarios con varios roles, ☰ en el teléfono para todos y una sola Vista de sección.
- **Ajustes:** los manuales del Coordinador y de Soporte usan los nombres del menú nuevo («Importar estudiantes», «Solicitudes de dispositivo»).
- **Decisiones de detalle** (mismo día): se aceptaron las 7 recomendaciones ([[Layout base#Decisiones de detalle]]); [[RF07]], [[RF24]] y [[RF31]] actualizados.

### 2026-10-11 · BASE · Datos de prueba completos
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - 4 seeders: `BaseSystemSeeder` (todos los entornos), `InstitutionSeeder`, `DemoPeopleSeeder` y `DemoAcademicSeeder` (solo fuera de producción), dentro de `DbContext::system()`. Detalle en [[Setup del entorno#Datos de prueba]].
  - Catálogo inventado y volumen realista para staging y k6: 244 estudiantes, 30 docentes, 70 secciones y un período cerrado con resultados.
  - Las 6 cuentas `*.dev@ues.edu.sv` tienen datos para revisar el diseño de cada rol.
- **Defecto corregido:** en SQLite, el índice parcial «una sola cuenta de emergencia» perdía su `WHERE` al reconstruirse `users`, y solo permitía un usuario normal. Nuevo `Adaptive::restorePartialUnique()` y prueba de regresión ([[ADR-033 Migraciones adaptativas]]).
- **Verificado:**
  - SQLite: sin choques de aula, docente ni estudiante.
  - PostgreSQL 17: siembra completa con CHECK, FK compuestas y triggers activos. La auditoría registró 29 498 cambios con evento `seed`, y AC900 sigue rechazando escrituras sin actor.
  - Pruebas: 7 en PostgreSQL; 8 + 1 omitida en SQLite.
- **Hallazgo (pendiente de decidir):** el vault dice que `enrollment_results` «se congela» al cerrar el período, pero ningún trigger lo impide; hoy se puede modificar.
- **Pendiente:** factories del resto de modelos, `Alcance`, CI, Larastan (no está instalado).

### 2026-10-11 · BASE · Migraciones adaptativas del modelo v2
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - **Laravel Boost** revisado: el MCP responde con 10 herramientas. Se corrigieron las directrices y los subagentes, porque Boost 2.10 no tiene `list-routes` ni `tinker`. Nuevo `.claude/settings.json` que activa el MCP y permisos de comandos de prueba.
  - **spatie/laravel-permission** instalado.
  - **14 migraciones** del [[Modelo entidad-relación]] v2, adaptativas ([[ADR-033 Migraciones adaptativas]]): SQLite por defecto y la capa completa de PostgreSQL en `database/sql/pgsql/`.
  - `App\Support\Database\Adaptive`, `DbContext` (que limpia el contexto al terminar), `DbError`, `User` y factory, y seeder.
  - `.env.example` y `phpunit.xml` pasan a SQLite; el puerto de PostgreSQL del compose es configurable (`DB_FORWARD_PORT`).
  - `tests/Feature/Database/SchemaTest.php`.
- **Verificado:**
  - SQLite: 99 tablas, 16 índices parciales, FK compuestas, ida y vuelta, `migrate:fresh --seed`.
  - PostgreSQL 17 (contenedor temporal): 101 tablas, 27 triggers, 71 CHECK, EXCLUDE, RLS con un rol sin privilegios, ida y vuelta, `migrate:fresh`.
  - Pruebas: 7 en PostgreSQL y 7 + 1 omitida en SQLite.
- **Decisiones:** ADR-033; `notifications` compatible con Laravel (uuid + `notifiable`).
- **Pendiente:** factories del resto de modelos, roles sembrados, `Alcance`, CI.

### 2026-10-10 · RF34 · Revisión del documento de roles
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Contrastado *Roles y permisos.pdf* con el vault.
  - Nuevo [[RF34]] (validación de actas y cierre departamental), OP#513, rama `calificaciones-rf34-actas`.
  - Modelo: `grade_sheets`, `department_closures`, `is_anonymous`, `grade_items.unit_id`, clave por asignatura.
  - Matriz RBAC con 4 permisos nuevos y la tabla de equivalencias con el documento.
  - 166 casos de prueba.
- **Decisiones:** [[ADR-032 Ajustes por el documento de roles]]: Soporte sin matrícula, diagnóstico QR de solo lectura, calificación anónima en el alcance, boleta por unidad, clave por asignatura, roles seguros.
- **Pendiente:** informar al equipo de roles las diferencias que quedan (token de 10 s, Meet por reporte, mesa de ayuda solo para teléfonos, sin coordenadas GPS).

### 2026-10-10 · — · Modelo de datos v2 (revisión del diseño de @Alex)
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Revisado el documento «Diseño de Base de Datos – Sistema de Gestión Académica» de @Alex (170 páginas, 84 tablas).
  - Reescrito el [[Modelo entidad-relación]] en inglés.
  - Actualizados RF04, RF05, RF07, RF09, RF17, RF19, RF20, RF22, RF29, RNF02, la Matriz RBAC, los roles, 8 flujos, los manuales, la arquitectura, las pruebas y las directrices de IA.
  - Casos de prueba: de 137 a 148.
  - `phpunit.xml` apunta a PostgreSQL `lms_test` (creada por `scripts/postgres-init.sql`).
- **Decisiones:** ADR-027 a ADR-031; quedan reemplazadas ADR-002, ADR-004 y ADR-022.
- **No adoptado del diseño de @Alex:** contraseñas y recuperación, evidencias virtuales ponderadas, mesa de ayuda y equipos por sección (contradicen ADR vigentes).
- **Pendiente:** confirmar los parámetros de `system_settings` con el reglamento; recrear el volumen de PostgreSQL local para que se cree `lms_test`.

### 2026-10-10 · — · Plan cargado en OpenProject y ramas por RF
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Revisado OpenProject: solo tenía las fases académicas 1–3.
  - Cargada la «Fase 4: Desarrollo del sistema» (OP#356): 157 paquetes, versiones H0–H4 y 66 dependencias. La carga es idempotente.
  - 33 ramas desde `test` con el formato `<modulo>-rfxx-<corto>`, más `base-h0-tecnica`.
  - Propiedades `rama` y `op` en cada RF.
- **Decisiones:** convención de ramas `<modulo>-rfxx-<corto>`, que reemplaza a `feature/RFxx-op<ID>`.
- **Pendiente:** crear en OpenProject los campos RF, Módulo y Equipo (opcional); revocar la API key temporal.

### 2026-10-10 · — · Livewire 4, estructura de módulos y primer commit
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Livewire **4.4** (ADR-015 actualizada).
  - Estructura de los 9 módulos en `app/Modules`, con `Events`, `Listeners` y `Notifications` en cada uno, más carpetas de vistas, pruebas, soporte y ayuda, todas con `.gitkeep`.
  - Primer commit en `main`.
- **Decisiones:** Livewire 4 en lugar de 3.
- **Pendiente:** `bun install && bun run build`; rama `test`; repositorio remoto.

### 2026-10-10 · — · Repositorio creado y vault en docs/
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:**
  - Repositorio `~/Github/lms-ues` con Laravel 12 y la plataforma fijada a PHP 8.4.
  - Paquetes: Livewire **3.8** (fijado por ADR-015), Horizon, Reverb, predis, Pest 3 y Boost 2.10, con sus configuraciones publicadas.
  - Plantilla aplicada en la raíz y scripts de Composer fusionados.
  - Boost configurado para Claude Code, Codex, Copilot, Cursor y Antigravity. Las directrices van en `AGENTS.md`; `CLAUDE.md` lo importa.
  - Vault movido a `docs/` y `plantilla-repo` eliminada.
  - Pest (2 pruebas) y Pint en verde.
- **Decisiones:** Boost usa `AGENTS.md` para todas las herramientas (`config/boost.php`).
- **Pendiente:**
  - `bun install` y `bun run build`: no se pudieron correr (sin acceso al registro de npm desde la sesión).
  - Confirmar Livewire 3 frente a 4 (Composer propuso la 4.4).
  - Primer commit y repositorio remoto en GitHub.
  - Rama `test`.

### 2026-10-10 · — · Entorno y directrices de IA
- **Autor:** IA (Claude Code), a pedido del equipo
- **Hecho:** setup estándar para Linux y Windows, plantilla del repositorio, directrices de IA en modo orquesta con Laravel Boost.
- **Decisiones:** [[ADR-023 Entorno de desarrollo estandarizado]], [[ADR-024 IA con Laravel Boost y modo orquesta]].
- **Pendiente:** crear el repositorio, copiar la plantilla y el vault en `docs/`; plan de desarrollo hacia el 25/11.
