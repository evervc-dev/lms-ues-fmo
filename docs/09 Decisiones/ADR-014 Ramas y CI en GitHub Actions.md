---
tipo: adr
id: ADR-014
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RNF14]]", "[[Flujo de ramas y CI]]"]
fuentes: [Requerimientos.pdf p.22]
---

# ADR-014 · Ramas main/test/feature y CI en GitHub Actions

> [!warning] La parte de CI la reemplaza [[ADR-061 Verificación local sin GitHub Actions]] (pendiente, 31/10/2026)
> GitHub Actions se quitó. Las ramas y los PR siguen como aquí.

## Contexto
[[RNF14]] exige revisión de código obligatoria antes de unir a la rama principal y pruebas de regresión antes de cada release.

## Decisión
*Decidido por el equipo el 10/10/2026:*
- Repositorio y CI en **GitHub Actions**.
- Ramas: `main` (protegida, producción), `test` (integración) y una rama por RF con formato `<modulo>-rfxx-<corto>`, creada desde `test` (actualizado el 10/10/2026).
- Los PR van **primero a `test`**, con 1 aprobación, y luego de `test` a `main`, con 2 aprobaciones, regresión completa y k6.

## Consecuencias
- Los entornos y previews de Coolify se definen en la fase 7.
- Detalle en [[Flujo de ramas y CI]].
