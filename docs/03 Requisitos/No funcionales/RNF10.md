---
tipo: rnf
id: RNF10
nombre: Rendimiento en evaluaciones en línea
aliases: ["Rendimiento en evaluaciones en línea"]
categoria: Rendimiento
prioridad: Media
adr: []
estado: borrador
fuente: Requerimientos.pdf p.20
---

# RNF10 · Rendimiento en evaluaciones en línea

## Descripción
Al menos 100 estudiantes pueden hacer una evaluación a la vez sin degradación. Cada pregunta carga en 2 s como máximo. Las respuestas se guardan automáticamente cada 30 s. Las reconexiones no pierden respuestas.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Concurrencia | 100 intentos simultáneos con p95 ≤ 2 s | Prueba de carga con k6 |
| Autoguardado | Cada 30 s y al cambiar de pregunta | Prueba e2e |
| Reconexión | 0 respuestas perdidas | Prueba e2e cortando la red |

## Implicaciones técnicas
- Respuestas guardadas en el servidor por intento; el tiempo límite se controla en el servidor, no solo en el navegador.

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
