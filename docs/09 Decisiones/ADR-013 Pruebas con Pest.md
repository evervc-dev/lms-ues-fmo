---
tipo: adr
id: ADR-013
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RNF07]]", "[[RNF10]]", "[[RNF14]]", "[[Estrategia de pruebas]]"]
fuentes: [Requerimientos.pdf p.19, p.22]
---

# ADR-013 · Pruebas con Pest, cobertura obligatoria y SQLite en memoria

> [!note] Actualizada por [[ADR-027 Base de datos híbrida]]
> Las suites que tocan la base de datos (Feature y Browser) corren en **PostgreSQL**, no en SQLite.

## Contexto
[[RNF07]] pide PHPUnit con cobertura de 70 % o más. [[RNF14]] pide pruebas unitarias, de integración y de regresión, más un registro de defectos.

## Decisión
*Decidido por el equipo el 10/10/2026:*
- **Pest**, que corre sobre PHPUnit y por eso cumple RNF07.
- La CI **bloquea el merge** si la cobertura baja del 70 %.
- **Pest Browser** (Playwright) para los 5 flujos críticos.
- **k6** para las pruebas de carga.
- **Pint + Larastan nivel 5** como análisis estático, que también bloquea.
- **SQLite en memoria** como base de datos de pruebas.
- ~~Defectos en GitHub Issues + Projects~~ → **reemplazado por [[ADR-025 OpenProject como gestor del trabajo]]**: los defectos van en OpenProject.

## Consecuencias
- Riesgo aceptado: las pruebas con SQLite pueden no detectar diferencias con el motor de producción. Ver la mitigación en [[Estrategia de pruebas]].
- Detalle en [[Estrategia de pruebas]], [[Pruebas de carga]] y [[Registro de defectos]] (OpenProject).
