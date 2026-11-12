---
tipo: layout
nombre: Mapa de vistas
estado: borrador
adr: ["[[ADR-034 Navegación y layouts]]", "[[ADR-026 Autorización sin Policies]]", "[[ADR-017 Código modular por dominio]]"]
---

# Mapa de vistas

Referencia para desarrollo: cada vista con su **ruta**, **layout**, **componente Livewire**, **permiso** que protege la ruta (middleware `permission:`) y **RF**. El alcance (G/D/C/S/M/P) lo aplica el Service con `App\Support\Alcance` ([[ADR-026 Autorización sin Policies]]).

- **Componente:** alias `modulo.nombre`, con la clase en `app/Modules/<Modulo>/Livewire/` y la vista en `resources/views/modules/<modulo>/` ([[Estructura del código]]).
- **Layout:** App = Aplicación · Inv = Invitado · Exa = Examen · Proy = Proyección ([[Layout base#Los 4 layouts]]).
- **Roles:** Adm · Jefe · Coord · Doc · Est · Sop, con su alcance entre paréntesis.
- Las rutas son una **propuesta** para H0; si una cambia al implementarla, se actualiza aquí.

## Comunes
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Iniciar sesión | `/login` | Inv | `identidad.login` | — (invitado) | Todos | [[RF01]] |
| Cuenta no habilitada | `/login/no-habilitada` | Inv | (Blade) | — | Todos | [[RF01]] |
| Acceso de emergencia | `/acceso-emergencia` | Inv | `identidad.acceso-emergencia` | — (invitado, limitado por intentos) | Cuenta de emergencia | [[ADR-007 Inicio de sesión con Google]] |
| Entrar como… | `/dev/entrar-como` | Inv | `identidad.entrar-como` | Solo con `LMS_DEV_LOGIN=true` (local y staging) | Desarrollo | [[Setup del entorno]] |
| Inicio (panel) | `/inicio` | App | `reportes.panel` | — (sesión iniciada) | Todos | [[RF24]] |
| Notificaciones | `/notificaciones` | App | `comunicacion.notificaciones` | — | Todos (P) | [[RF23]] |
| Mi perfil · Datos personales | `/perfil` | App | `identidad.perfil` | — | Todos (P) | [[RF01]] |
| Mi perfil · Dispositivo | `/perfil/dispositivo` | App | `identidad.dispositivo` | `dispositivo.registrar` | Est (P) | [[RF27]] |
| Mi perfil · Google Calendar | `/perfil/google-calendar` | App | `asistencia.google-calendar` | `videoconferencias.gestionar` | Doc (P) | [[ADR-018 Calendar con OAuth del docente]] |
| Mi perfil · Notificaciones | `/perfil/notificaciones` | App | `comunicacion.preferencias` | `notificaciones.preferencias` | Todos (P) | [[RF23]] |
| Mi perfil · Sesiones | `/perfil/sesiones` | App | `identidad.sesiones` | `perfil.sesiones.cerrar` | Todos (P) | [[RNF01]] |
| Mi perfil · Seguridad (2FA) | `/perfil/seguridad` | App | `identidad.dos-factores` | Solo cuenta de emergencia | Adm | [[ADR-007 Inicio de sesión con Google]] |
| Ayuda | `/ayuda/{manual}` | App | (Blade + Markdown) | — | Todos | [[Ayuda en la app]] |

## Estudiante
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Escanear QR | `/asistencia/escanear` | App | `asistencia.escanear` | `asistencia.marcar` | Est (M) | [[RF20]], [[RF28]] |
| Mis materias | `/materias` | App | `academica.mis-materias` | `contenidos.ver` | Est (M) | [[RF18]], [[RF24]] |
| Inscripción de materias | `/inscripcion` | App | `academica.inscripcion` | `matricula.automatricular` | Est (P) | [[RF29]], [[ADR-050 Inscripción por catálogo y claves compartidas]] |
| Materia (descripción, grupos e «Inscribirme con clave») | `/inscripcion/{asignatura}` | App | `academica.inscripcion-detalle` | `matricula.automatricular` | Est (P) | [[RF29]] |
| Solicitar cambio de grupo (modal) | `/secciones/{seccion}` | App | `academica.solicitar-cambio-grupo` | `matricula.solicitar-cambio` | Est (P) | [[RF09]] |
| Mis notas | `/mis-notas` | App | `calificaciones.mis-notas` | `calificaciones.ver` | Est (P) | [[RF17]] |
| Mi asistencia | `/mi-asistencia` | App | `asistencia.mi-asistencia` | `asistencia.ver` | Est (P) | [[RF22]] |
| Intento de cuestionario | `/intentos/{intento}` | Exa | `evaluaciones.intento` | `cuestionarios.resolver` | Est (M) | [[RF16]] |

## Vista de sección
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Listado de secciones | `/secciones?ambito=propias\|carrera\|departamento` | App | `academica.secciones` | `contenidos.ver` o `matricula.gestionar` | Doc (S) · Coord (C) · Jefe (D) | [[RF08]] |
| Contenidos | `/secciones/{seccion}` | App | `contenidos.unidades` | `contenidos.ver` (editar: `contenidos.gestionar`) | Est (M) · Doc (S) · Coord (C, lectura) | [[RF11]] |
| Agregar recurso | `/secciones/{seccion}/unidades/{unidad}/recursos/crear` | App | `contenidos.nuevo-recurso` | `contenidos.gestionar` | Doc (S) | [[RF11]], [[RF12]] |
| Ver recurso | `/secciones/{seccion}/recursos/{recurso}` | App | `contenidos.recurso` | `contenidos.ver` | Est (M) · Doc (S) · Coord (C) | [[RF11]], [[RF12]] |
| Papelera | `/secciones/{seccion}/papelera` | App | `contenidos.papelera` | `contenidos.gestionar` | Doc (S) | [[RF11]] |
| Importar de un período anterior | `/secciones/{seccion}/importar` | App | `contenidos.importar` | `contenidos.reutilizar` | Doc (S) | [[RF14]] |
| Actividades | `/secciones/{seccion}/actividades` | App | `evaluaciones.actividades` | `contenidos.ver` | Est (M) · Doc (S) · Coord (C) | [[RF15]], [[RF16]] |
| Tarea (detalle y entrega) | `/secciones/{seccion}/tareas/{tarea}` | App | `evaluaciones.tarea` | `tareas.entregar` o `tareas.gestionar` | Est (M) · Doc (S) | [[RF15]] |
| Crear o editar tarea | `/secciones/{seccion}/tareas/crear` | App | `evaluaciones.tarea-formulario` | `tareas.gestionar` | Doc (S) | [[RF15]] |
| Entregas de una tarea | `/secciones/{seccion}/tareas/{tarea}/entregas` | App | `evaluaciones.entregas` | `calificaciones.gestionar` | Doc (S) | [[RF15]] |
| Cuestionario | `/secciones/{seccion}/cuestionarios/{cuestionario}` | App | `evaluaciones.cuestionario` | `cuestionarios.resolver` o `cuestionarios.gestionar` | Est (M) · Doc (S) | [[RF16]] |
| Crear o editar cuestionario | `/secciones/{seccion}/cuestionarios/crear` | App | `evaluaciones.cuestionario-formulario` | `cuestionarios.gestionar` | Doc (S) | [[RF16]] |
| Notas (boleta o libro) | `/secciones/{seccion}/notas` | App | `calificaciones.libro` | `calificaciones.ver` (editar: `calificaciones.gestionar`; corregir: `calificaciones.corregir`) | Est (P) · Doc (S) · Coord (C) | [[RF17]], [[RF18]], [[RF31]] |
| Mensajes enviados | `/secciones/{seccion}/notas/mensajes` | App | `comunicacion.mensajes-enviados` | `mensajes.condicionales.enviar` | Doc (S) | [[RF31]] |
| Mensajear a estudiantes que… | `/secciones/{seccion}/notas/mensajes/crear` | App | `comunicacion.mensaje-condicional` | `mensajes.condicionales.enviar` | Doc (S) | [[RF31]] |
| Revisiones | `/secciones/{seccion}/revisiones` | App | `calificaciones.revisiones` | `calificaciones.revision.ver` | Doc (S) · Coord (C, lectura) | [[RF35]] |
| Solicitud de revisión | `/secciones/{seccion}/revisiones/{revision}` | App | `calificaciones.revision` | `calificaciones.revision.ver` (resuelve: `calificaciones.revision.resolver`) | Doc (S) · Coord (C, lectura) | [[RF35]] |
| Acta | `/secciones/{seccion}/acta` | App | `calificaciones.acta` | `actas.publicar` | Doc (S) | [[RF34]] |
| Asistencia de la sección | `/secciones/{seccion}/asistencia` | App | `asistencia.sesiones` | `asistencia.ver` | Est (P) · Doc (S) · Coord (C) | [[RF19]], [[RF22]] |
| Sesión de asistencia | `/secciones/{seccion}/asistencia/{sesion}` | App | `asistencia.sesion` | `asistencia.sesion.abrir` | Doc (S) | [[RF19]], [[RF21]], [[RF28]], [[RF32]] |
| Proyectar QR | `/secciones/{seccion}/asistencia/{sesion}/proyectar` | Proy | `asistencia.sesion-qr` | `asistencia.sesion.abrir` | Doc (S) | [[RF20]] |
| Foro | `/secciones/{seccion}/foro` | App | `comunicacion.foro` | `foros.participar` | Est (M) · Doc (S) | [[RF30]] |
| Hilo del foro | `/secciones/{seccion}/foro/{hilo}` | App | `comunicacion.hilo` | `foros.participar` (moderar: `foros.moderar`) | Est (M) · Doc (S) | [[RF30]] |
| Estudiantes de la sección | `/secciones/{seccion}/estudiantes` | App | `academica.estudiantes-seccion` | `matricula.gestionar` | Doc (S) · Coord (C) · Jefe (D) | [[RF09]] |
| Analítica | `/secciones/{seccion}/analitica` | App | `contenidos.analitica` | `contenidos.analitica.ver` | Doc (S) · Coord (C) | [[RF13]] |

## Docente
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Pendientes de calificar | `/pendientes-de-calificar` | App | `evaluaciones.pendientes` | `calificaciones.gestionar` | Doc (S) | [[RF15]], [[RF16]], [[RF24]] |
| Bancos de preguntas | `/bancos-de-preguntas` | App | `evaluaciones.bancos` | `banco-preguntas.gestionar` | Doc (S) | [[RF16]] |
| Banco (preguntas) | `/bancos-de-preguntas/{banco}` | App | `evaluaciones.banco` | `banco-preguntas.gestionar` | Doc (dueño o compartido) | [[RF16]] |
| Nueva pregunta | `/bancos-de-preguntas/{banco}/preguntas/crear` | App | `evaluaciones.nueva-pregunta` | `banco-preguntas.gestionar` | Doc (dueño o compartido) | [[RF16]] |

## Coordinación y administración académica
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Carga académica | `/carga-academica` | App | `academica.carga-academica` | `secciones.gestionar` | Adm (G) · Coord (C) | [[RF08]] |
| Matrícula · Claves | `/matricula/claves` | App | `academica.claves` | `matricula.claves.gestionar` | Jefe (D), Coord (C) | [[RF29]] |
| Matrícula · Plazos | `/matricula/plazos` | App | `academica.plazos` | `matricula.plazo.gestionar` \| `matricula.prorroga.otorgar` | Adm (G), Jefe (D), Coord (C) | [[RF29]], [[ADR-051 Fecha límite de inscripción por departamento]] |
| Matrícula · Estudiantes | `/matricula/estudiantes` | App | `academica.matricula` | `matricula.gestionar` | Adm (G) · Coord (C) | [[RF09]] |
| Matrícula · Solicitudes | `/matricula/solicitudes` | App | `academica.solicitudes-cambio` | `matricula.gestionar` | Coord (C) | [[RF09]] |
| Importar usuarios | `/usuarios/importar` | App | `identidad.importar` | `usuarios.importar` | Adm (G) · Coord (C) | [[RF04]] |
| Lote de importación | `/usuarios/lotes/{lote}` | App | `identidad.lote` | `usuarios.importar` | Adm (G) · Coord (C, propios) | [[RF04]] |
| Actas por validar | `/actas` | App | `calificaciones.actas` | `actas.validar` | Coord (C) | [[RF34]] |
| Clases canceladas | `/clases-canceladas` | App | `asistencia.cancelaciones` | `clases.cancelar` | Adm (G) · Coord (C) | [[RF32]] |
| Cierre del período | `/cierre` | App | `calificaciones.cierre` | `cierre.autorizar` | Jefe (D) | [[RF34]] |
| Desempeño docente | `/desempeno-docente` | App | `reportes.desempeno-docente` | `desempeno-docente.ver` | Jefe (D) | [[RF33]] |

## Administración y soporte
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Usuarios | `/usuarios` | App | `identidad.usuarios` | `usuarios.ver` (editar: `usuarios.gestionar`) | Adm (G) · Sop (G, lectura) | [[RF04]] |
| Nuevo usuario | `/usuarios/crear` | App | `identidad.crear-usuario` | `usuarios.gestionar` | Adm (G) · Sop (G) | [[RF04]] |
| Usuario | `/usuarios/{usuario}` | App | `identidad.usuario` | `usuarios.ver` | Adm (G) · Sop (G, lectura) | [[RF04]] |
| Roles y asignaciones | `/usuarios/{usuario}/roles` | App | `identidad.roles-usuario` | `usuarios.ver` (lo que puede cambiar lo decide la jerarquía, [[ADR-037 Jerarquía de creación y asignación de roles]]) | Adm (G) · Jefe (D) | [[RF04]] |
| Roles y permisos | `/roles` | App | `identidad.roles` | `roles.gestionar` | Adm (G) | [[RF03]] |
| Períodos | `/periodos` | App | `academica.periodos` | `periodos.gestionar` | Adm (G) | [[RF06]] |
| Período | `/periodos/{periodo}` | App | `academica.periodo` | `periodos.gestionar` | Adm (G) | [[RF06]], [[RF34]] |
| Catálogo · Asignaturas | `/catalogo/asignaturas` | App | `academica.asignaturas` | `asignaturas.gestionar` | Adm (G) | [[RF07]] |
| Catálogo · Departamentos, carreras, planes y aulas | `/catalogo/...` | App | `academica.catalogo` | `asignaturas.gestionar` | Adm (G) | [[RF07]] |
| Áreas de asistencia | `/areas-asistencia` | App | `asistencia.areas` | `areas-asistencia.gestionar` | Adm (G) | [[RF26]] |
| Integraciones | `/integraciones` | App | `identidad.integraciones` | `integraciones.gestionar` | Adm (G) | [[RNF09]] |
| Colas | `/horizon` | Horizon | (panel de Horizon) | Gate `viewHorizon` (`horizon.ver`) | Adm (G) | [[ADR-026 Autorización sin Policies]] |
| Solicitudes de dispositivo | `/dispositivos` | App | `identidad.solicitudes-dispositivo` | `dispositivos.gestionar` | Sop (G) | [[RF27]] |
| Diagnóstico de asistencia | `/asistencia/diagnostico` | App | `asistencia.diagnostico` | `asistencia.diagnostico.ver` | Sop (G, lectura) | [[RF20]] |

## Consultas
| Vista | Ruta | Layout | Componente | Permiso | Roles | RF |
|---|---|---|---|---|---|---|
| Reportes | `/reportes` | App | `reportes.reportes` | `reportes.ver` (programar: `reportes.programar`) | Adm (G) · Jefe (D) · Coord (C) · Doc (S) | [[RF25]] |
| Estadísticas de oferta | `/oferta` | App | `academica.oferta` | `oferta.estadisticas.ver` | Adm (G) · Jefe (D) · Coord (C) | [[RF10]] |
| Auditoría | `/auditoria` | App | `auditoria.eventos` | `auditoria.ver` | Adm (G) · Jefe (D, RLS) | [[RF05]] |
