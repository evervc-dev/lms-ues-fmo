---
tipo: adr
id: ADR-030
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF04]]", "[[RF09]]", "[[RF29]]", "[[RF17]]", "[[RF19]]", "[[RF22]]", "[[Matriz RBAC]]", "[[Flujo Matrícula y cambio de grupo]]", "[[Flujo Carga masiva de usuarios]]"]
fuentes: ["Diseño de BD de @Alex, secciones 3, 9.5 y 17"]
---

# ADR-030 · Matrícula, carga masiva y parámetros académicos

> [!note] Actualizada por [[ADR-032 Ajustes por el documento de roles]]
> **Soporte Técnico ya no matricula** (sin privilegios académicos) y la **clave de matrícula es por asignatura**, no por sección.

> [!note] Modificada por [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]] (pendiente)
> La **carga masiva deja de ser del Coordinador**: queda en el Administrador y en Soporte Técnico.

> [!note] Restringida por [[ADR-051 Fecha límite de inscripción por departamento]]
> La matrícula tiene **fecha límite** por período y departamento: vencida, **tampoco la matrícula manual** inscribe sin prórroga.

*Decidido por el equipo el 10/10/2026, a partir del diseño de @Alex:*

## Quién inscribe
| Rol | Ámbito |
|---|---|
| Estudiante | A sí mismo, con la clave de la sección ([[RF29]]) |
| Docente | Sus secciones (asignación vigente) |
| Coordinador | Secciones de materias de su carrera |
| Jefe de Departamento | Secciones de carreras de su departamento |
| ~~Soporte Técnico~~ | ~~Cualquier sección~~ (quitado por ADR-032) |
| Administrador | Cualquier sección |

El **cambio de grupo** no cambia: lo solicita el estudiante y lo aprueba el Coordinador, hasta la 3.ª semana ([[Flujo Matrícula y cambio de grupo]]).

## Reglas de matrícula
- **Choque de horario del estudiante:** no puede tener dos matrículas activas cuyos horarios se solapen.
- **Prerrequisitos:** se registran en el catálogo, pero **no se validan** al matricular. Queda documentado para que nadie agregue la validación.
- Una sola matrícula activa por estudiante, materia y período (índice único parcial).

## Carga masiva
- La hace el **Administrador** (todas) y **Soporte Técnico**; el Coordinador ya no ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]], pendiente).
- Formato **Excel** (también se acepta CSV), con validación por fila. Cada lote queda en `import_batches` y cada error en `import_batch_errors`.

## Parámetros provisionales (`system_settings`, sin confirmar)
| Parámetro | Valor |
|---|---|
| `passing_score` | 6.0 |
| `grade_rounding` | 1 decimal, mitad hacia arriba |
| `missing_grade_policy` | Una nota faltante **cuenta como 0** |
| `attendance_partial_weight` | Asistencia parcial (tardanza) **vale 0,5** |
| Justificadas | **No cuentan** en el porcentaje |
| `min_attendance_percent` | 75 |
| Avisos de inasistencia | 85 y 78 |
| `max_scans_per_student` | 3 |

Todos se confirman con el reglamento académico (`is_confirmed`).
