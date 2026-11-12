---
tipo: prueba
rf: "[[RF21]]"
nombre: Asistencia virtual y gestión de videoconferencias
prioridad: Media
nivel: browser + feature
casos: 8
estado: automatizado
---

# CP-RF21 · Asistencia virtual y gestión de videoconferencias

Casos derivados de los criterios de aceptación de [[RF21]]. Nivel: **browser + feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF21-01 | **Dado** el docente, **cuando** programa una clase virtual, **entonces** se crea el evento en Google Calendar con enlace de Meet y se invita a los matriculados. *Automatizado contra `CalendarFalso` y contra Google simulado (`GoogleCalendarTest`: conexión OAuth del docente solo @ues.edu.sv, evento con Meet, invitados del dominio, permiso revocado y borrado del evento al cancelar la clase). Probado contra Google real el 01/11/2026 con `php artisan google:calendar-prueba` y el cliente OAuth del equipo; en producción falta el cliente de TI ([[ADR-018 Calendar con OAuth del docente]]).* | automatizado |
| CP-RF21-02 | **Dado** una clase finalizada, **cuando** el docente sube el reporte de Meet (CSV o Excel exportado de Google Sheets), **entonces** el sistema lo lee, suma el tiempo de cada estudiante y muestra una vista previa antes de confirmar. | automatizado |
| CP-RF21-03 | **Dado** una fila del reporte con un correo `@ues.edu.sv` matriculado, **entonces** se asocia automáticamente al estudiante. | automatizado |
| CP-RF21-04 | **Dado** una fila sin correo o con un correo que no está en la sección (entró por el enlace sin su cuenta), **entonces** aparece en «Sin asociar» y el docente la asigna a mano o la descarta. | automatizado |
| CP-RF21-05 | **Dado** un estudiante matriculado que no aparece en el reporte, **entonces** queda Ausente. | automatizado |
| CP-RF21-06 | **Dado** un porcentaje igual o mayor a 70 % de la duración programada, **entonces** queda Presente; si es menor, Ausente. | automatizado |
| CP-RF21-07 | **Dado** un reporte que ya se subió para esa sesión, **cuando** se sube otro, **entonces** reemplaza al anterior y el cambio queda en la auditoría. | automatizado |
| CP-RF21-08 | **Dado** un reporte cuya fecha no coincide con la sesión, **entonces** se rechaza con un aviso. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF21-01 …')`.
