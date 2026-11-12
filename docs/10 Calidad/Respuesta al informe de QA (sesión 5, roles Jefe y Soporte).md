---
tipo: calidad
estado: en-curso
fecha: 2026-10-31
fuentes: ["Reporte de Pruebas (31/10/2026, 11 páginas): roles Jefe de Departamento, Estudiante y Soporte Técnico", "Informe del rol Administrador (sesión 3), hallazgos R-11 y R-12 de Reportes"]
rf: ["[[RF05]]", "[[RF10]]", "[[RF24]]", "[[RF25]]", "[[RF34]]"]
adr: ["[[ADR-063 Reactivar una matrícula retirada por error]]", "[[ADR-064 Duración del ciclo académico]]"]
---

# Respuesta al informe de QA · sesión 5 · roles Jefe y Soporte

La QA entregó el 31/10/2026 un informe nuevo, el «Reporte de Pruebas». Trae tres partes:

- **Jefe de Departamento:** observaciones abiertas.
- **Estudiante y Soporte Técnico:** todos los hallazgos marcados como **IMPLEMENTADO**.

Como en las sesiones anteriores, el informe no se versiona porque trae capturas del staging.

Esta sesión también cierra dos hallazgos de Reportes que el informe del Administrador había dejado abiertos en la [[Respuesta al informe de QA (sesión 3, rol Administrador)|sesión 3]]: **R-11** (gráficos) y **R-12** (filtro por período).

> [!info] Cómo leer esto
> Cada hallazgo es un **defecto** o una **mejora** (se corrige), una **decisión de diseño** que la prueba leyó como defecto (se explica o pasa a una ADR) o un efecto de los **datos de demostración** del staging.

## 1. Jefe de Departamento: corregido en esta sesión

| Hallazgo | Qué se hizo | PR | Prueba |
|---|---|---|---|
| «En auditoría no sé leer esa información… solo ustedes los programadores lo entienden» | La auditoría se lee en palabras: «Qué pasó», «Sobre» y una tabla «Qué cambió» con los nombres de campo y los estados en español (`lang/es/auditoria.php`). El registro técnico sigue disponible al desplegar. La exportación sale igual de legible | #83, #84 | CP-RF05-02 (incluye columnas JSON, que fallaban en PostgreSQL) |
| Oferta: «no sé de qué año ni de qué carrera es la materia» | Cada asignatura muestra la carrera y el **ciclo** de su plan («ISI · Ciclo 2»; el ciclo 2 es el primer año). Hay un filtro por carrera, limitado a su alcance | #83 | `RespuestaQaJefeTest` (3) |
| «Reporte: debe decir de qué carrera es, la sección de Matemática no tiene una sola carrera» | Nueva columna **Carrera** en los reportes por sección, con todas las carreras de la asignatura («ISI, LMA»). En «Estudiantes en riesgo» va la carrera del estudiante. Hay un filtro por carrera | este PR | CP-RF25-06 |
| «Aunque el botón está habilitado, no sé por qué no puedo autorizar el cierre» | El botón sigue deshabilitado mientras falten actas, y ahora un aviso dice cuántas faltan y de qué secciones; la tabla resalta las pendientes | #83 | `RespuestaQaJefeTest` (2) |
| Inicio: «especificar de quién es esa asistencia, de los docentes o de los estudiantes» | Las cifras del panel dicen que son de estudiantes, y la asistencia aclara que no incluye la de los docentes | #83 | `RespuestaQaJefeTest`, `CabeceraTest` |
| «Evitemos los emojis» | Los avisos de la Ayuda llevaban ⚠️ 💡 ℹ️ ⛔, y la guía de bienvenida un ✅. Ahora dicen «Atención:», «Consejo:», «Nota:» e «Importante:». Se corrigió en el vault (`docs/11 Manuales`), del que `ayuda:sincronizar` copia | este PR | `RespuestaQaJefeTest` |

### Reportes del Administrador (sesión 3)

| Hallazgo | Qué se hizo | Prueba |
|---|---|---|
| **R-12** · «No existen filtros; solo presenta información del período en curso» | Selector de período en Reportes: el en curso o uno cerrado, como en Desempeño docente. La exportación lleva el período y la carrera elegidos (en el PDF, en el subtítulo). Los envíos programados siguen con el período en curso | CP-RF25-07 |
| **R-11** · «Todos los datos se representan en tablas, sin gráficos» | Cada reporte tiene un gráfico de barras de su medida principal: asistencia por sección, promedio por sección, estudiantes por docente y alertas por tipo. Usa Chart.js, empaquetado con Vite (funciona sin Internet). La tabla sigue debajo con todos los datos | CP-RF25-08 |

