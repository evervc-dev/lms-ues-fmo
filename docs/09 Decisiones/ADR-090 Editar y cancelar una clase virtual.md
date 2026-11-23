---
tipo: adr
id: ADR-090
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF21]]", "[[CP-RF21]]"]
fuentes: ["Reporte del rol Docente del 22/11/2026 (staging): «al programar una clase no deja editar los campos de la clase programada»"]
---

# ADR-090 · Editar y cancelar una clase virtual

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- RF21 solo pedía programar la clase, subir el reporte y asociar la grabación. Un error en el tema, la hora o el enlace de Meet no tenía arreglo, y una clase que no se iba a dar quedaba en la lista con su enlace.
- La fecha y la duración programadas son la base del 70 % del reporte de Meet ([[ADR-003 Asistencia virtual por reporte de Meet]]): cambiarlas después de la clase alteraría quién quedó Presente.

## Opciones
- **A. Editar según el estado y cancelar solo antes de empezar.**
- **B. Editar todo siempre**, también después del reporte.
- **C. Sin edición**: borrar y volver a programar.

## Decisión
**A.** *Decidida por el usuario el 22/11/2026.*

| Estado | Se puede cambiar |
|---|---|
| Programada | Tema, fecha y hora y duración, con las reglas de programar |
| En curso | Tema y duración, solo para alargarla |
| Finalizada, o con reporte o sesión de asistencia | Solo el tema |

- **Cancelar** solo una clase programada sin reporte: se borra con auditoría (`video_session.cancelled`). Si tenía evento en Google Calendar, se borra en la cola `google` (`CancelarEventoDeClase`) y Google avisa a los invitados.
- Si la clase tiene evento y cambió el tema, el inicio o la duración, se actualiza en la cola `google` (`ActualizarEventoDeClase`, `events.patch` con `sendUpdates=all`). El enlace de Meet del evento no cambia.
- **El enlace de Meet no se escribe a mano** (el campo «Enlace de Meet (opcional)» confundía; pedido del usuario del 22/11/2026): lo crea el evento de Google Calendar. Con la integración activa, programar exige que el docente tenga su Calendar conectado; si no, el modal lo lleva a conectarlo y el Service rechaza la clase. Si la integración no está activa en el servidor, la clase se programa sin enlace y se avisa.
- Se aplica en `ReporteMeetService::editar()` y `cancelar()`; permiso `videoconferencias.gestionar` con alcance S.

## Consecuencias
- Nuevo método `actualizarClase` en `CalendarGateway`.
- Casos CP-RF21-10 y CP-RF21-11.
