---
tipo: rnf
id: RNF13
nombre: Documentación técnica y de usuario
aliases: ["Documentación técnica y de usuario"]
categoria: Calidad
prioridad: Media-Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.21
---

# RNF13 · Documentación técnica y de usuario

## Descripción
Documentación técnica al día (arquitectura y base de datos), manual de usuario por rol, proceso de instalación y despliegue, y flujos de trabajo principales, accesible para el equipo de desarrollo y soporte.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Cobertura | Las secciones 06–11 del vault completas | [[00 Plan de documentación]] |

## Implicaciones técnicas
- Se cumple con este vault de Obsidian.

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
