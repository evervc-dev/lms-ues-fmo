---
tipo: prueba
rf: "[[RF04]]"
nombre: Gestión de usuarios
prioridad: Alta
nivel: feature
casos: 23
estado: automatizado
---

# CP-RF04 · Gestión de usuarios

Casos derivados de los criterios de aceptación de [[RF04]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF04-01 | **Dado** el Coordinador, **cuando** intenta importar un Excel, **entonces** se le rechaza: la carga masiva la hacen el Administrador y Soporte Técnico, de cualquier carrera. | pasa |
| CP-RF04-02 | **Dado** un archivo Excel (o CSV) válido, **cuando** se importa, **entonces** se crean los usuarios y se muestra un resumen: creados, omitidos por duplicado y rechazados con el motivo por fila. | pasa |
| CP-RF04-03 | **Dado** un correo que no termina en `@ues.edu.sv`, **entonces** la fila o el formulario se rechaza. | pasa |
| CP-RF04-04 | **Dado** un correo que ya existe, **entonces** no se crea un duplicado. | pasa |
| CP-RF04-05 | **Dado** un usuario desactivado, **entonces** no puede entrar, se cierran sus sesiones abiertas y su historial académico sigue consultable; al reactivarlo recupera el acceso. | pasa |
| CP-RF04-06 | **Dado** cualquier alta, baja o cambio de rol, **entonces** queda en la auditoría ([[RF05]]). | pasa |
| CP-RF04-07 | **Dado** el Administrador, **cuando** edita los nombres y apellidos de un usuario, **entonces** se guardan y el cambio queda en la auditoría; el correo no se edita. | pasa |
| CP-RF04-08 | **Dado** un usuario, **cuando** el Administrador le asigna varios roles, **entonces** los conserva todos; se rechaza dejarlo sin roles, que el actor modifique sus propios roles (incluido concederse Administrador), quitar el rol al último Administrador activo o editar los roles de la cuenta de emergencia. | pasa |
| CP-RF04-09 | **Dado** el rol Coordinador, **cuando** se concede, **entonces** exige una carrera; si la carrera ya tenía coordinador vigente, a ese se le cierra con `valid_to` = hoy y el nuevo empieza hoy; el relevado pierde el rol Coordinador y conserva sus otros roles (no se releva al propio actor ni a la cuenta de emergencia); al quitar el rol se cierra su coordinación. | pasa |
| CP-RF04-10 | **Dado** el rol Jefe de Departamento, **cuando** se concede, **entonces** exige un departamento, con el mismo relevo, histórico y pérdida de rol del relevado que el Coordinador; al quitar el rol se cierra su jefatura. | pasa |
| CP-RF04-11 | **Dado** el rol Docente, **cuando** se concede, **entonces** exige un departamento (perfil docente) y desde ese momento el docente aparece en «Asignar docente» de la carga académica ([[RF08]]); sin el rol o con la cuenta inactiva no aparece ni se puede asignar, y no se le quita el rol si tiene secciones asignadas en un período no cerrado. | pasa |
| CP-RF04-12 | **Dado** el rol Estudiante, **cuando** se concede, **entonces** exige carrera, un plan de esa carrera, ciclo de 1 a 12 y carné único; el carné no se puede modificar después; no se le quita el rol si tiene matrículas activas; al cambiar de carrera, la anterior queda `changed` y se crea la nueva activa. | pasa |
| CP-RF04-13 | **Dado** un Coordinador, o Soporte sobre una cuenta con roles que no asigna (p. ej. un docente sin `usuarios.crear-docente`), **cuando** intenta editar datos, roles o asignaciones, **entonces** recibe 403; Soporte sí puede consultar la ficha ([[ADR-037 Jerarquía de creación y asignación de roles]]). | pasa |
| CP-RF04-15 | **Dado** Soporte, **cuando** crea un estudiante con su carrera, **entonces** se crea; no puede crear Administradores, Jefes, Coordinadores, Soporte ni (sin permiso) Docentes, no edita otras cuentas y no desactiva. | pasa |
| CP-RF04-16 | **Dado** que el Administrador concede `usuarios.crear-docente` a un Soporte, **entonces** ese Soporte crea docentes con su departamento; al retirarlo, ya no. Solo el Administrador lo concede, y solo a Soporte. | pasa |
| CP-RF04-18 | **Dado** una cuenta sin roles (p. ej. un autorregistro), **cuando** Soporte le corrige los datos y le da el rol Estudiante con su carrera, **entonces** se aplica; no puede darle Coordinador. La lista de Usuarios filtra «Sin rol» y el resumen cuenta esas cuentas ([[ADR-038 Autorregistro sin rol]]). | pasa |
| CP-RF04-17 | **Dado** un Jefe, **cuando** da Docente (perfil en su departamento) y Coordinador (carrera de su departamento) a un usuario existente, **entonces** se aplica; fuera de su departamento, al crear cuentas, editar datos, dar otros roles o desactivar recibe 403. Ve solo su personal (más la cuenta cuyo correo escribe completo) y no ve accesos ni dispositivo. | pasa |
| CP-RF04-14 | **Dado** PostgreSQL, **cuando** cambia un perfil docente o de estudiante, una carrera del estudiante, una coordinación o una jefatura, **entonces** queda en `audit_logs` ([[ADR-036 Auditoría de asignaciones académicas]]). | pasa |
| CP-RF04-19 | **Dado** la lista de Usuarios con búsqueda, rol, estado y teléfono puestos, **cuando** se pulsa «Limpiar filtros», **entonces** se quitan los cuatro de una vez y el botón desaparece. | pasa |
| CP-RF04-20 | **Dado** la pantalla de importación, **cuando** se descarga la plantilla, **entonces** trae los encabezados en orden y una fila de ejemplo con una carrera y un plan del alcance de quien la descarga, que la vista previa acepta sin errores. | pasa |
| CP-RF04-21 | **Dado** un libro de Excel (`.xlsx`) con las columnas de la plantilla, **cuando** se sube sin convertirlo a CSV, **entonces** la vista previa lo lee y la importación crea las cuentas ([[ADR-048 Lector propio de hojas para el reporte de Meet]]). | pasa |
| CP-RF04-22 | **Dado** una cuenta con el rol Estudiante, **cuando** se le intenta asignar Docente o Soporte Técnico, **entonces** se rechaza nombrando el choque; los demás roles sí coexisten y las cuentas que ya tienen la combinación no se tocan ([[ADR-053 Roles incompatibles con el de Estudiante]]). | automatizado |
| CP-RF04-23 | **Dado** un nombre o apellido con una palabra de más de 25 letras o más de 60 caracteres, **cuando** se crea, se edita o se importa la cuenta, **entonces** se rechaza diciendo qué corregir; los demás se guardan como nombre propio ([[ADR-060 Formato de los nombres de usuario]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF04-01 …')`.
>
> **27/10/2026:** la guardia «no se desactiva al último Administrador activo» se verifica en `tests/Feature/Identidad/UsuariosTest.php` bajo CP-RF04-05, que es el caso más cercano; antes llevaba por error el ID CP-RF03-03. **Pendiente de decidir:** si merece un caso propio, porque CP-RF04-05 habla de los efectos de la desactivación y CP-RF04-08 solo cubre la mitad de los roles.
>
> **13/10/2026:** los 14 casos están automatizados en `tests/Feature/Identidad/` y pasan en SQLite y en PostgreSQL 17. CP-RF04-06 y CP-RF04-14 solo corren en PostgreSQL. CP-RF04-05 prueba «no puede entrar» con el acceso de desarrollo, porque el inicio con Google ([[RF01]]) aún no existe.
