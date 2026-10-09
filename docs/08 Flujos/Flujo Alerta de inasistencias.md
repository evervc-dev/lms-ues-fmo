---
tipo: flujo
nombre: Alerta de inasistencias
modulo: "[[Asistencia]]"
rf: ["[[RF22]]", "[[RF23]]", "[[RF32]]"]
estado: revisado
---

# Flujo · Alerta de inasistencias

**Disparador:** cada vez que se confirma un registro de asistencia (QR, código, manual, Meet o justificación), por estudiante y cómputo.

```mermaid
flowchart TD
  E[Evento: asistencia confirmada] --> C[Recalcula el % del cómputo:<br/>presentes + 0,5 × tardanzas ÷ clases dictadas<br/>sin contar canceladas ni justificadas]
  C --> A{% actual}
  A -- "≥ 85 %" --> OK[Sin aviso]
  A -- "< 85 % y ≥ 78 %" --> P{¿Ya recibió el aviso preventivo<br/>en este cómputo?}
  P -- No --> NP[Aviso preventivo al estudiante]
  A -- "< 78 %" --> K{¿Ya recibió el aviso crítico<br/>en este cómputo?}
  K -- No --> NK[Aviso crítico al estudiante + alerta en el panel del docente]
  A -- "< 75 %" --> R[Marca «en riesgo por inasistencia» en el panel del docente y el Coordinador]
```

## Reglas
| Umbral | Acción |
|---|---|
| **85 %** | Aviso **preventivo**, una vez por cómputo |
| **78 %** | Aviso **crítico** al estudiante y alerta al docente, una vez por cómputo |
| **< 75 %** | Estado «en riesgo por inasistencia» (mínimo reglamentario) |

- Los umbrales son configurables por si el reglamento cambia.
- Los avisos se guardan en `academic_alerts` y no se duplican: una alerta abierta por matrícula, tipo y cómputo ([[RNF12]]).
- La tardanza vale 0,5 y las justificadas no cuentan como falta ([[ADR-030 Matrícula ampliada]]).
- Las clases canceladas ([[RF32]]) y las inasistencias justificadas no cuentan como falta.
