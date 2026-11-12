---
tipo: arquitectura
estado: revisado
adr: ["[[ADR-010 Notificaciones en tiempo real]]", "[[ADR-015 Plataforma base]]"]
rnf: ["[[RNF12]]", "[[RNF03]]"]
---

# Colas, programador y tiempo real

## Supervisores de Horizon
| Cola | Qué procesa | Procesos | Timeout | Alerta de espera |
|---|---|---|---|---|
| `notificaciones` | Notificaciones en la plataforma y correos ([[RNF12]]: < 30 s y < 5 min) | 2–6 | 60 s | 30 s |
| `default` | Registro de accesos a recursos, recálculos, finalización automática | 2–4 | 60 s | 120 s |
| `google` | Calendar: crear, actualizar y borrar eventos | 1–2 | 60 s | 300 s |
| `importaciones` | CSV de usuarios, Excel de asistencia, reporte de Meet | 1–2 | 600 s | 600 s |
| `reportes` | Exportaciones de Excel y PDF, reportes programados | 1–2 | 900 s | 1800 s |

Todos los jobs son **idempotentes**: un reintento no duplica notificaciones ni registros.

## Programador (`routes/console.php`)
| Tarea | Frecuencia | RF |
|---|---|---|
| Cerrar sesiones de asistencia vencidas y marcar ausentes | Cada minuto | [[RF19]], [[RF20]] |
| Enviar intentos de cuestionario vencidos | Cada minuto | [[RF16]] |
| Recordatorios de vencimiento (24 h antes) | Cada hora | [[RF23]] |
| Purgar la papelera (más de 30 días) | Diario 02:00 | [[RF11]] |
| Purgar códigos temporales vencidos | Cada hora | [[RF28]] |
| Purgar `qr_tokens` de más de 7 días | Diario 03:00 | [[RF20]] |
| Refrescar `mv_offer_stats` | Diario 02:30 | [[RF10]] |
| Crear la partición del mes siguiente de `audit_logs` y `resource_access_logs` | Mensual, día 25 | [[RNF02]] |
| Recalcular `academic_alerts` de riesgo por nota | Cada hora | [[RF17]] |
| Reportes programados | Según cada configuración | [[RF25]] |
| `backup:clean` y `backup:run` | Diario 01:00 y 01:30 | [[RNF04]] |
| `backup:monitor` | Diario 08:00 | [[RNF04]] |
| `horizon:snapshot` | Cada 5 min | — |

Todas usan `->onOneServer()->withoutOverlapping()`, por si en el futuro hay más de una instancia. Las que escriben tablas auditadas fijan `app.system_job = on` dentro de su transacción ([[ADR-027 Base de datos híbrida]]).

## Tiempo real (Reverb)
- Canales privados por usuario: `usuario.{id}` para notificaciones.
- Canal por intento de cuestionario: tiempo restante y aviso de cierre.
- **QR rotativo:** la pantalla de la sesión pide un token nuevo cada 10 s (`wire:poll.10s` o un evento de Reverb). No necesita el programador ([[ADR-028 QR rotativo]]).
- Si el WebSocket no conecta, Livewire consulta cada 15 s (`wire:poll.15s`) ([[ADR-010 Notificaciones en tiempo real]]).
