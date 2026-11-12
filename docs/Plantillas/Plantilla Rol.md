---
tipo: rol
nombre:
slug:
estado: borrador
fuente: Roles y permisos.pdf p.
---

# Rol · Nombre

> **Enfoque:** 

## Funcionalidades

## Permisos (spatie)
| Permiso | Descripción | Alcance |
|---|---|---|

## Alcance de datos

## Requisitos donde participa
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del rol
    order: [file.name, nombre, prioridad, modulo]
```
