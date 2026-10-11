---
tipo: matriz
estado: borrador
fuente: Roles y permisos.pdf
---

# Matriz RBAC

Permisos con nombres listos para `spatie/laravel-permission`. Cada celda indica el **alcance** de datos con que se concede el permiso. spatie dice si el usuario tiene el permiso; **el alcance lo aplica cada Service** con `App\Support\Alcance`, que lee las asignaciones vigentes (`teaching_assignments`, `career_coordinators`, `department_heads`; ver [[Modelo entidad-relación]]). No se usan Policies ([[ADR-026 Autorización sin Policies]]).

**Alcances:** **G** = global · **D** = su departamento · **C** = su carrera · **S** = las secciones que imparte · **M** = las asignaturas donde está matriculado · **P** = solo lo propio · vacío = sin permiso.

Roles: [[Administrador]] (Adm) · [[Jefe de Departamento]] (Jefe) · [[Coordinador]] (Coord) · [[Docente]] (Doc) · [[Estudiante]] (Est) · [[Soporte Técnico]] (Sop)

## Identidad, seguridad y auditoría
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `usuarios.ver` | G | D | | | | G | [[RF04]], [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `perfiles.ver` | G | D | C | S | M | G | [[RF36]], [[ADR-056 Visibilidad contextual de perfiles]]: ver el perfil de otra persona solo con vínculo académico o jerárquico en el período en curso |
| `usuarios.gestionar` | G | | | | | G* | [[RF04]], [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `usuarios.crear-docente` | | | | | | (directo) | [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `usuarios.roles.asignar` | | D | | | | | [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `usuarios.importar` | G | | | | | G* | [[RF04]], [[ADR-030 Matrícula ampliada]], [[ADR-037 Jerarquía de creación y asignación de roles]], [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]] |
| `roles.gestionar` | G | | | | | | [[RF03]] |
| `integraciones.gestionar` | G | | | | | | [[RNF09]] |
| `horizon.ver` | G | | | | | | Panel de colas `/horizon`, vía el Gate `viewHorizon`, la única excepción de [[ADR-026 Autorización sin Policies]] |
| `auditoria.ver` | G | **D** | | | | | [[RF05]], [[ADR-029 Auditoría departamental del Jefe]] (RLS) |
| `dispositivo.registrar` | | | | | P | | [[RF27]] |
| `dispositivo.solicitar-cambio` | | | | | P | | [[RF27]] |
| `dispositivos.gestionar` | | | | | | G | [[RF27]] |

## Gestión académica
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `periodos.gestionar` | G | | | | | | [[RF06]], [[RF34]] (con este permiso el Administrador cierra el período, último eslabón de la cadena de actas) |
| `asignaturas.gestionar` | G | | | | | | [[RF07]] |
| `carreras.ver` | | D | C | | | | [[RF07]], [[RF08]], [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]] (el Administrador no lo necesita: ya tiene el catálogo) |
| `secciones.gestionar` | G | | C | | | | [[RF08]] |
| `secciones.asignar-docente` | G | D | C | | | | [[RF08]], [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `secciones.cupo` | G | D | | | | | [[RF08]], [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `matricula.gestionar` | G | D | C | S | | G | [[RF09]], [[ADR-030 Matrícula ampliada]], [[ADR-032 Ajustes por el documento de roles]], [[ADR-037 Jerarquía de creación y asignación de roles]] |
| `matricula.claves.gestionar` | | D | C | | | | [[RF29]], [[ADR-050 Inscripción por catálogo y claves compartidas]] |
| `matricula.automatricular` | | | | | P | | [[RF29]] |
| `matricula.plazo.gestionar` | G | D | C | | | | [[RF29]], [[ADR-051 Fecha límite de inscripción por departamento]] (el Coordinador, en los departamentos de sus carreras) |
| `matricula.plazo.prorrogar` | | D | | | | | [[RF29]], [[ADR-051 Fecha límite de inscripción por departamento]] |
| `matricula.prorroga.otorgar` | | D | C | | | | [[RF29]], [[ADR-051 Fecha límite de inscripción por departamento]] |
| `matricula.solicitar-cambio` | | | | | P | | [[RF09]] |
| `oferta.estadisticas.ver` | G | D | C | | | | [[RF10]] |
| `areas-asistencia.gestionar` | G | | | | | | [[RF26]] |

## Contenidos y evaluaciones
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `contenidos.ver` | | | C (lectura) | S | M | | [[RF11]], [[RF03]] |
| `contenidos.gestionar` | | | | S | | | [[RF11]], [[RF12]] |
| `contenidos.reutilizar` | | | | S | | | [[RF14]] |
| `contenidos.analitica.ver` | | | C | S | | | [[RF13]] |
| `tareas.gestionar` | | | | S | | | [[RF15]] |
| `tareas.entregar` | | | | | M | | [[RF15]] |
| `banco-preguntas.gestionar` | | | | S | | | [[RF16]] |
| `cuestionarios.gestionar` | | | | S | | | [[RF16]] |
| `cuestionarios.resolver` | | | | | M | | [[RF16]] |
| `foros.participar` | | | | S | M | | [[RF30]] |
| `foros.moderar` | | | | S | | | [[RF30]] |

## Calificaciones y progreso
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `calificaciones.ver` | | | C | S | P | | [[RF17]] |
| `calificaciones.gestionar` | | | | S | | | [[RF17]] |
| `calificaciones.revision.solicitar` | | | | | M | | [[RF35]], [[ADR-040 Solicitud de revisión de calificaciones]] |
| `calificaciones.revision.resolver` | | | | S | | | [[RF35]] |
| `calificaciones.revision.ver` | | | C | S | | | [[RF35]] |
| `actas.publicar` | | | | S | | | [[RF34]] |
| `actas.validar` | | | C | | | | [[RF34]] |
| `cierre.autorizar` | | D | | | | | [[RF34]] |
| `calificaciones.corregir` | | | C (período cerrado) | S (período abierto) | | | [[RF03]], [[RF17]] |
| `progreso.ver` | | | C | S | P | | [[RF18]] |

## Asistencia
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `asistencia.sesion.abrir` | | | | S | | | [[RF19]], [[RF20]] |
| `asistencia.marcar` | | | | | M | | [[RF20]] |
| `asistencia.codigo-temporal.generar` | | | | S | | | [[RF28]] |
| `asistencia.diagnostico.ver` | | | | | | G | [[RF20]] (solo lectura) |
| `asistencia.corregir` | | | | S | | | [[RF19]] |
| `asistencia.ver` | | | C | S | P | | [[RF19]], [[RF22]] |
| `videoconferencias.gestionar` | | | | S | | | [[RF21]] |
| `clases.solicitar-cancelacion` | | | | S | | | [[RF32]] |
| `clases.cancelar` | G | | C | | | | [[RF32]] |

## Comunicación, paneles y reportes
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop | RF |
|---|---|---|---|---|---|---|---|
| `mensajes.condicionales.enviar` | | | | S | | | [[RF31]] |
| `reportes.ver` | G | D | C | S | | | [[RF25]] |
| `reportes.programar` | G | D | C | | | | [[RF25]] |
| `desempeno-docente.ver` | | D | | | | | [[RF33]] |

Todos los roles tienen además `notificaciones.preferencias` y `perfil.sesiones.cerrar` sobre lo propio ([[RF23]], [[RNF01]]).

## Decidido
- Notas de un período cerrado: solo las corrige el **Coordinador**, con motivo obligatorio (10/10/2026).

- El **Administrador no** ve contenidos ni notas de los cursos ([[ADR-032 Ajustes por el documento de roles]]).
- **Jerarquía de usuarios** ([[ADR-037 Jerarquía de creación y asignación de roles]], 17/10/2026):
  - **Administrador:** crea y asigna todos los roles; es el único que desactiva y reactiva cuentas.
  - **Soporte (G\*):** crea y corrige cuentas de estudiantes; de docentes solo con `usuarios.crear-docente`, que el Administrador le concede a esa persona. Nunca a administradores, jefes ni coordinadores. Matricula en cualquier sección.
  - **Jefe:** no crea cuentas; da o quita Docente y Coordinador en su departamento, asigna docentes a sus secciones y cambia su cupo.
  - **Coordinador:** no asigna roles ni importa estudiantes; la carga masiva es del Administrador y de Soporte ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]).
- Soporte ve el diagnóstico de asistencia en solo lectura.
- **Roles seguros:** el Administrador asigna permisos a roles, pero no crea permisos nuevos; no se puede quitar `roles.gestionar` al último Administrador ([[RF03]]).
- **Actas:** el docente publica, el Coordinador valida, el Jefe autoriza el cierre de su departamento y el Administrador cierra el período ([[RF34]]).

## Equivalencias con el documento de roles
Cada permiso de *Roles y permisos.pdf* y su nombre técnico. Sirve para revisar y actualizar ese documento con el equipo de roles.

| Rol | Permiso en el documento | Permisos técnicos | Nota |
|---|---|---|---|
| Administrador | Administrar catálogo de materias | `asignaturas.gestionar`, `periodos.gestionar` | |
| Administrador | Crear, modificar, desactivar e importar usuarios | `usuarios.ver`, `usuarios.gestionar`, `usuarios.importar` | La importación también la hace Soporte ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]) |
| Administrador | Asignar, crear, modificar o revocar permisos y roles | `roles.gestionar` | No crea permisos nuevos (roles seguros) |
| Administrador | Consultar auditoría global | `auditoria.ver` (G) | |
| Administrador | Gestionar llaves de API e integraciones | `integraciones.gestionar` | También `horizon.ver` |
| Estudiante | Consultar recursos de sus asignaturas | `contenidos.ver` (M) | |
| Estudiante | Subir entregables y realizar exámenes | `tareas.entregar`, `cuestionarios.resolver` | |
| Estudiante | Crear hilos y publicar en foros | `foros.participar` (M) | |
| Estudiante | Marcar asistencia por QR y consultar historial | `asistencia.marcar`, `asistencia.ver` (P) | El token rota cada 10 s, no 5 s (ADR-028) |
| Estudiante | Registrar y gestionar su dispositivo (token de dispositivo) | `dispositivo.registrar`, `dispositivo.solicitar-cambio` | |
| Docente | Crear, modificar o eliminar módulos y archivos | `contenidos.gestionar`, `contenidos.reutilizar` (S) | |
| Docente | Administrar banco de preguntas y matrices de evaluación | `banco-preguntas.gestionar`, `cuestionarios.gestionar`, `tareas.gestionar` | La rúbrica se arma dentro de la tarea con `tareas.gestionar` ([[ADR-065 La rúbrica es parte de la tarea]]) |
| Docente | Registrar notas, calificar con rúbricas, libro de calificaciones | `calificaciones.gestionar`, `calificaciones.corregir`, `actas.publicar` (S) | Incluye la calificación anónima |
| Docente | Iniciar asistencia QR y configurar asistencia en Meet | `asistencia.sesion.abrir`, `asistencia.codigo-temporal.generar`, `videoconferencias.gestionar` | En Meet solo programa la clase y sube el reporte (ADR-003) |
| Docente | Filtrar alumnos y enviar mensajes masivos | `mensajes.condicionales.enviar` | |
| Coordinador | Asignar y modificar docentes | `secciones.gestionar`, `secciones.asignar-docente` (C) | |
| Coordinador | Inscribir o retirar estudiantes | `matricula.gestionar`, `matricula.claves.gestionar` (C) | Clave por asignatura (ADR-032) |
| Coordinador | Lectura de aulas, progreso y asistencias | `contenidos.ver`, `progreso.ver`, `asistencia.ver`, `calificaciones.ver` (C) | |
| Coordinador | *(enfoque)* Validar calificaciones finales | `actas.validar` (C) | [[RF34]] |
| Jefe | Consultar y descargar desempeño docente | `desempeno-docente.ver`, `reportes.ver` (D) | |
| Jefe | Inspeccionar auditoría departamental | `auditoria.ver` (D) | RLS (ADR-029) |
| Jefe | *(enfoque)* Autorizar cierres departamentales | `cierre.autorizar` (D) | [[RF34]] |
| Soporte | Dispositivos de asistencia (token de dispositivo) y su cambio | `dispositivos.gestionar` | |
| Soporte | Atender y cerrar solicitudes e incidentes | — | Solo solicitudes de cambio de teléfono; la mesa de ayuda general no se aprobó (P08) |
| Soporte | Inspeccionar logs de validación, GPS y tokens QR | `asistencia.diagnostico.ver` | Distancia y motivo, sin coordenadas (privacidad) |
