---
tipo: prueba
rf: "[[RF39]]"
nombre: Supervisión del trabajo docente
prioridad: Alta
nivel: feature
casos: 14
estado: pendiente
---

# CP-RF39 · Supervisión del trabajo docente

Casos derivados de los criterios de aceptación de [[RF39]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF39-01 | **Dado** el Jefe de Departamento, **cuando** entra a una sección de su departamento, **entonces** ve las pestañas Contenidos, Actividades, Notas, Revisiones, Asistencia, Progreso, Analítica y Foro en modo lectura. | pendiente |
| CP-RF39-02 | **Dado** el Jefe, **cuando** intenta entrar a una sección de otro departamento, **entonces** recibe 403. | pendiente |
| CP-RF39-03 | **Dado** el Coordinador, **cuando** entra a una sección de su carrera, **entonces** ve la pestaña Foro en solo lectura (antes no la tenía). | pendiente |
| CP-RF39-04 | **Dado** el Coordinador, **cuando** intenta entrar a una sección de otra carrera, **entonces** recibe 403. | pendiente |
| CP-RF39-05 | **Dado** el Coordinador con `asistencia.sesion.ver`, **cuando** abre el detalle de una sesión de asistencia de su carrera, **entonces** ve fecha, modalidad, estado, hora de apertura, hora de cierre, si fue dentro o fuera de horario, y la razón si aplica. | pendiente |
| CP-RF39-06 | **Dado** el Jefe con `asistencia.sesion.ver`, **cuando** abre el detalle de una sesión de asistencia de su departamento, **entonces** ve la misma información que CP-RF39-05. | pendiente |
| CP-RF39-07 | **Dado** el detalle de la sesión, **entonces** el Coordinador o el Jefe ve la lista de estudiantes con nombre, estado, hora de entrada, hora de salida y método de registro. | pendiente |
| CP-RF39-08 | **Dado** una sesión virtual, **entonces** el Coordinador o el Jefe ve el porcentaje de asistencia en Meet de cada estudiante. | pendiente |
| CP-RF39-09 | **Dado** el Coordinador o el Jefe en el detalle de la sesión, **entonces** no ve los botones de abrir QR, corregir, cerrar sesión ni marcar salida. | pendiente |
| CP-RF39-10 | **Dado** un usuario sin `asistencia.sesion.ver` ni `asistencia.sesion.abrir` (p. ej. un Estudiante), **cuando** intenta entrar al detalle, **entonces** recibe 403. | pendiente |
| CP-RF39-11 | **Dado** el Coordinador con `foros.ver`, **cuando** entra al Foro de una sección de su carrera, **entonces** ve los hilos y respuestas pero no los formularios de crear ni responder. | pendiente |
| CP-RF39-12 | **Dado** un usuario sin `foros.ver` ni `foros.participar`, **cuando** intenta entrar al foro, **entonces** recibe 403. | pendiente |
| CP-RF39-13 | **Dado** el Jefe con `calificaciones.ver` (D), **cuando** entra a las Notas de una sección de su departamento, **entonces** ve el libro de calificaciones en solo lectura. | pendiente |
| CP-RF39-14 | **Dado** una sesión abierta fuera del horario con una razón auditada, **cuando** el Coordinador o el Jefe ve el detalle, **entonces** se muestra la razón (solo PostgreSQL). | pendiente |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF39-01 …')`.
