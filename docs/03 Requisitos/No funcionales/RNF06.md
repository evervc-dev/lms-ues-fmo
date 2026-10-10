---
tipo: rnf
id: RNF06
nombre: Escalabilidad
aliases: ["Escalabilidad"]
categoria: Arquitectura
prioridad: Media-Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.18
---

# RNF06 · Escalabilidad

## Descripción
La arquitectura permite escalar horizontalmente. Soporta el crecimiento de usuarios, asignaturas y períodos sin rediseño. Es modular, para agregar módulos con facilidad. La base de datos está normalizada.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Horizontal | 2 o más instancias de la app detrás de un balanceador sin cambios de código | Despliegue de prueba |

## Implicaciones técnicas
- App sin estado: sesiones, caché y colas en Redis; archivos en almacenamiento S3 o MinIO.
- Módulos como carpetas de dominio dentro del monolito.

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
