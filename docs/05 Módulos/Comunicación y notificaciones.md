---
tipo: modulo
nombre: Comunicación y notificaciones
estado: borrador
---

# Módulo · Comunicación y notificaciones

> Que cada usuario se entere a tiempo, sin duplicados.

## Qué hace
- **Notificaciones** en la plataforma (menos de 30 s) y por correo (menos de 5 min) para tareas, materiales, notas, horarios, matrícula, vencimientos y alertas.
- Preferencias por usuario; historial; agrupación de avisos.
- **Foros** con hilos anidados y moderación del docente.
- **Mensajería condicional**: correo a un segmento filtrado desde el libro de notas.

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
[[Docente]] · [[Estudiante]]

## Tablas
[[Modelo entidad-relación#Comunicación y notificaciones|Diagrama ER]]: `notification_events`, `notification_preferences`, `notifications`, `notification_deliveries`, `forum_threads`, `forum_posts`, `mass_messages`, `mass_message_recipients`

## Flujos
—

## Decisiones
[[ADR-010 Notificaciones en tiempo real]]

## Notas técnicas
- Laravel Notifications por los canales database, broadcast y mail.
- Reverb (WebSockets), con consulta cada 15 s como respaldo.
- Cola dedicada a notificaciones; clave de idempotencia por evento y usuario.
