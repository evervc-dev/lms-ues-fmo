---
tipo: rnf
id: RNF02
nombre: Auditoría y trazabilidad
aliases: ["Auditoría y trazabilidad"]
categoria: Seguridad
prioridad: Alta
adr: ["[[ADR-029 Auditoría departamental del Jefe]]", "[[ADR-027 Base de datos híbrida]]"]
estado: borrador
fuente: Requerimientos.pdf p.16
---

# RNF02 · Auditoría y trazabilidad

## Descripción
Todo cambio sobre notas, asistencias, matrícula, asignación docente, usuarios o configuración crítica queda registrado con el usuario, fecha, hora, acción, entidad y valores anterior y nuevo. También cada inicio de sesión (fecha, hora, dispositivo, IP y resultado). Los registros son inmutables y solo los consultan el [[Administrador]] y el [[Jefe de Departamento]], con filtros por usuario, fecha o tipo de acción, cumpliendo la retención institucional.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Cobertura | 100 % de las entidades críticas auditadas | Prueba por entidad |
| Inmutabilidad | Sin UPDATE ni DELETE sobre la tabla de auditoría | Permisos del usuario de base de datos y prueba |
| Consulta | Filtro de 1 mes en menos de 3 s | Prueba de carga |

## Implicaciones técnicas
- **Triggers de PostgreSQL** (`fn_audit_row`) con el actor fijado por `DbContext`; inmutabilidad con `fn_block_mutation` y particiones mensuales ([[ADR-027 Base de datos híbrida]]).
- RLS: el Jefe solo ve su departamento ([[ADR-029 Auditoría departamental del Jefe]]).
- Una tabla aparte para los inicios de sesión.
- Definir el plazo de retención con la UES.

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
