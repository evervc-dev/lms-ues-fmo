---
tipo: adr
id: ADR-029
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF05]]", "[[RNF02]]", "[[Jefe de Departamento]]", "[[Matriz RBAC]]", "[[Seguridad]]"]
fuentes: ["Diseño de BD de @Alex, sección 17.1 #3", "Roles y permisos.pdf p.5"]
---

# ADR-029 · El Jefe ve la auditoría solo de su departamento

**Reemplaza a [[ADR-004 Acceso a la auditoría]]** (Jefe con acceso global).

## Decisión
*Decidido por el equipo el 10/10/2026:*
- **Administrador:** toda la auditoría.
- **Jefe de Departamento:** solo los eventos cuyo `department_id` coincide con su departamento vigente (`department_heads`).
- **Coordinador y demás roles:** ninguna. El Coordinador tiene su propia vista académica de solo lectura.

## Implementación
- Cada fila de `audit_logs` guarda `department_id` cuando se puede derivar: de la sección, la materia o la carrera afectada.
- **Dos barreras:** la consulta filtra en el Service con `Alcance`, y **RLS** en `audit_logs` como segunda barrera ([[ADR-027 Base de datos híbrida]]).

## Pendiente
Los eventos sin departamento derivable (usuarios o configuración global) solo los ve el Administrador.
