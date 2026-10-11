---
tipo: adr
id: ADR-005
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF03]]", "[[Coordinador]]", "[[Jefe de Departamento]]"]
fuentes: [Alcance - Aula virtual.pdf, Requerimientos.pdf p.1, Roles y permisos.pdf]
---

# ADR-005 · ¿Coordinador y Jefe son un rol o dos?

## Contexto
El **alcance** habla de un solo rol «Coordinador/Jefe con permisos de auditoría académica». **[[RF03]]** y **Roles y permisos** definen dos roles distintos. La jefatura dijo que la coordinación es «solo un apoyo administrativo al jefe».

## Opciones
- **A. Dos roles.** Es lo que dicen RF03 y la matriz. El Coordinador gestiona su carrera y el Jefe supervisa el departamento y la auditoría.
- **B. Un solo rol.** Más simple, pero mezcla la operación diaria con la supervisión.

## Recomendación
**A.** La fuente de verdad (RF03) ya define seis roles, y spatie permite que una persona tenga los dos si hace falta.

## Decisión
**A. Dos roles.** El Coordinador opera su carrera; el Jefe supervisa el departamento, ve la auditoría y los KPIs docentes ([[RF33]]). Una persona puede tener los dos roles. *Decidido por el equipo el 10/10/2026.*
