# LMS UES FMO

Plataforma LMS para Ingeniería de Sistemas Informáticos de la Universidad de El Salvador, Facultad Multidisciplinaria de Oriente.
Laravel 12 · Livewire 4 · PostgreSQL 17 · Redis + Horizon · Reverb.

## Documentación
Toda la documentación (requisitos, decisiones, arquitectura, datos, flujos, calidad, manuales y plan) está en **[`docs/`](docs/00%20Inicio.md)**, un vault de Obsidian: abra la carpeta `docs/` como vault.

- Inicio: `docs/00 Inicio.md`
- Plan de desarrollo: `docs/00 Plan de desarrollo.md`
- Instalación: `docs/12 Desarrollo/Setup del entorno.md`
- IA (modo orquesta + Laravel Boost): `docs/12 Desarrollo/Directrices de IA.md`

## Arranque rápido
```bash
docker compose -f docker-compose.dev.yml up -d
./scripts/doctor.sh          # Windows: .\scripts\doctor.ps1
composer setup
composer dev                 # Windows nativo: composer dev:win
```

## Trabajo
Las tareas, defectos e hitos se gestionan en **OpenProject** (`OP#<ID>` en commits y PR). Ramas: `<modulo>-rfxx-<corto>` (p. ej. `asistencia-rf20-qr`) desde `test` → PR a `test` → PR a `main`.
