---
tipo: arquitectura
estado: revisado
adr: ["[[ADR-019 Entornos]]", "[[ADR-014 Ramas y CI en GitHub Actions]]"]
---

# Entornos

Dos entornos: **producción** y **staging** ([[ADR-019 Entornos]]). No hay previews por PR.

| Entorno | Rama | Datos | Uso |
|---|---|---|---|
| **Local** | `<modulo>-rfxx-<corto>` | Seeders | Desarrollo ([[Instalación local]]) |
| **Verificación local** | PR a `test` o a `main` | SQLite en memoria; PostgreSQL 17 antes de `main` | Pruebas antes del PR ([[ADR-061 Verificación local sin GitHub Actions]]) |
| **Staging** | `test` | **Falsos**, sembrados (nunca de producción) | QA, demo, **k6** ([[Pruebas de carga]]) |
| **Producción** | `main` | Reales | Usuarios |

## Reglas
- El despliegue a staging es automático al unir a `test`; el de producción, al unir a `main`.
- Staging tiene su propio cliente OAuth (o URI de redirección) y su propia base de datos.
- El inicio de sesión de desarrollo existe en local y en staging; **nunca en producción** (`APP_ENV=production` lo desactiva y una prueba arch lo vigila).
- Las migraciones de producción deben poder correr sin corte (aditivas primero, destructivas en el release siguiente).
