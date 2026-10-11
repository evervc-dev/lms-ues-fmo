---
tipo: adr
id: ADR-024
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Directrices de IA]]", "[[Bitácora]]", "[[Definition of Done]]"]
fuentes: []
---

# ADR-024 · IA con Laravel Boost en modo orquesta

## Decisión
*Decidido por el equipo el 10/10/2026:*
- Toda IA usa **Laravel Boost**: su MCP y las directrices generadas.
- Las directrices propias viven en `.ai/guidelines/` y Boost las publica para Claude Code, Codex (`AGENTS.md`), Cursor, GitHub Copilot y Antigravity.
- **Modo orquesta**, con 5 reglas:
  1. Preguntar antes de decidir.
  2. Planificar y esperar aprobación.
  3. Delegar en subagentes.
  4. Verificar contra el vault.
  5. Documentar el progreso.
- En Claude Code: la skill `orquesta-lms` y los subagentes `explorador-lms`, `implementador-lms`, `probador-lms` y `revisor-lms`.

## Consecuencias
- El vault debe estar en el repositorio, en `docs/`, para que la IA lo lea.
- Los estados de RF y CP y la [[Bitácora]] se actualizan en cada tarea.
- Verificado con Boost 2.10: las 5 herramientas leen `AGENTS.md`, Antigravity incluida, y cada una tiene su MCP. `CLAUDE.md` importa `AGENTS.md`.
