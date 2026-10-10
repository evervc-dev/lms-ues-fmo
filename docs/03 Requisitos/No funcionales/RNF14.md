---
tipo: rnf
id: RNF14
nombre: Pruebas y aseguramiento de calidad
aliases: ["Pruebas y aseguramiento de calidad"]
categoria: Calidad
prioridad: Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.22
---

# RNF14 · Pruebas y aseguramiento de calidad

## Descripción
Pruebas unitarias para la lógica de negocio crítica, pruebas de integración y de regresión antes de cada release. Los casos de prueba y sus resultados se documentan. Se lleva un registro de defectos. Hay code review obligatorio antes de hacer merge a la rama principal.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Regresión | La suite completa pasa antes de cada release | CI |
| Code review | 100 % de los merges con aprobación | Protección de rama |

## Implicaciones técnicas
- Los casos de prueba salen de los criterios de aceptación de cada RF (fase 10).

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
