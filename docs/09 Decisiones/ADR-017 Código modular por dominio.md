---
tipo: adr
id: ADR-017
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RNF06]]", "[[RNF07]]", "[[Estructura del código]]"]
fuentes: [Requerimientos.pdf p.18-19]
---

# ADR-017 · Código organizado por módulo de dominio

## Decisión
*Decidido por el equipo el 10/10/2026:* `app/Modules/<Modulo>/`, uno por cada módulo del vault, con sus Models, Services, Livewire, Jobs y rutas.

## Consecuencias
- Cumple la «arquitectura modular» de [[RNF06]].
- Las dependencias entre módulos se vigilan con pruebas `arch()` de Pest.
- Detalle en [[Estructura del código]].
