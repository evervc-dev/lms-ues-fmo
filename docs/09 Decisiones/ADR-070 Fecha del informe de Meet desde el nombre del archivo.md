---
tipo: adr
id: ADR-070
estado: pendiente
fecha: 2026-11-17
afecta: ["[[RF21]]", "[[ADR-003 Asistencia virtual por reporte de Meet]]", "[[ADR-048 Lector propio de hojas para el reporte de Meet]]", "[[Flujo Asistencia virtual con reporte de Meet]]", "[[CP-RF21]]"]
fuentes: ["Informe real de Meet subido por el usuario (17/11/2026)", "Decisión del usuario del 17/11/2026"]
---

# ADR-070 · La fecha del informe de Meet sale del nombre del archivo, con un día de tolerancia

> [!info] Pendiente de aceptar por el equipo. Precisa cómo se cumple el criterio de [[RF21]] «un reporte cuya fecha no coincide con la sesión se rechaza».

## Contexto
[[RF21]] exige rechazar el informe que no sea de esa clase, y la implementación buscaba la fecha **dentro** del archivo. El informe real que exporta Google Meet en español no la trae:

| Nombre | Apellido | Correo electrónico | Duración | Hora a la que se unió | Hora a la que abandonó la reunión |
|---|---|---|---|---|---|
| Jesús Steven | Medrano Carballo | mc18055@ues.edu.sv | 6 min | 9:51 p. m. | 9:58 p. m. |

Solo hay **hora**, nunca fecha. La única fecha está en el nombre con que Google guarda el informe: `Informe de asistencia a poc-tbuu-xpb (2026-11-17 21:49).xlsx`. Con la validación anterior, **todo** informe real se rechazaba con «No se encontró la fecha de la reunión en el reporte».

## Opciones
- **A. Leer la fecha del nombre del archivo** cuando el contenido no la trae, y tolerar un día de diferencia.
- **B. Exigir la fecha en el contenido** (como estaba): ningún informe real se puede subir.
- **C. Quitar la validación de fecha:** incumple el criterio de [[RF21]].
- **D. Pedir al docente que confirme la fecha a mano:** un paso más y confía en quien se puede equivocar.

## Decisión
**A.** *Decidida por el usuario el 17/11/2026.* El orden es: fecha en el contenido → fecha en el nombre del archivo → ninguna.

- Si la fecha sale del **contenido** (informes de otras ediciones de Workspace, o exportaciones con fecha completa en «Hora a la que se unió»), debe coincidir **exactamente** con el día de la clase.
- Si sale del **nombre**, se toleran **± 1 día** (`ReporteMeetService::TOLERANCIA_DIAS`): esa marca es el momento en que Google generó el informe, y una clase que cruza la medianoche cambia de día.
- Si **no hay fecha por ninguna vía** (el docente renombró el archivo), **no se bloquea**: el borrador se guarda con `meet_reports.meeting_date` nulo y la vista previa avisa que no se pudo verificar, antes de confirmar.

## Consecuencias
- Nueva columna `meet_reports.meeting_date` (date, nulable) con la fecha detectada; ver [[Modelo entidad-relación]].
- La pantalla pide no renombrar el archivo descargado.
- Se amplían los encabezados reconocidos de la columna de entrada: «Hora a la que se unió», «Hora en la que se unió» y «Hora de unión», además de los que ya había.
- [[CP-RF21]]-08 se mantiene: se sigue rechazando el informe de otro día; se prueba con la fecha del nombre, con la tolerancia y con el caso sin fecha.
