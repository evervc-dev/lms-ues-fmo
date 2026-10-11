---
tipo: adr
id: ADR-018
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF21]]", "[[RF06]]", "[[Integraciones con Google]]"]
fuentes: []
---

# ADR-018 · Eventos de Calendar con el permiso OAuth del docente

## Opciones
- **A. OAuth incremental del docente.** El evento queda en su calendario y no hace falta delegación en todo el dominio.
- **B. Cuenta de servicio con delegación.** Es transparente para el docente, pero TI debe autorizarla en todo el dominio.

## Decisión
**A.** *Decidido por el equipo el 10/10/2026.*

## Consecuencias
- El docente acepta el permiso `calendar.events` la primera vez que programa una clase.
- El refresh token se guarda cifrado; si se revoca, se pide de nuevo.
- Pendiente menor: el calendario de evaluaciones del período ([[Integraciones con Google]]).
