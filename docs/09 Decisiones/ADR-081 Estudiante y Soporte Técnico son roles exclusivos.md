---
tipo: adr
id: ADR-081
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF03]]", "[[RF04]]", "[[Matriz RBAC]]", "[[ADR-053 Roles incompatibles con el de Estudiante]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]"]
fuentes: ["Decisión del usuario del 21/11/2026"]
---

# ADR-081 · Estudiante y Soporte Técnico son roles exclusivos

> [!info] Pendiente de aceptar por el equipo. **Amplía [[ADR-053 Roles incompatibles con el de Estudiante]]** y reemplaza su punto 2.

## Contexto
- [[ADR-053 Roles incompatibles con el de Estudiante]] solo prohibía Estudiante junto con Docente o con Soporte Técnico. Una cuenta podía tener Estudiante y Coordinador, Jefe o Administrador, y Soporte Técnico podía sumar cualquier rol del personal.
- El usuario pidió que un estudiante y una cuenta de Soporte Técnico no puedan tener más de un rol.

## Opciones
- **A. Estudiante y Soporte Técnico exclusivos:** la cuenta que tiene uno de los dos no tiene ningún otro rol.
- **B. Solo Estudiante exclusivo.**
- **C. Dejarlo como en ADR-053.**

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- Una cuenta con el rol **Estudiante** o **Soporte Técnico** no puede tener ningún otro rol, tampoco el otro de los dos.
- **Docente, Coordinador, Jefe de Departamento y Administrador** siguen combinándose entre sí.
- Se valida en `JerarquiaService::exigirRolesCompatibles()` (`ROLES_EXCLUSIVOS`), el camino común de crear cuentas, cambiar roles y la carga masiva. Vale igual para el Administrador, Soporte y el Jefe.
- El mensaje nombra el choque: «Una cuenta con el rol Estudiante no puede tener además el rol Coordinador».

## Consecuencias
- Como en ADR-053, la regla se aplica al asignar: una cuenta que ya tenga la combinación queda como está hasta que alguien le cambie los roles, y entonces debe quedar válida. En la base de desarrollo no había ninguna al decidirlo.
- La pantalla de roles lo explica junto a las casillas.
- Las pruebas que usaban una cuenta de Soporte con Docente pasan a una de Administrador con Docente.
