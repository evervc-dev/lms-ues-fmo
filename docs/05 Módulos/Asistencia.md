---
tipo: modulo
nombre: Asistencia
estado: borrador
---

# Módulo · Asistencia

> El problema número uno de la investigación: registrar la asistencia presencial y virtual de forma confiable y con poco trabajo para el docente.

## Qué hace
- **Sesión QR de 5 a 15 min con token que rota cada 10 s**, validada con **GPS dentro del área**, **dispositivo registrado** (token de dispositivo, [[ADR-045 Token de dispositivo]]) y 3 intentos; un registro por estudiante.
- **Código temporal de 30 s** para un estudiante que no puede escanear.
- **Marcado manual** e **importación de Excel/CSV**, con correcciones auditadas.
- **Asistencia virtual con el reporte de Meet** que sube el docente: Presente si es al menos 70 % de la duración programada.
- **Justificaciones** de inasistencia con documentos; **clases canceladas** y cambios de modalidad.
- **Alertas** al acercarse al límite del 75 %.

## Requisitos
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del módulo
    order: [file.name, nombre, prioridad, estado]
    sort:
      - property: file.name
        direction: ASC
```

## Roles
[[Docente]] · [[Estudiante]] · [[Coordinador]] · [[Administrador]]

## Tablas
[[Modelo entidad-relación#Asistencia|Diagrama ER]]: `attendance_areas`, `attendance_sessions`, `qr_tokens`, `attendance_records`, `attendance_scan_attempts`, `temporary_attendance_codes`, `attendance_justifications`, `video_sessions`, `meet_reports`, `meet_report_rows`

## Flujos
[[Flujo Asistencia con QR]] · [[Flujo Código temporal]] · [[Flujo Asistencia virtual con reporte de Meet]] · [[Flujo Alerta de inasistencias]]

## Decisiones
[[ADR-002 Vigencia del QR]] · [[ADR-003 Asistencia virtual por reporte de Meet]] · [[ADR-011 Prioridad de RF26 a RF28]] · [[ADR-012 Validación con cámara]]

## Notas técnicas
- Restricción única: un registro por estudiante y sesión.
- Cada rechazo de QR guarda su motivo exacto.
- La lectura del reporte de Meet tolera columnas en español e inglés.
- Confirmar con TI que la edición de Workspace de la UES genera el reporte de asistencia.
