---
tipo: rnf
id: RNF04
nombre: Respaldo y recuperación de datos
aliases: ["Respaldo y recuperación de datos"]
categoria: Continuidad
prioridad: Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.17
---

# RNF04 · Respaldo y recuperación de datos

## Descripción
Respaldos automáticos diarios de la base de datos con una retención mínima de 30 días. Un procedimiento documentado de recuperación ante desastres con RTO menor a 4 horas y RPO menor a 24 horas, de forma que una caída no implique pérdida de evaluaciones, asistencia o contenido.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Frecuencia | 1 respaldo diario como mínimo | Registro del job |
| Retención | ≥ 30 días | Inventario del almacenamiento |
| RTO | < 4 h | Simulacro de restauración trimestral |
| RPO | < 24 h | Fecha del último respaldo válido |

## Implicaciones técnicas
- El respaldo incluye los archivos subidos, no solo la base de datos. E7 reportó pérdida de archivos al reiniciar el servidor.
- Copia fuera del servidor principal.
- El autoguardado de exámenes ([[RNF10]]) reduce la pérdida durante una caída.

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
