---
tipo: desarrollo
estado: revisado
---

# Archivos del repositorio

El repositorio **`~/Github/lms-ues`** (Laravel 12) ya contiene estos archivos en su raíz. El vault vive en **`docs/`**: Obsidian puede abrir esa carpeta como vault. Los archivos que empiezan con punto no se ven en Obsidian; ábralos con el editor.

| Archivo | Para qué |
|---|---|
| `docker-compose.dev.yml` | PostgreSQL 17, Redis 7, Mailpit y MinIO (con el bucket `lms-archivos`) |
| `.env.example` | Variables de desarrollo estándar: pgsql, predis, Reverb, Mailpit, MinIO, Google, `LMS_DEV_LOGIN` |
| `composer.json` → `scripts` | `setup`, `dev`, `dev:win`, `fresh`, `test` y `check` |
| `composer.json` → `config.platform.php = 8.4.0` | Las dependencias se resuelven para PHP 8.4 aunque la máquina tenga otra versión |
| `.editorconfig` · `.gitattributes` | Indentación y **LF** iguales en Linux y Windows |
| `.tool-versions` | PHP 8.4 y Bun 1.2, para quien use `mise` o `asdf` |
| `scripts/doctor.sh` · `scripts/doctor.ps1` | Verifican versiones, extensiones, servicios y `.env` |
| `scripts/openproject.py` | Carga el plan del vault en OpenProject y permite cambiar estados, comentar y crear bugs ([[OpenProject]]) |
| `.ai/guidelines/lms-ues.md` · `orquesta.md` | Directrices propias de IA: la fuente que Boost publica |
| `AGENTS.md` | **Generado por Boost.** Directrices para todas las herramientas de IA |
| `CLAUDE.md` | Importa `AGENTS.md` (`@AGENTS.md`) y menciona la skill y los subagentes |
| `config/boost.php` | Indica a Boost que Claude Code también use `AGENTS.md` |
| `boost.json` | Agentes de Boost: Claude Code, Codex, Copilot, Cursor y Antigravity |
| `.mcp.json` · `.codex/config.toml` · `.vscode/mcp.json` · `.cursor/mcp.json` · `.agents/mcp_config.json` | MCP de Boost para cada herramienta (generados) |
| `.claude/skills/orquesta-lms/` | Skill propia de Claude Code para implementar un RF |
| `.claude/agents/*-lms.md` | Subagentes: explorador, implementador, probador y revisor |
| `.claude/skills/*` · `.agents/skills/*` · `.cursor/skills/*` · `.github/skills/*` | Skills de Boost (Horizon, Livewire, Tailwind, pruebas, buenas prácticas…) |

## Paquetes instalados
| Paquete | Versión |
|---|---|
| laravel/framework | 12.x |
| livewire/livewire | **4.4** ([[ADR-015 Plataforma base]]) |
| laravel/horizon · laravel/reverb · predis/predis | 5.50 · 1.12 · 3.7 |
| pestphp/pest · pest-plugin-laravel | 3.8 |
| laravel/boost · laravel/pail | 2.10 · 1.2 |

Los demás paquetes de [[Stack y paquetes]] (spatie/permission, auditing, excel, dompdf, backup…) se instalan en la base técnica H0 ([[00 Plan de desarrollo]]).
