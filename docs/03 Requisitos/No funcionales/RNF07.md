---
tipo: rnf
id: RNF07
nombre: Mantenibilidad
aliases: ["Mantenibilidad"]
categoria: Calidad
prioridad: Alta
adr: ["[[ADR-001 Stack Laravel y Livewire]]"]
estado: borrador
fuente: Requerimientos.pdf p.19
---

# RNF07 · Mantenibilidad

## Descripción
Código según los estándares PSR. Patrones Service Layer, Repository y Observer. Documentación técnica al día. Migraciones de Laravel para la base de datos. Pruebas automatizadas con PHPUnit y cobertura mínima de 70 %.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Estilo | PSR-12, 0 errores | Laravel Pint en CI |
| Cobertura | ≥ 70 % | PHPUnit o Pest con informe de cobertura en CI |
| Análisis estático | Larastan nivel 5 o superior (propuesto) | CI |

## Implicaciones técnicas
- Usar Repository solo donde aporte; Eloquent ya cumple ese papel en muchos casos.

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
