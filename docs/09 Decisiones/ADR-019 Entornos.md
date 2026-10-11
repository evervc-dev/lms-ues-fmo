---
tipo: adr
id: ADR-019
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Entornos]]", "[[Flujo de ramas y CI]]", "[[Pruebas de carga]]"]
fuentes: []
---

# ADR-019 · Producción y staging, sin previews

## Decisión
*Decidido por el equipo el 10/10/2026:* dos entornos desplegados.
- `main` → **producción**.
- `test` → **staging**, con datos falsos.

No hay previews por PR.

## Consecuencias
- k6 corre contra staging antes de cada PR de `test` a `main`.
- La revisión visual de un PR se hace en local o después de unirlo a `test`.
