---
tipo: adr
id: ADR-047
estado: pendiente
fecha: 2026-10-24
afecta: ["[[RF04]]", "[[RF07]]", "[[RF08]]", "[[RF29]]", "[[Matriz RBAC]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-032 Ajustes por el documento de roles]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[Flujo Carga masiva de usuarios]]"]
fuentes: ["Decisión del usuario (24/10/2026)"]
---

# ADR-047 · Carreras visibles para el Jefe y el Coordinador, y carga masiva sin el Coordinador

> [!warning] Pendiente de aceptar
> Modifica [[ADR-030 Matrícula ampliada]], que está **aceptada**. No la aplique como definitiva hasta que el equipo la acepte.

## Contexto
El Jefe de Departamento solo veía asignaturas y grupos («secciones»), nunca las **carreras** de su departamento, que es la unidad con la que trabaja. El Coordinador tampoco tenía una vista de conjunto de su carrera. Por otra parte, [[ADR-030 Matrícula ampliada]] dio al Coordinador la carga masiva de estudiantes de su carrera, y el equipo decidió que esa operación quede en manos de quien administra cuentas.

## Decisión

### 1. Permiso nuevo `carreras.ver`
| Permiso | Adm | Jefe | Coord | Doc | Est | Sop |
|---|---|---|---|---|---|---|
| `carreras.ver` | | **D** | **C** | | | |

- El **Jefe** ve las carreras de su departamento y entra al detalle de cada una: Coordinador vigente, docentes, y las asignaturas con sus grupos y los horarios de cada grupo.
- El **Coordinador** ve lo mismo, limitado a su carrera.
- El **Administrador** no lo necesita: ya tiene el catálogo con `asignaturas.gestionar`.
- Es un permiso **de solo lectura**: no altera quién crea grupos, asigna docentes ni cambia cupos ([[ADR-037 Jerarquía de creación y asignación de roles]]).

### 2. La carga masiva deja de ser del Coordinador
- `usuarios.importar` queda en **Administrador (G)** y **Soporte Técnico (G\*)**.
- Sustituye a «La hace el Coordinador de Carrera, para su carrera» de [[ADR-030 Matrícula ampliada]] §Carga masiva.
- El Coordinador conserva lo demás de su rol: claves de matrícula, matrícula de su carrera, grupos y validación de actas.

## Consecuencias
- Hay que actualizar [[Matriz RBAC]] (hecho), el seeder de permisos (hecho) y [[Flujo Carga masiva de usuarios]], que todavía nombra al Coordinador.
- Un Coordinador que hoy tenga el permiso lo pierde al volver a sembrar los roles; la pantalla `/usuarios/importar` le responderá 403.
- [[RF29]] y [[RF04]] deben revisar sus criterios y casos de prueba si mencionan al Coordinador importando.
- `carreras.ver` no aparece en ningún RF: conviene reflejarlo en [[RF07]] o [[RF08]], o abrir un RF propio.

## Alternativas descartadas
- **Reutilizar `usuarios.ver` (D) para el Jefe:** mezcla la gestión de cuentas con la vista académica y le daría al Coordinador un alcance que no tiene.
- **Dejar la carga masiva al Coordinador con doble confirmación:** no resuelve la objeción de fondo, que es quién debe administrar cuentas.
