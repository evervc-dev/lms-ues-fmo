---
tipo: adr
id: ADR-021
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Observabilidad]]", "[[RNF03]]"]
fuentes: []
---

# ADR-021 · Sin herramienta de monitoreo por ahora

## Decisión
*Decidido por el equipo el 10/10/2026:* **no** se incorpora una herramienta de monitoreo por ahora.

## Consecuencias
- No se puede demostrar el 99 % de disponibilidad de [[RNF03]] ni recibir alertas de caída.
- Quedan Horizon, los logs, `backup:monitor` y la auditoría ([[Observabilidad]]).
- Se recomienda revisar esta decisión antes de salir a producción.
