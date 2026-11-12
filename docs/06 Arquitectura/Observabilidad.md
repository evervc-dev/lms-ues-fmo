---
tipo: arquitectura
estado: pendiente
adr: ["[[ADR-021 Monitoreo]]"]
rnf: ["[[RNF03]]"]
---

# Observabilidad

> [!warning] Sin monitoreo por ahora
> El equipo decidió **no incorporar herramientas de monitoreo por ahora** ([[ADR-021 Monitoreo]]).
> **Consecuencia:** no se puede **demostrar** el 99 % de disponibilidad de [[RNF03]] ni detectar caídas antes que los usuarios.

## Lo mínimo que ya existe
- Logs de Laravel a `stderr` (visibles en los logs del contenedor).
- Panel de **Horizon**: colas, tiempos de espera y jobs fallidos.
- `backup:monitor`: alerta por correo si falla el respaldo.
- Registro de inicios de sesión y auditoría.

## Cuando se retome
| Necesidad | Opciones evaluadas |
|---|---|
| Disponibilidad externa y alertas | Uptime Kuma (autoalojado) |
| Rendimiento interno | Laravel Pulse |
| Excepciones | Sentry (gratuito o autoalojado) |
| Todo en uno | Laravel Nightwatch (de pago) |
