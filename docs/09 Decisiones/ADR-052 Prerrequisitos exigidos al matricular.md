---
tipo: adr
id: ADR-052
estado: pendiente
fecha: 2026-10-27
afecta: ["[[RF09]]", "[[RF29]]", "[[RF07]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-032 Ajustes por el documento de roles]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]"]
fuentes: ["Informe de QA del rol Administrador (27/10/2026), hallazgo H-09", "Decisión del usuario (27/10/2026)"]
---

# ADR-052 · Prerrequisitos exigidos al matricular

> [!warning] Pendiente de aceptar
> **Supera a [[ADR-030 Matrícula ampliada]] y a [[ADR-032 Ajustes por el documento de roles]]** en un punto: donde decían que el sistema registra los prerrequisitos pero **no los exige**, ahora sí los exige. No la aplique como definitiva hasta que el equipo la acepte.

## Contexto
[[RF07]] permite registrar prerrequisitos por asignatura y el catálogo los muestra, pero la matrícula nunca los comprobó: [[ADR-030 Matrícula ampliada]] decidió validar el cupo y el choque de horario, y dejar los prerrequisitos fuera. La propia pantalla del catálogo lo declara: «los prerrequisitos se registran, pero no se exigen al matricular».

El **informe de QA del rol Administrador** (27/10/2026) lo registró como hallazgo **H-09**, de gravedad media, con el comentario «no tiene sentido»: el sistema pide un dato que después ignora. La recomendación 2 del informe pide «activar la verificación obligatoria de prerrequisitos».

## Decisión
*Decidida por el usuario el 27/10/2026.*

1. **La matrícula exige los prerrequisitos** de la asignatura, en el camino común de `MatriculaService`, así que vale para las cuatro vías: el estudiante con clave ([[RF29]]), el docente, el Coordinador y el Jefe en su ámbito, y Soporte en cualquier sección.
2. **Se cumple un prerrequisito** cuando el estudiante tiene en `enrollment_results` un resultado **aprobado** de esa asignatura en cualquier período cerrado. La nota mínima es la de `system_settings` (6.0).
3. **El mensaje nombra lo que falta**: «Le falta aprobar MAT115 · Matemática I». Si faltan varias, se listan.
4. **Sin histórico no hay bloqueo**: un estudiante sin ningún resultado registrado (por ejemplo, de nuevo ingreso) solo se bloquea en las asignaturas que **tienen** prerrequisitos declarados.
5. **Sin excepciones por rol:** ni Soporte ni el Administrador pueden saltarlo. Si hace falta una excepción, se resuelve como las prórrogas de [[ADR-051 Fecha límite de inscripción por departamento]]: con una decisión aparte y su registro.

## Consecuencias
- La matrícula masiva rechazará filas que antes aceptaba. El resumen de la importación debe nombrar el prerrequisito que falta, no un error genérico.
- Los datos de demostración (`DemoAcademicSeeder`) necesitan resultados previos coherentes, o la demo no podrá matricular en asignaturas con prerrequisito.
- **Riesgo:** el histórico académico real de la UES no está en el sistema, así que al arrancar en producción **ningún** estudiante tendría prerrequisitos aprobados. Antes de activar esto en producción hace falta cargar el histórico o un período de gracia. Queda fuera de esta ADR.
- Afecta a [[RF09]], [[RF29]] y sus casos de prueba.
