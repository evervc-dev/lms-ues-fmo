---
tipo: adr
id: ADR-093
estado: pendiente
fecha: 2026-11-24
afecta: ["[[RF39]]", "[[RF19]]", "[[RF30]]", "[[Matriz RBAC]]", "[[CP-RF39]]"]
fuentes: ["Pedido del usuario del 24/11/2026"]
---

# ADR-093 · Permisos de supervisión del Jefe y lectura de sesiones de asistencia

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- El Coordinador ya ve en solo lectura los contenidos, notas, asistencia (lista de sesiones), progreso y analítica de las secciones de su carrera.
- El Jefe de Departamento solo ve KPIs tabulares ([[RF33]]) y la auditoría departamental; no puede entrar a las pestañas de contenido, asistencia ni notas de las secciones de su departamento.
- Ninguno de los dos puede entrar al **detalle** de una sesión de asistencia (marcaciones individuales, hora de apertura/cierre), porque la ruta exige `asistencia.sesion.abrir` (solo Docente).
- Los foros solo los ven el Docente y el Estudiante (`foros.participar`); no hay permiso de solo lectura.
- El motivo de apertura fuera de horario ([[ADR-075 Apertura fuera de horario con motivo auditado]]) solo está en `audit_logs.reason`; el Coordinador no tiene acceso a la auditoría.

## Opciones
- **A. Ampliar los permisos del Jefe y crear `asistencia.sesion.ver` y `foros.ver`.**
- **B. Crear una pantalla de supervisión separada** que consolide la información en un lugar nuevo.
- **C. Dejar como está** (el Jefe usa RF33 y la auditoría).

## Decisión
**A.** *Decidida por el usuario el 24/11/2026.*

### Permisos nuevos

| Permiso | Descripción |
|---|---|
| `asistencia.sesion.ver` | Ver el detalle de una sesión de asistencia (marcaciones, hora apertura/cierre, razón fuera de horario) sin poder operar (abrir QR, corregir, cerrar). |
| `foros.ver` | Leer hilos y respuestas de foros sin poder crear, responder ni moderar. |

### Permisos ampliados al Jefe (alcance D)

| Permiso | Antes | Después |
|---|---|---|
| `contenidos.ver` | — | D |
| `contenidos.analitica.ver` | — | D |
| `calificaciones.ver` | — | D |
| `calificaciones.revision.ver` | — | D |
| `asistencia.ver` | — | D |
| `progreso.ver` | — | D |

### Asignación de los permisos nuevos

| Permiso | Jefe | Coord | Doc | Est |
|---|---|---|---|---|
| `asistencia.sesion.ver` | D | C | — | — |
| `foros.ver` | D | C | — | — |

El Docente no necesita `asistencia.sesion.ver` porque `asistencia.sesion.abrir` (S) ya le da acceso al detalle; la ruta acepta cualquiera de los dos.

### Ruta de detalle de sesión
El middleware cambia de `permission:asistencia.sesion.abrir` a aceptar `asistencia.sesion.ver` **o** `asistencia.sesion.abrir`. El componente ya distingue `$abre` y `$corrige`; quien entra con solo `asistencia.sesion.ver` ve la sesión sin botones de acción.

### Ruta de foro
El middleware del foro acepta `foros.ver` **o** `foros.participar`. Quien entra con solo `foros.ver` ve los hilos sin formularios de publicación ni respuesta.

### Razón de apertura fuera de horario
Se consulta desde `audit_logs` (fuente de verdad, ya existe). No se agrega columna a `attendance_sessions` para evitar desnormalización. La consulta solo funciona en PostgreSQL; en SQLite la razón no se muestra (aceptable: SQLite es solo para tests).

## Consecuencias
- Las pestañas de la Vista de sección se habilitan automáticamente con `Vistas::pestanas()` al tener el Jefe los permisos.
- Los tests existentes que verifican 403 sin `asistencia.sesion.abrir` deben ajustarse para aceptar `asistencia.sesion.ver` como alternativa.
- El Coordinador gana `foros.ver` (C), que antes no tenía; ahora puede leer los foros de su carrera.
