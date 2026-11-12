---
tipo: prueba
rf: "[[RF06]]"
nombre: Gestión de períodos académicos
prioridad: Alta
nivel: feature
casos: 9
estado: automatizado
---

# CP-RF06 · Gestión de períodos académicos

Casos derivados de los criterios de aceptación de [[RF06]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF06-01 | **Dado** un período activo, **cuando** se intenta activar otro, **entonces** se rechaza: solo puede haber un período activo. | automatizado |
| CP-RF06-02 | **Dado** un período, **cuando** se cierra o se reabre, **entonces** solo lo puede hacer un rol con permiso y el cambio queda en la auditoría. | automatizado |
| CP-RF06-03 | **Dado** un departamento que no ha autorizado su cierre, **cuando** el Administrador intenta cerrar el período, **entonces** se rechaza ([[RF34]]). | automatizado |
| CP-RF06-04 | **Dado** el calendario de evaluaciones, **cuando** se sincroniza, **entonces** los usuarios involucrados reciben el evento en Google Calendar. *Cada ámbito en el calendario de quien lo gestiona —Administrador, Jefe y Coordinador— e invitando solo a los suyos, con Google simulado (`CalendarioPeriodoTest`) ([[ADR-068 Calendario de evaluaciones por ámbito]]).* | automatizado |
| CP-RF06-05 | **Dado** un período cerrado, **entonces** se puede consultar y exportar a Excel o PDF. | automatizado |
| CP-RF06-06 | **Dado** un período, **cuando** se exporta, **entonces** sale en Excel, PDF y CSV con una fila por departamento: sus secciones, su matrícula y su autorización de cierre ([[ADR-049 Exportación a Excel y PDF]]). | pasa |
| CP-RF06-07 | **Dado** un período, **cuando** se crea otro cuyas fechas se cruzan con las suyas en cualquier estado, **entonces** se rechaza nombrando el período con el que choca. | automatizado |
| CP-RF06-08 | **Dado** un período planificado, **cuando** el Administrador abre la lista, **entonces** ve una acción de edición visible, sin tener que descubrir que el nombre es un enlace. | automatizado |
| CP-RF06-09 | **Dado** el calendario de evaluaciones, **cuando** lo abre el Jefe o el Coordinador, **entonces** gestiona y sincroniza las fechas de su departamento o de su carrera, ve las globales sin poder modificarlas y no toca las de otro ámbito ([[ADR-068 Calendario de evaluaciones por ámbito]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF06-01 …')`.
