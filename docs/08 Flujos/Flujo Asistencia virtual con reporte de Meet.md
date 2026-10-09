---
tipo: flujo
nombre: Asistencia virtual con reporte de Meet
modulo: "[[Asistencia]]"
rf: ["[[RF21]]"]
adr: ["[[ADR-003 Asistencia virtual por reporte de Meet]]"]
estado: revisado
---

# Flujo · Asistencia virtual con el reporte de Meet

```mermaid
sequenceDiagram
  actor D as Docente
  participant L as LMS
  participant C as Google Calendar
  participant M as Google Meet
  D->>L: Programar clase virtual (fecha, hora, duración)
  L->>C: Crea un evento con conferencia de Meet e invitados (matriculados)
  C-->>L: Enlace de Meet
  L-->>D: Clase programada y notificada
  Note over D,M: La clase ocurre en Meet
  D->>M: Descarga el reporte de asistencia (Sheets → CSV/Excel)
  D->>L: Sube el reporte en la sesión
  L->>L: meet_reports + meet_report_rows, normaliza columnas (es/en), agrupa por correo y suma los tiempos
  L-->>D: Vista previa con Presentes, Ausentes y Sin asociar
  D->>L: Asigna o descarta las filas sin asociar y confirma
  L->>L: attendance_records por matrícula, method=meet, meet_percent guardado
  L->>L: Auditoría
```

## Cálculo
```mermaid
flowchart LR
  A[Minutos del estudiante en el reporte] --> B[÷ duración programada de la clase]
  B --> C{≥ 70 %?}
  C -- Sí --> P[PRESENTE]
  C -- No --> AU[AUSENTE]
```

## Validaciones
| Caso | Resultado |
|---|---|
| La fecha del reporte no coincide con la sesión | Se rechaza el archivo |
| Columnas no reconocidas | Se rechaza, indicando las columnas esperadas |
| Fila con correo `@ues.edu.sv` matriculado | Se asocia sola |
| Fila sin correo o con un correo ajeno | Va a **Sin asociar** |
| Matriculado que no aparece | **Ausente** |
| Se sube un segundo reporte | Reemplaza al anterior (auditado) |

## Pendientes
- Confirmar con TI que la edición de Workspace de la UES genera este reporte.
- Definir las columnas exactas con un reporte real.
