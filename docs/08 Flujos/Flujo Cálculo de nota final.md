---
tipo: flujo
nombre: Cálculo de nota final
modulo: "[[Calificaciones y progreso]]"
rf: ["[[RF17]]"]
estado: revisado
---

# Flujo · Cálculo de nota final

## Fórmula (parámetros provisionales)
```mermaid
flowchart TD
  A[grades de cada grade_item, de 0.0 a 10.0<br/>tardanzas descontadas; sin nota = 0] --> B[Nota del cómputo =<br/>Σ nota × peso de la actividad]
  B --> C[Nota final =<br/>Σ nota del cómputo × peso del cómputo]
  C --> R[Redondeo a 1 decimal, mitad hacia arriba]
  R --> D{¿≥ 6.0?}
  D -- Sí --> AP[APROBADO]
  D -- No --> RE[REPROBADO]
```

| Parámetro | Valor provisional | Configurable |
|---|---|---|
| Escala | 0.0–10.0 | No |
| Nota mínima de aprobación | **6.0** | Sí |
| Redondeo | **1 decimal, mitad hacia arriba** (5.95 pasa a 6.0) | Sí |
| Pesos | Deben sumar 100 % en cada nivel | — |
| Nota faltante | **Cuenta como 0** ([[ADR-030 Matrícula ampliada]]) | Sí |

> [!warning] Pendiente
> Confirmar con el reglamento académico de la UES: nota mínima, redondeo, número de cómputos y si hay examen de reposición.

## Cuándo se calcula
- **En vivo:** al publicar cualquier nota, como nota «parcial acumulada» y para el estado «en riesgo» (< 6.0 proyectado).
- **Al cerrar el período:** se congela en `enrollment_results`, con `policy_snapshot` (los parámetros aplicados) ([[Ciclo de vida del período académico]]).

## Corrección
```mermaid
flowchart LR
  Q[Cambiar una nota] --> P{¿Período en curso?}
  P -- Sí --> D[El docente corrige, queda auditado con el valor anterior y el nuevo]
  P -- Cerrado --> C[Solo el Coordinador, con motivo obligatorio]
  D & C --> RC[Recalcula la nota final y notifica al estudiante]
```
