---
tipo: rnf
id: RNFxx
nombre:
prioridad: Alta
categoria:
estado: borrador
fuente: Requerimientos.pdf p.
---

# RNFxx · Nombre

## Descripción

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|

## Implicaciones técnicas

## Requisitos funcionales afectados
```base
filters:
  and:
    - file.hasLink(this.file)
    - tipo == "rf"
views:
  - type: table
    name: RF afectados
    order: [file.name, nombre, prioridad, modulo]
```
