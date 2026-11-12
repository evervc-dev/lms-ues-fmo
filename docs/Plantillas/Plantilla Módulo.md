---
tipo: modulo
nombre:
estado: borrador
---

# Módulo · Nombre

## Objetivo

## Requisitos
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del módulo
    order: [file.name, nombre, prioridad, estado]
```

## Roles

## Entidades

## Flujos
