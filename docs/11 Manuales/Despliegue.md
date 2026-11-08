---
tipo: tecnico
estado: esqueleto
rnf: ["[[RNF03]]", "[[RNF04]]", "[[RNF13]]"]
---

# Despliegue

> [!todo] Pendiente: [[ADR-020 Hosting y almacenamiento]]
> Ya están decididos la plataforma ([[ADR-015 Plataforma base]]), los entornos ([[ADR-019 Entornos]]: producción + staging) y los respaldos (spatie/laravel-backup). Faltan el hosting y los archivos.

## Lo ya decidido
- Ramas: `test` → integración y `main` → producción ([[Flujo de ramas y CI]]).
- No hay CI automática ([[ADR-061 Verificación local sin GitHub Actions]]): solo se fusiona, y por tanto se despliega, lo que pasó la verificación local.
- La app no guarda estado: sesiones, caché y colas en Redis; archivos en almacenamiento de objetos ([[RNF06]]).
- Procesos: web, Horizon, scheduler y Reverb, con PostgreSQL 17 y Redis 7 ([[Visión de arquitectura]]).
- Dominio bajo `ues.edu.sv` con TLS ([[RNF01]]); hay que tramitarlo con TI.
- Respaldo diario con spatie/laravel-backup y 30 días de retención; RTO < 4 h y RPO < 24 h ([[Respaldos y recuperación]]).

## Estructura (por completar)
1. Entornos y URL
2. Variables y secretos
3. Primer despliegue
4. Despliegues siguientes y migraciones sin corte
5. Respaldos y restauración (simulacro)
6. Monitoreo y alertas
7. Reversión (rollback)
