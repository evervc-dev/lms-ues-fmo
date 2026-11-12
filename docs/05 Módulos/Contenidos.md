---
tipo: modulo
nombre: Contenidos
estado: borrador
---

# Módulo · Contenidos

> El aula virtual: unidades y recursos que el docente publica y el estudiante consume.

## Qué hace
- **Unidades colapsables** (acordeón) por semana, cómputo o tema.
- Recursos: **archivos con drag and drop** (descarga o visor embebido), **carpetas descargables en .zip**, **editor HTML**, enlaces y **video incrustado**.
- **Papelera** con 30 días para restaurar.
- **Seguimiento de uso**: aperturas, tiempo y descargas, con mapa de calor para el docente.
- **Reutilización** de unidades, materiales y bancos entre períodos.
- Búsqueda y filtros por asignatura, unidad o tipo.

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
    sort:
      - property: file.name
        direction: ASC
```

## Roles
[[Docente]] · [[Estudiante]] · [[Coordinador]]

## Tablas
[[Modelo entidad-relación#Contenidos|Diagrama ER]]: `units`, `resources`, `resource_files`, `resource_access_logs`, `resource_progress`, `content_copies`

## Flujos
—

## Decisiones
—

## Notas técnicas
- Almacenamiento de objetos (S3/MinIO) con URL firmada; nunca en el disco del contenedor (lo advirtió E7).
- Sanitización del HTML en el servidor.
- Registro de accesos en cola.
