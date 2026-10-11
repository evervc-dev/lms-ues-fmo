---
tipo: adr
id: ADR-058
estado: pendiente
fecha: 2026-10-28
afecta: ["[[RF17]]", "[[RF24]]", "[[RF23]]", "[[Flujo Cálculo de nota final]]"]
fuentes: ["Decisión del usuario (28/10/2026)", "docs/12 Desarrollo/Planes de implementación pendientes.md (Plan 3)"]
---

# ADR-058 · Fórmula del riesgo académico

> [!warning] Pendiente de aceptar

## Contexto
[[RF17]] pide que el estudiante por debajo del mínimo aparezca «en riesgo» en el panel del docente (CP-RF17-05). El [[Flujo Cálculo de nota final]] lo define como «< 6.0 proyectado», calculado en vivo al publicar cualquier nota. El panel ([[RF24]]) ya leía las alertas `grade_risk` de `academic_alerts`, pero nada las creaba: solo existían las del seeder.

## Decisión
*Aprobada por el usuario el 28/10/2026.*

1. **Nota proyectada:** promedio ponderado de las actividades con las notas **publicadas** (`grade_items.is_published`), reescalado al peso ya evaluado. Las actividades sin publicar no cuentan. En una actividad publicada, la nota que falta cuenta 0 (regla «nota faltante = 0»).
   - Dentro de cada cómputo: Σ(nota × peso) / Σ(peso de las actividades publicadas).
   - Entre cómputos: Σ(nota del cómputo × peso) / Σ(peso de los cómputos con alguna actividad publicada).
   - Sin ninguna actividad publicada no se evalúa.
2. **La alerta es por materia** (`grade_risk`, sin cómputo): se abre si la proyectada baja de `passing_score` y se cierra si llega a la mínima o más. También se cierra cuando la matrícula deja de estar activa o el período se cierra (el resultado ya está congelado).
3. **Se avisa al estudiante** al abrirse la alerta, con `academic_alert.opened` (`notified_at`).
4. **Disparadores:** el job `EvaluarRiesgoAcademico` (cola `default`, único por sección, idempotente y sin solaparse) se encola después del commit al publicar notas, al calificar una actividad ya publicada (directa, entrega de tarea o nota automática de un cuestionario), al resolver una revisión con cambios ([[RF35]]) y al corregir una nota.

## Alternativas consideradas
- **La nota final como en la boleta (las actividades sin publicar cuentan 0):** simple, pero al inicio del ciclo casi todos aparecerían en riesgo.
- **Sin avisar al estudiante:** menos ruido, pero el estudiante no se entera hasta ver su boleta.

## Consecuencias
- Publicar las notas de una actividad en la que un estudiante no tiene nota lo deja en riesgo si el 0 lo baja de la mínima; es coherente con «nota faltante = 0».
- Los valores siguen siendo los provisionales de `system_settings` ([[ADR-030 Matrícula ampliada]]).
