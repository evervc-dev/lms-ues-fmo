---
tipo: rnf
id: RNF03
nombre: Disponibilidad y rendimiento
aliases: ["Disponibilidad y rendimiento"]
categoria: Rendimiento
prioridad: Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.17
---

# RNF03 · Disponibilidad y rendimiento

## Descripción
Disponibilidad de al menos 99 % en horario académico (6:00 a 22:00). Las operaciones comunes responden en 3 s como máximo. Soporta al menos 500 usuarios concurrentes sin degradación significativa. Usa caché Redis para las consultas frecuentes.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Disponibilidad | ≥ 99 % de 6:00 a 22:00 (unas 4.8 h de caída al mes como máximo) | Monitor externo de uptime |
| Respuesta | p95 ≤ 3 s en operaciones comunes | APM o logs de tiempos |
| Concurrencia | 500 usuarios sin errores y con p95 ≤ 3 s | Prueba de carga con k6 |

## Implicaciones técnicas
- Redis para caché, sesiones y colas.
- Tareas pesadas (reportes, importaciones, correos) en cola.
- Mantenimientos y despliegues fuera del horario académico, o sin corte.

## Requisitos funcionales relacionados
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF relacionados
    order: [file.name, nombre, prioridad, modulo]
```
