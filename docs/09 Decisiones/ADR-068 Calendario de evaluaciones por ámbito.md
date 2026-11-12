---
tipo: adr
id: ADR-068
estado: pendiente
fecha: 2026-11-01
afecta: ["[[RF06]]", "[[Integraciones con Google]]", "[[ADR-018 Calendar con OAuth del docente]]", "[[Modelo entidad-relación]]"]
fuentes: ["Decisión del usuario del 01/11/2026", "[[ADR-018 Calendar con OAuth del docente]] (pendiente menor)"]
---

# ADR-068 · Quién fija las fechas de evaluación y en qué calendario van

> [!info] Pendiente de aceptar por el equipo. **Implementada** el 01/11/2026 por decisión del usuario, en la rama `academica-rf06-calendario`, y **ampliada el mismo día** con el ámbito por departamento y carrera (pedido del 01/11, opción A de las dos que se plantearon).

## Contexto
[[RF06]] pide que, al sincronizar el calendario de evaluaciones, «los usuarios involucrados reciben el evento en Google Calendar». [[ADR-018 Calendar con OAuth del docente]] dejó abierto en qué calendario. El ER ya tenía `google_sync_refs` con UNIQUE `(entity_type, entity_id, provider)`: **un** evento de Google por fecha del período.

## Opciones
- **A. En el calendario del Administrador que sincroniza**, con su OAuth (el mismo mecanismo de ADR-018), invitando a los involucrados.
- **B. Un enlace `.ics` de suscripción** del período, que cada usuario agrega a su Google Calendar.
- **C. En el calendario de cada docente**, con sus estudiantes. Contradice el UNIQUE de `google_sync_refs`.
- **D. Fuera del 1.0.**

## Decisión
**A**, con el ámbito que pidió el usuario el 01/11: **cada quien fija las fechas de su ámbito y las sincroniza en su propio calendario.**

| Quién | Permiso y alcance | Qué fechas fija | A quién invita |
|---|---|---|---|
| Administrador | `periodos.calendario.gestionar` G | Las de **todo el período** | Docentes y estudiantes del período |
| Jefe de Departamento | `periodos.calendario.gestionar` D | Las de **su departamento** | Los de las secciones cuyas asignaturas son de ese departamento |
| Coordinador | `periodos.calendario.gestionar` C | Las de **su carrera** | Los de las secciones cuyas asignaturas están en el plan de esa carrera |

- El ámbito vive en `period_calendar_events`: `department_id` y `career_id`, anulables y **excluyentes** (un `CHECK` impide que vengan las dos). Las dos nulas es una fecha global, que es lo que había antes de este cambio.
- Todos **ven** las fechas globales, porque les aplican; **solo las modifica quien las creó en su ámbito**, incluido el Administrador, que no toca las del departamento ni las de la carrera.
- El permiso es **nuevo y propio**: `periodos.gestionar` sigue siendo G y solo del Administrador, porque con él se cierra el período ([[RF34]]). El Jefe y el Coordinador no deben heredar eso por gestionar unas fechas.
- Las fechas se gestionan en su **propia página**, «Calendario de evaluaciones» (`/calendario-evaluaciones/{periodo?}`), no dentro del Detalle del período, al que el Jefe y el Coordinador no entran. Sin período en la ruta se usa el que está en curso.
- Cada quien sincroniza **solo lo suyo**: el evento queda en su calendario principal.
- Cada fecha es un evento en su calendario principal: con hora de fin, un evento con horario; sin ella, de todo el día.
- **Involucrados:** los docentes con una asignación vigente en las secciones del período y los estudiantes con matrícula activa, solo cuentas activas @ues.edu.sv.
- La lista de invitados va **oculta** (`guestsCanSeeOtherGuests = false`): son cientos de correos de estudiantes.
- Sincronizar de nuevo actualiza (`events.patch`); si el evento ya no existe en Google, se crea otro. Eliminar la fecha borra el evento (`events.delete`). Google avisa a los invitados (`sendUpdates=all`).
- Para conectar su calendario, los tres usan la misma página que el docente (Mi perfil › Google Calendar), que abre `videoconferencias.gestionar`, `periodos.gestionar` o `periodos.calendario.gestionar`.

## Consecuencias
- No hace falta otra autorización de TI: es la misma que la de [[RF21]].
- **Un docente de dos carreras puede recibir dos invitaciones** con fechas distintas, una de cada Coordinador. Es correcto —son fechas distintas— pero hay que decirlo en el manual, porque parece un duplicado.
- Si el Jefe o el Coordinador no conectan su Google Calendar, sus fechas quedan en la plataforma sin sincronizar; la pantalla se lo dice y enlaza a Mi perfil › Google Calendar. Nadie sincroniza por otro.
- El riesgo del límite de invitados **baja** para las fechas de departamento y de carrera, que invitan a menos gente, y sigue igual para las globales del Administrador.
- `google_sync_refs` no guarda quién creó el evento. Para borrarlo se prueba primero con quien elimina la fecha y después con los demás Administradores conectados.
- **Riesgo:** Google limita la cantidad de invitados por evento. En un período con muchos estudiantes, el evento puede fallar; queda como «Falló» con el motivo, y habría que pasar a la opción B.
- Cada sincronización envía correo a todos los invitados.
