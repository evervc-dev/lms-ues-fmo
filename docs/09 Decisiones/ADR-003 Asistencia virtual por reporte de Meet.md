---
tipo: adr
id: ADR-003
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF21]]", "[[RF22]]", "[[RNF09]]"]
fuentes: [Alcance - Aula virtual.pdf, Requerimientos.pdf p.11, Instrumentos de investigación.pdf]
---

# ADR-003 · Asistencia virtual con el reporte de Meet que sube el docente

## Contexto
- **Alcance:** «Presente» si el estudiante estuvo conectado a Google Meet al menos el 70 % de la clase.
- **[[RF21]]:** evidencias configurables con peso (tiempo de conexión, lectura, actividad de comprobación) para clasificar en Presente, Parcial o Ausente.
- **Jefatura:** validar la presencia con la cámara abierta.

## Opciones
- **A. Evidencias ponderadas.** Descartada: no es viable.
- **B. Leer la asistencia automáticamente con la API de Meet.** Descartada: hay muchas discrepancias. Quien entra con el enlace sin su cuenta, o no lo hace desde la web, no se puede asociar ni controlar.
- **C. El docente sube el reporte de asistencia que genera Meet** y el sistema lo procesa.

## Decisión
**C.** *Decidido por el equipo el 10/10/2026.*

**Regla:** el estudiante queda **Presente** si su tiempo en el reporte es al menos el **70 % de la duración programada** de la clase; si no, **Ausente**. No hay estado Parcial.

## Cómo funciona
1. Al terminar la clase, el docente descarga el reporte de asistencia de Meet: una hoja de Google Sheets que exporta como CSV o Excel.
2. Lo sube en la sesión virtual del curso.
3. El sistema asocia cada fila por el correo `@ues.edu.sv` y suma las filas repetidas (reconexiones).
4. Muestra una **vista previa** con tres grupos: Presentes, Ausentes y **Sin asociar** (filas sin correo o con un correo ajeno). El docente asigna o descarta las filas sin asociar y confirma.
5. Al confirmar se guarda la asistencia. Si sube otro reporte, reemplaza al anterior y queda en la auditoría.

## Consecuencias
- **No hace falta la API de Meet para la asistencia.** La integración con Google se limita al inicio de sesión ([[ADR-007 Inicio de sesión con Google]]) y a crear el evento en Calendar con su enlace de Meet.
- La asistencia virtual depende de que el docente suba el reporte. El panel le recuerda las sesiones sin reporte ([[RF24]]).
- La grabación queda disponible pero **no cuenta** como asistencia. La cámara tampoco ([[ADR-012 Validación con cámara]]).
- **Por verificar:** el reporte de asistencia de Meet solo existe en algunas ediciones de Google Workspace for Education. Hay que confirmar con TI que la UES lo tenga.
- Hace falta definir las columnas exactas del reporte con uno real, porque los nombres cambian según el idioma.
