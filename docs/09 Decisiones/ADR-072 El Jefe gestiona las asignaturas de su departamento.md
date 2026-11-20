---
tipo: adr
id: ADR-072
estado: pendiente
fecha: 2026-11-18
afecta: ["[[RF07]]", "[[Matriz RBAC]]", "[[Jefe de Departamento]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[ADR-034 Navegación y layouts]]"]
fuentes: ["Decisión del usuario del 18/11/2026"]
---

# ADR-072 · El Jefe gestiona las asignaturas de su departamento

> [!info] Pendiente de aceptar por el equipo. Amplía [[RF07]], que reservaba el catálogo al Administrador.

## Contexto
En staging se le dio al rol Jefe de Departamento el permiso `asignaturas.gestionar` desde **Roles y permisos**, pero el Catálogo no le apareció: la opción solo estaba en el grupo «Administración» del menú. Además, como el permiso solo existía con alcance G, el Service no filtraba por departamento: entrando por la URL, el Jefe habría podido tocar las asignaturas de todos los departamentos y la estructura institucional (departamentos y aulas).

## Opciones
- **A. Jefe con alcance D:** gestiona solo las asignaturas de su departamento; la estructura sigue siendo del Administrador.
- **B. Jefe con alcance G:** mantiene todo el catálogo, como el Administrador; choca con la jerarquía de [[ADR-037 Jerarquía de creación y asignación de roles]].
- **C. Nada en la Matriz** y que el Administrador lo active a mano: ningún Jefe lo trae sembrado y el alcance quedaría indefinido.

## Decisión
**A**, y por defecto en la [[Matriz RBAC]]. *Decidida por el usuario el 18/11/2026.*
- `asignaturas.gestionar`: Administrador G, **Jefe D**.
- Con D, el Jefe lista, crea, edita, desactiva y elimina solo asignaturas de los departamentos que dirige hoy, y no puede mover una a otro departamento. Los **prerrequisitos** se eligen de cualquier departamento (p. ej. MAT115, de DCNM). También edita la descripción que ve el estudiante al inscribirse.
- **Catálogo › Estructura** (departamentos, carreras, planes y aulas) exige alcance **G**: el Jefe recibe 403 y no ve el enlace.
- El menú muestra **Catálogo** en el grupo «Jefatura».

## Consecuencias
- [[RF07]] suma al Jefe en sus roles y reglas; casos CP-RF07-13 a CP-RF07-16.
- `config/vistas.php` admite el campo `alcance` para las vistas cuyo Service exige un alcance mínimo del permiso (aquí, Estructura con G); la prueba de vistas lo respeta.
- Un Jefe solo ve las asignaturas del departamento de su jefatura **vigente**; al relevarlo, deja de verlas.
