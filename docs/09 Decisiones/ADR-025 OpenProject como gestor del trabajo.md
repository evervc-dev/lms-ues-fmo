---
tipo: adr
id: ADR-025
estado: aceptada
fecha: 2026-10-10
afecta: ["[[00 Plan de desarrollo]]", "[[OpenProject]]", "[[Registro de defectos]]", "[[ADR-013 Pruebas con Pest]]", "[[Directrices de IA]]"]
fuentes: []
---

# ADR-025 · OpenProject gestiona todo el trabajo

## Contexto
El equipo tiene OpenProject (Community, con admin) en su servidor y todo debe quedar registrado ahí. Se evaluó sincronizarlo con GitHub Issues, pero mantener los dos «es más vuelta».

## Decisión
*Decidido por el equipo el 10/10/2026:*
- **OpenProject** registra tareas, hitos, defectos, horas y estado.
- **No se usan GitHub Issues ni GitHub Projects.**
- GitHub queda para el código, los PR y la CI, enlazados con `OP#<ID>` mediante la integración GitHub de OpenProject.
- Organización:
  - 5 equipos de 4 personas, cada uno dueño de módulos.
  - Flujo continuo **sin sprints**, con hitos H0 a H4 y entrega el 25/11/2026.

## Consecuencias
- Reemplaza la parte de defectos de [[ADR-013 Pruebas con Pest]]. Ver [[Registro de defectos]].
- La IA registra el progreso en OpenProject con `scripts/openproject.py` ([[Directrices de IA]]).
- La carga inicial se genera desde el vault: el equipo, el hito y las dependencias son propiedades de cada RF.