> [!note] Sin probar por la QA
> Se verificaron con pruebas Pest en SQLite y en PostgreSQL. El gráfico también se vio en un navegador (Playwright): sin errores de JavaScript. Conviene que la QA los repruebe en staging.

## 2. Jefe de Departamento: decisiones de diseño

### 2.1 «¿Por qué el Jefe puede retirar a un estudiante? ¿Y si se equivoca?»
**Que el Jefe pueda retirar es una decisión tomada:** la [[Matriz RBAC]] le da `matricula.gestionar` con alcance D ([[ADR-030 Matrícula ampliada]], [[ADR-037 Jerarquía de creación y asignación de roles]]). El retiro pide motivo, queda en `enrollment_changes` y se audita.

La preocupación de fondo sí es válida: **hoy el retiro no se puede deshacer.** Volver a matricular al estudiante crea una matrícula nueva, y sus notas y su asistencia quedan colgadas de la anterior.

> [!warning] Pendiente: [[ADR-063 Reactivar una matrícula retirada por error]]
> La ADR propone un botón «Reactivar» que devuelve la misma matrícula a activa, con motivo y auditoría, validando otra vez el cupo y el choque de horario. Está sin implementar hasta que el equipo la acepte.

### 2.2 «Exportando a Excel necesitamos un formato estándar»
Las seis exportaciones del sistema ya pasan por el mismo `App\Support\Exportacion` ([[ADR-049 Exportación a Excel y PDF]]): una hoja con el nombre del reporte y los encabezados en negrita. El PDF lleva el membrete de la facultad y un subtítulo.

Lo que **no** tiene el Excel es una cabecera con la institución, el reporte, el período, los filtros y la fecha. Por eso un archivo exportado no dice de qué período es.

> [!success] Decisión: cabecera común en todo Excel (decidida por el usuario el 31/10/2026, implementada)
> Las seis exportaciones a Excel abren con tres filas, antes de los encabezados de columna:
> 1. «Universidad de El Salvador · Facultad Multidisciplinaria Oriental».
> 2. El reporte con su período, su sección o sus filtros, en palabras y con fechas DD/MM/AAAA.
> 3. «Generado el … por …».
>
> Va en `Exportacion::xlsx()`, así que cambian todas a la vez; queda anotado en [[ADR-049 Exportación a Excel y PDF]]. El CSV queda sin cabecera, para procesar los datos con otro programa. El costo: en Excel hay que seleccionar desde la fila 4 para filtrar como tabla. Prueba CP-RF25-03.

## 3. Jefe de Departamento: efecto de los datos de demostración

- **«La carrera no tiene materias, pero las secciones del departamento sí tienen materias con estudiantes inscritos.»** En el staging solo está cargado el plan de **ISI**: las carreras ICI, LMA y LAE tienen plan pero sin asignaturas (`InstitutionSeeder`). Las secciones de Matemática o Física son del departamento DCNM, pero sus asignaturas están en el plan de ISI y sus estudiantes son de ISI. **No es un defecto**: con los planes reales cargados, cada carrera muestra sus materias. Ahora la columna «Carrera» de la oferta y de los reportes hace visible esa relación.
- **«La oferta muestra Física I, pero no aparece en las secciones del departamento.»** Física I es del **ciclo 2**, y el período de demostración 2026-II solo abre secciones de los ciclos impares (1, 3, 5, 7 y 9; `DemoAcademicSeeder`). La oferta lista **todas** las asignaturas activas del departamento para proyectar cuántos grupos abrir; «Secciones» solo lista las abiertas en el período. Con la columna nueva, la oferta dice «ISI · Ciclo 2» y 0 inscritos, y eso lo explica.

## 4. Lo que la QA marcó como correcto o implementado

- **Jefe:** inscribir estudiantes («bien validada»).
- **Estudiante:** la notificación lleva al evento o tiene «Ver».
- **Soporte Técnico:** los 19 puntos del informe están marcados como IMPLEMENTADO. Entre ellos, la navegación, el solo-lectura del Diagnóstico, la trazabilidad de las solicitudes, la seguridad de sesión, el manual por tareas, el permiso para crear docentes, las preferencias de aviso por rol, la búsqueda por nombre en el Diagnóstico, el motivo del rechazo, el desglose del panel, los filtros del Diagnóstico, la confirmación antes de aprobar o rechazar, el contador del menú y el puente entre Solicitudes y Diagnóstico. No queda nada abierto de Soporte.

## 5. Pendiente

- Aceptar o ajustar [[ADR-063 Reactivar una matrícula retirada por error]] y [[ADR-064 Duración del ciclo académico]].
- Que la QA repruebe en staging las correcciones de las secciones 1 y 2.
