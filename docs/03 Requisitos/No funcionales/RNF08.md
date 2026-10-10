---
tipo: rnf
id: RNF08
nombre: Integridad de datos
aliases: ["Integridad de datos"]
categoria: Datos
prioridad: Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.19
---

# RNF08 · Integridad de datos

## Descripción
Consistencia mediante validaciones en backend y frontend, transacciones en las operaciones críticas, llaves foráneas y restricciones de integridad referencial. Los cambios críticos quedan en la auditoría.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Restricciones | Toda relación tiene una FK | Revisión de migraciones |
| Transacciones | Matrícula, calificación final y asistencia dentro de una transacción | Revisión de código |

## Implicaciones técnicas
- Validación de formato **dentro de la clase del componente Livewire** (`#[Validate]` / `$this->validate()`) y reglas de negocio en el Service, antes de persistir. No hay Form Requests: no hay controladores de API.
- Restricciones únicas: correo, código de asignatura, un registro de asistencia por estudiante y sesión.

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
