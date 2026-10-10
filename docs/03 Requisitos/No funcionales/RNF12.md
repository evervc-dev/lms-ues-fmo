---
tipo: rnf
id: RNF12
nombre: Eficiencia en notificaciones
aliases: ["Eficiencia en notificaciones"]
categoria: Rendimiento
prioridad: Media
adr: ["[[ADR-010 Notificaciones en tiempo real]]"]
estado: borrador
fuente: Requerimientos.pdf p.21
---

# RNF12 · Eficiencia en notificaciones

## Descripción
Las notificaciones en la plataforma llegan en menos de 30 s tras el evento y los correos en 5 minutos como máximo. No se envían notificaciones duplicadas.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Plataforma | < 30 s | Marca de tiempo del evento frente a la entrega |
| Correo | ≤ 5 min | Registro de la cola de correo |
| Duplicados | 0 | Clave de idempotencia por evento y usuario |

## Implicaciones técnicas
- Laravel Reverb (WebSockets), con consulta cada 15 s como respaldo ([[ADR-010 Notificaciones en tiempo real]]).
- Una cola dedicada a notificaciones para que los reportes no la retrasen.

## Requisitos funcionales relacionados
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF relacionados
    order: [file.name, nombre, prioridad, modulo]
```
