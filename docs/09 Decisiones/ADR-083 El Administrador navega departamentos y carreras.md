---
tipo: adr
id: ADR-083
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF07]]", "[[RF08]]", "[[Matriz RBAC]]", "[[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]"]
fuentes: ["Pedido del equipo de pruebas (21/11/2026): el Administrador debe ver departamentos, sus carreras y sus materias como los ve el Jefe, con alcance global"]
---

# ADR-083 · El Administrador navega departamentos, carreras y materias

> [!info] Pendiente de aceptar por el equipo. **Amplía [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]**: la Matriz decía que el Administrador no necesitaba `carreras.ver` porque ya tenía el catálogo.

## Contexto
- El Jefe ve las carreras de su departamento (`/carreras`) y el detalle de cada una: coordinador, docentes, materias del plan con sus grupos y horarios. El Coordinador ve la suya.
- El Administrador solo tenía el catálogo de asignaturas, sin una vista por departamento y carrera.

## Opciones
- **A. Página de departamentos y luego las carreras filtradas:** tres niveles, reutilizando las páginas del Jefe.
- **B. Un selector de departamento en `/carreras`:** más corto, sin el nivel de departamentos con sus datos.

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- El Administrador recibe `carreras.ver` con alcance **G**.
- `/departamentos` (solo alcance G) lista cada departamento con su jefe vigente y sus carreras, asignaturas activas y grupos teóricos del período en curso.
- Al elegir uno se va a `/carreras?departamento={id}`; el filtro solo se aplica con alcance G y no amplía el del Jefe ni el del Coordinador.
- Al elegir una carrera se va a `/carreras/{id}`, el detalle que ya usa el Jefe; la miga intermedia devuelve a las carreras del departamento.

## Consecuencias
- Migración que agrega `carreras.ver` al rol `administrador` en las bases desplegadas.
- El menú de Administración suma «Departamentos».
