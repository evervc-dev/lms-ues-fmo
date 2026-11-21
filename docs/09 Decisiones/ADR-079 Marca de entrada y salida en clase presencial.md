---
tipo: adr
id: ADR-079
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF19]]", "[[RF20]]", "[[RF28]]", "[[Docente]]", "[[Estudiante]]"]
fuentes: ["Decisión del usuario del 21/11/2026 (D3 de la revisión de flujos)"]
---

# ADR-079 · Marca de entrada y salida en clase presencial

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- RF19 pide registrar «la hora de entrada y salida cuando aplique». Hasta ahora la asistencia presencial solo tenía una marca: quien escaneaba al inicio quedaba Presente aunque se fuera a los diez minutos.
- [[ADR-076 Una sesión de asistencia por grupo y día]] dejó una sola sesión por día, así que la salida no puede ser una segunda sesión.

## Opciones
- **A. Salida dentro de la misma sesión, la abre el docente:** al final de la clase el docente abre una ventana de salida (5 a 15 min, QR nuevo) y los estudiantes vuelven a escanear.
- **B. Salida automática al terminar el bloque del horario.**
- **C. Sin salida:** solo la entrada.

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- Solo en la asistencia **presencial con QR**. La virtual sigue con el reporte de Meet ([[ADR-003 Asistencia virtual por reporte de Meet]]).
- El docente pulsa «Abrir marca de salida» en la sesión del día. Si la entrada sigue abierta, primero se cierra (quien no marcó queda Ausente). La sesión vuelve a `open`, `exit_opened_at` guarda el inicio de la salida y `opened_at` pasa a ser el inicio de esa ventana; el inicio de la entrada queda en la auditoría.
- La marca de salida (QR o código temporal) escribe `attendance_records.exit_recorded_at`. Quien no tenía registro lo obtiene con la marca de salida.
- Los intentos del QR se cuentan por ventana: la salida da otros 3 intentos ([[ADR-028 QR rotativo]]).
- Al cerrar la salida se recalcula cada registro:

| Entrada | Salida | Estado |
|---|---|---|
| Sí | Sí | El de la entrada (Presente, o Tarde si llegó tarde) |
| Sí | No | Tarde (vale 0,5) |
| No | Sí | Tarde (vale 0,5) |
| No | No | Ausente |

- «Entrada» es un registro Presente o Tarde anterior a la apertura de la salida. No se tocan las **justificadas** ni los registros que el docente **corrigió a mano** (`corrected_by`).
- Si el docente nunca abre la salida, la sesión se calcula como antes: solo cuenta la entrada.

## Consecuencias
- Columnas nuevas, nulas: `attendance_sessions.exit_opened_at` y `attendance_records.exit_recorded_at` (migración adaptativa, [[ADR-033 Migraciones adaptativas]]). Las auditan los triggers existentes.
- La salida se abre una sola vez por sesión y solo el mismo día, con el período en curso.
- Lo que falte después de cerrar la salida se corrige a mano, con motivo, como en [[ADR-076 Una sesión de asistencia por grupo y día]].
