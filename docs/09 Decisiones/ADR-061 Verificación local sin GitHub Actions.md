---
tipo: adr
id: ADR-061
estado: pendiente
fecha: 2026-10-31
afecta: ["[[ADR-014 Ramas y CI en GitHub Actions]]", "[[Flujo de ramas y CI]]", "[[Definition of Done]]", "[[RNF07]]", "[[RNF14]]"]
fuentes: ["Decisión del usuario (31/10/2026)"]
---

# ADR-061 · Verificación local en lugar de GitHub Actions

> [!warning] Pendiente de aceptar

## Contexto
[[ADR-014 Ramas y CI en GitHub Actions]] puso la CI en GitHub Actions (`.github/workflows/ci.yml`, desde el 27/10/2026). **Nunca llegó a correr:** las 56 ejecuciones, desde la primera del 28/10, terminaron en `startup_failure` en 0 s. Un workflow mínimo (`echo ok`) falló igual, así que el problema no estaba en el archivo sino en la cuenta: el repositorio es privado, de una cuenta personal, y GitHub no arrancaba ningún workflow.

## Decisión
*Decidida por el usuario el 31/10/2026.*

1. **Se quita GitHub Actions:** se borra `.github/workflows/ci.yml` y se desactiva Actions en el repositorio. La parte de **ramas y PR** de ADR-014 sigue vigente.
2. **La verificación es local**, con evidencia en la descripción del PR:
   - PR a `test`: `composer check` (Pint, Larastan nivel 5 y cobertura ≥ 70 %).
   - PR de `test` a `main`: además, la suite Unit + Feature + Arch en **PostgreSQL 17** (4 procesos) y los flujos críticos en navegador ([[Estrategia de pruebas]]).

## Consecuencias
- Nada impide fusionar un PR sin verificar: depende de quien lo abre y de quien lo revisa.
- Las pruebas de triggers, RLS, CHECK y EXCLUDE solo corren si alguien levanta PostgreSQL en local.
- Railway (despliegue de staging y producción) es una app de GitHub, no Actions: sigue funcionando.
- Para volver a una CI automática hará falta otra ADR: arreglar la facturación de la cuenta, hacer público el repositorio o usar un runner propio.
