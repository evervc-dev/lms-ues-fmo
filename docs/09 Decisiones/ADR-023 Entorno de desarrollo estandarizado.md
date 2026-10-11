---
tipo: adr
id: ADR-023
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Setup del entorno]]", "[[Instalación local]]"]
fuentes: []
---

# ADR-023 · Entorno de desarrollo estandarizado

## Contexto
20 personas en Linux y Windows. Los entornos deben ser lo más parecidos posible. Reemplaza la idea anterior de hacerlo «todo en Docker Compose».

## Decisión
*Decidido por el equipo el 10/10/2026:*
- **Servicios en Docker**, idénticos para todos: PostgreSQL 17, Redis 7, Mailpit y MinIO (`docker-compose.dev.yml`).
- **PHP 8.4, Composer 2 y Bun nativos**, instalados con los instaladores oficiales (`php.new`, `bun.sh`).
- **Comandos únicos** con scripts de Composer: `setup`, `dev`, `dev:win`, `fresh`, `test` y `check`.
- **Windows nativo no usa Horizon**: `composer dev:win` usa `queue:listen` con las mismas colas. Horizon y Pail requieren `pcntl`.
- Cliente **predis** para Redis (sin extensión). Finales de línea **LF** (`.gitattributes`, `core.autocrlf=input`).
- `scripts/doctor.sh` y `doctor.ps1` verifican el entorno.

## Consecuencias
- Los jobs se prueban con Horizon solo en Linux o WSL; en Windows, con `queue:listen`. El comportamiento de las colas es el mismo.
- Bun reemplaza a npm en el desarrollo y en la CI (`bun install`, `bun run build`).
