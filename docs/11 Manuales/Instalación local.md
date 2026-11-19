---
tipo: tecnico
estado: revisado
rnf: ["[[RNF13]]"]
---

# Instalación local

La guía de instalación ahora es **[[Setup del entorno]]**. Cubre Linux, WSL y Windows con comandos estándar para Composer, Bun, las migraciones, los workers y Reverb ([[ADR-023 Entorno de desarrollo estandarizado]]).

Resumen:
```bash
docker compose -f docker-compose.dev.yml up -d
composer setup
composer dev        # Linux / WSL (Horizon)
composer dev:win    # Windows nativo (queue:listen)
```
