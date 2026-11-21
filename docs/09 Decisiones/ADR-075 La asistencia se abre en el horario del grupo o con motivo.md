---
tipo: adr
id: ADR-075
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF19]]", "[[RF20]]", "[[Docente]]", "[[ADR-027 Base de datos híbrida]]"]
fuentes: ["Decisión del usuario del 21/11/2026"]
---

# ADR-075 · La asistencia se abre en el horario del grupo o con motivo

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- El docente podía abrir la sesión de asistencia QR cualquier día y a cualquier hora: no se miraba el horario del grupo teórico (`section_schedules`). En las pruebas se llegó a marcar asistencia un sábado.
- El estudiante solo puede marcar mientras la sesión está abierta (5 a 15 min), así que limitar la apertura también limita cuándo marca él.
- Los docentes a veces adelantan o reponen clases, así que un bloqueo estricto no sirve.

## Opciones
- **A. Abrir libre dentro del horario; fuera de él, con un motivo obligatorio** que queda en la auditoría.
- **B. Fuera del horario, con aprobación previa del Coordinador** (como la cancelación de RF32).
- **C. Solo avisar** fuera del horario, sin bloquear.

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- La sesión se abre sin más si hoy hay un bloque del horario del grupo, desde **15 min antes del inicio hasta 15 min después del fin** (`config('lms.asistencia.margen_horario_minutos')`, `ASIS_MARGEN_HORARIO`).
- Fuera de ese rango, `AsistenciaService::abrir` exige un **motivo** (por ejemplo «Clase adelantada» o «Reposición de clase»). Va a la auditoría como `reason` del evento `attendance_session.opened`, con el prefijo «Fuera de horario:».
- `attendance_sessions` pasa a tener trigger de auditoría (`15_audit_attendance_sessions.sql`): antes solo se auditaban los registros, no la apertura ni el cierre.
- **`ASIS_BYPASS_WEEKEND=true`** quita la restricción para probar en fin de semana o fuera de horario. Se ignora en producción.

## Consecuencias
- Un grupo sin horario cargado siempre pide motivo: hay que cargar el horario en la carga académica.
- No hay columna nueva: el motivo vive en `audit_logs`, como el de las correcciones ([[RF19]]).
- Las pruebas de la suite corren con el bypass encendido (`tests/TestCase.php`); `AsistenciaHorarioTest` lo apaga para probar la regla.
- La sesión por reporte de Meet ([[RF21]]) no cambia: la crea el docente al confirmar el reporte, después de la clase.
