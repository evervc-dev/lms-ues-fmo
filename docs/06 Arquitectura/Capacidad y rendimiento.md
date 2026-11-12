---
tipo: arquitectura
estado: borrador
rnf: ["[[RNF03]]", "[[RNF10]]"]
---

# Capacidad y rendimiento

| Objetivo | Diseño |
|---|---|
| 500 concurrentes, p95 ≤ 3 s ([[RNF03]]) | Caché Redis de catálogos, paneles y permisos; eager loading obligatorio (`Model::preventLazyLoading()` fuera de producción); índices por FK y filtros; trabajo pesado en colas |
| 100 exámenes simultáneos, carga ≤ 2 s ([[RNF10]]) | Preguntas sorteadas y guardadas al iniciar; autoguardado que solo hace un upsert de la respuesta; tiempo en el servidor; Reverb para el contador |
| Pico de QR (60 escaneos en 2 min por sección) | Validación en una transacción corta; restricción única en PostgreSQL; sin llamadas externas. Un token nuevo cada 10 s por sesión abierta: inserción barata en `qr_tokens`, purgada a los 7 días |
| Choques de horario | **Aula:** `EXCLUDE USING gist` con `int4range` de minutos por día y período. **Docente:** validación en el Service dentro de una transacción ([[Modelo entidad-relación]]) |

> [!info] Pruebas en PostgreSQL
> Desde [[ADR-027 Base de datos híbrida]], las suites que tocan la base de datos corren en PostgreSQL. Las restricciones `EXCLUDE`, los índices parciales, los triggers y la RLS se prueban de verdad.

## Dimensionamiento inicial (orientativo)
Unos 250 usuarios activos de ISI: **1 servidor de 4 vCPU y 8 GB** basta para web, Horizon, Reverb, PostgreSQL y Redis. Se confirma con k6 en staging antes del primer release. Si el piloto se extiende a la facultad (11 departamentos), se separa PostgreSQL y se agregan instancias `web`.
