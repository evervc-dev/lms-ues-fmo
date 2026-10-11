---
tipo: adr
id: ADR-059
estado: pendiente
fecha: 2026-10-30
afecta: ["[[RF06]]", "[[RF34]]", "[[RF23]]", "[[Ciclo de vida del período académico]]"]
fuentes: ["Decisión del usuario (30/10/2026)"]
---

# ADR-059 · Reapertura de un período cerrado

> [!warning] Pendiente de aceptar

## Contexto
[[RF06]] y el [[Ciclo de vida del período académico]] dicen que **solo el Administrador** reabre un período cerrado, con motivo obligatorio y auditoría, sin otro período en curso, y que se avisa a los Coordinadores. No dicen qué pasa con lo que dejó el cierre: las autorizaciones de los departamentos (`department_closures`, [[RF34]]) y los resultados congelados (`enrollment_results`, [[RF17]]). Tampoco con qué tipo de aviso se notifica.

## Decisión
*Decidida por el usuario el 30/10/2026.*

1. **Se conserva todo.** Reabrir solo pasa el período de `closed` a `active`. No se borran las autorizaciones de cierre ni los resultados congelados, y las actas no vuelven a borrador.
2. **Al volver a cerrar se recalculan los resultados.** `CalificacionService::congelarResultados()` sobrescribe `enrollment_results` con la política vigente (`updateOrInsert`). Como las autorizaciones siguen en pie, el Administrador puede cerrar de nuevo sin repetir la cadena de actas.
3. **El motivo va a la auditoría** (`audit_logs.reason`, evento `period.reopened`). No se agregó una columna en `academic_periods`.
4. **Aviso nuevo `period.reopened`** («Período reabierto») para los Coordinadores vigentes, con el motivo. No se agrupa, para que el motivo no se pierda, y el Coordinador puede configurarlo en sus preferencias.

## Consecuencias
- La reapertura sirve para una corrección puntual. Si hiciera falta repetir toda la validación, habría que anular a mano las autorizaciones del departamento, lo que hoy no tiene pantalla.
- Mientras el período está reabierto, `enrollment_results` muestra los valores del cierre anterior hasta el nuevo cierre.
- Afecta a [[RF06]] (CP-RF06-02) y al catálogo de avisos de [[RF23]].
