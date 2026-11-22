---
tipo: adr
id: ADR-084
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF29]]", "[[ADR-050 Inscripción por catálogo y claves compartidas]]"]
fuentes: ["Pruebas en staging del 21/11/2026: en 2026-II (ciclo II) el estudiante no veía ninguna materia sembrada, todas de ciclo impar"]
---

# ADR-084 · La inscripción lista todo el plan, sin la regla de paridad

> [!info] Pendiente de aceptar por el equipo. **Reemplaza el punto 2 de [[ADR-050 Inscripción por catálogo y claves compartidas]]** (materias de ciclo impar en el ciclo I y de ciclo par en el ciclo II).

## Contexto
- ADR-050 listaba solo las materias del plan con la paridad del ciclo en curso.
- En staging, los grupos sembrados son de materias de ciclo impar y el período 2026-II es ciclo II: el estudiante veía «No hay materias para inscribir» aunque hubiera grupos abiertos.

## Opciones
- **A. Todas las asignaturas con grupo abierto**, sean o no de su carrera.
- **B. Todo el plan de su carrera, de cualquier ciclo.**
- **C. No cambiar el código** y sembrar grupos de ciclo par.

## Decisión
**B.** *Decidida por el usuario el 21/11/2026.*
- `/inscripcion` lista las asignaturas activas del plan de la carrera del estudiante, de cualquier ciclo, agrupadas por el ciclo del plan.
- Se conserva «solo su carrera»: la clave de una asignatura de otro plan no se acepta («La materia no es del plan de su carrera»).
- `PeriodoService::ciclo()` y `ciclo_ii_mes_inicio` se conservan para el encabezado.

## Consecuencias
- CP-RF29-03, CP-RF29-08 y CP-RF29-09 cambian: una materia del plan de otro ciclo sí se lista, se abre y acepta su clave.
