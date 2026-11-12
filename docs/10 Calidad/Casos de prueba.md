---
tipo: calidad
estado: borrador
---

# Casos de prueba

166 casos derivados de los criterios de aceptación de los 33 RF vigentes. RF02 está descartado y no tiene casos. Cada nota `CP-RFxx` lista sus casos con un ID estable, que se usa también en el nombre de la prueba Pest.

```base
filters:
  and:
    - tipo == "prueba"
views:
  - type: table
    name: Casos por RF
    order: [file.name, nombre, prioridad, nivel, casos, estado]
    sort:
      - property: file.name
        direction: ASC
  - type: table
    name: Por prioridad
    groupBy:
      property: prioridad
      direction: ASC
    order: [file.name, nombre, casos, estado]
```

**Estados:** `pendiente` → `automatizado` → `pasa`. Si una prueba falla en `test`, se registra un **Bug en OpenProject** ([[Registro de defectos]]).
