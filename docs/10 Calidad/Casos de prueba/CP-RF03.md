---
tipo: prueba
rf: "[[RF03]]"
nombre: Gestión de roles y permisos
prioridad: Alta
nivel: feature
casos: 7
estado: pasa
---

# CP-RF03 · Gestión de roles y permisos

Casos derivados de los criterios de aceptación de [[RF03]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF03-01 | **Dado** el Administrador, **cuando** asigna o revoca un permiso a un rol, **entonces** el cambio aplica en la siguiente petición de los usuarios de ese rol y queda en la auditoría ([[RF05]]). | pasa |
| CP-RF03-02 | **Dado** el docente responsable de una asignatura, **cuando** edita su contenido o configuración, **entonces** se permite; **dado** un docente que no está asignado a ella, **entonces** se rechaza con 403. | pasa |
| CP-RF03-03 | **Dado** el último Administrador activo, **cuando** alguien intenta quitarle `roles.gestionar` o el rol, **entonces** se rechaza. | pasa |
| CP-RF03-04 | **Dado** un rol sin el permiso de corrección, **cuando** intenta corregir una nota o una asistencia, **entonces** se rechaza. | pasa |
| CP-RF03-05 | **Dado** un rol autorizado, **cuando** corrige una nota o una asistencia, **entonces** la corrección queda en la auditoría con el valor anterior y el nuevo. | pasa |
| CP-RF03-06 | **Dado** la matriz de permisos, **cuando** se busca por el nombre de un permiso o de su grupo, **entonces** quedan solo los que coinciden, con aviso si no hay ninguno, y «Limpiar filtros» la devuelve completa. | pasa |
| CP-RF03-07 | **Dado** la pantalla de roles, **cuando** se intenta alternar un permiso que no existe, **entonces** se rechaza: los permisos los define el seeder con los nombres de la [[Matriz RBAC]] y no se crean desde la interfaz ([[ADR-032 Ajustes por el documento de roles]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF03-01 …')`.
>
> **27/10/2026:** CP-RF03-03 cubre sus dos mitades: «quitarle `roles.gestionar`» en `tests/Feature/Identidad/PermisosRolTest.php` (guardia de `RolService::alternar`) y «ni el rol» en `tests/Feature/Identidad/AsignacionesUsuarioTest.php`, etiquetada `CP-RF04-08 CP-RF03-03`. La prueba de «no desactivar al último Administrador» pasó a CP-RF04-05, que es el caso de desactivación.
>
> **27/10/2026:** los casos pasan a `pasa` con la corrida contra **PostgreSQL 17**: 16 pruebas en verde (`vendor/bin/pest --filter="CP-RF03"`), ninguna saltada. Hasta entonces parte de ellos solo existía como prueba escrita, porque se saltaban en SQLite y el repositorio no tenía CI que los repitiera.
