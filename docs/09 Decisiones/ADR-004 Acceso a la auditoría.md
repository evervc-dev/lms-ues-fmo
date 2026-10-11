---
tipo: adr
id: ADR-004
estado: reemplazada
fecha: 2026-10-10
afecta: ["[[RF05]]", "[[RNF02]]", "[[Jefe de Departamento]]", "[[Matriz RBAC]]"]
fuentes: [Requerimientos.pdf p.2, Requerimientos.pdf p.16, Roles y permisos.pdf p.5]
---

# ADR-004 · El Jefe de Departamento ve la auditoría igual que el Administrador

> [!warning] Reemplazada
> Reemplazada el 10/10/2026 por [[ADR-029 Auditoría departamental del Jefe]]. Se conserva como historial.

## Contexto
- **[[RF05]]:** la auditoría se muestra «únicamente al rol Administrador».
- **[[RNF02]]:** la consultan «Administrador, Jefe de Departamento».
- **Roles y permisos:** el Jefe accede a la auditoría «en el ámbito departamental».

## Decisión
El **Jefe de Departamento tiene el mismo acceso a la auditoría que el Administrador**: global, con filtros y exportación. *Decidido por el equipo el 10/10/2026.*

## Consecuencias
- Se amplía RF05. El permiso `auditoria.ver` se concede con alcance G a los dos roles.
- La auditoría sigue siendo inmutable para todos ([[RNF02]]).
- Esta decisión **solo** cubre la auditoría: el Jefe no recibe la gestión de usuarios ni de roles.
