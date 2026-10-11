---
tipo: adr
id: ADR-043
estado: pendiente
fecha: 2026-10-22
afecta: ["[[RF23]]", "[[RNF12]]", "[[RNF03]]", "[[ADR-010 Notificaciones en tiempo real]]", "[[ADR-040 Solicitud de revisión de calificaciones]]"]
fuentes: ["Decisión del equipo (22/10/2026)"]
---

# ADR-043 · Agrupación y correo de las notificaciones

## Contexto
[[RF23]] exige que un aviso llegue a la plataforma en menos de 30 s y por correo en menos de 5 min ([[RNF12]]), que el usuario pueda apagar el correo por tipo de aviso, y que «varios avisos del mismo tipo en poco tiempo se agrupen en uno». El vault no define la ventana de la agrupación, qué se manda por correo ni cuándo basta el WebSocket y cuándo hay que consultar. [[ADR-010 Notificaciones en tiempo real]] fija Reverb y una consulta de respaldo cada 15 s, pero advierte que con 500 usuarios son unas 33 peticiones por segundo ([[RNF03]]).

## Decisión
*Decidido por el equipo el 22/10/2026:*

1. **Agrupación por ventana de 10 minutos.** Si el usuario ya tiene un aviso **sin leer** del **mismo tipo** (`event_code`) creado hace menos de 10 minutos, el aviso nuevo se suma a ese: sube `data.count`, el título pasa a «N avisos nuevos: <tipo>» y el cuerpo deja el último. No se crea otra fila. Un aviso ya leído no se reabre. La ventana se cuenta desde el primer aviso del grupo, así que un grupo no crece sin fin.
2. **Un aviso agrupado no genera otro correo.** El correo sale solo con el primero del grupo: es lo que cumple «duplicados = 0» de [[RNF12]]. Los demás se ven en la plataforma.
3. **Correo inmediato, una plantilla única.** Un correo por aviso, en la cola `notificaciones`, al correo institucional, con el título, el cuerpo y un botón al enlace. Se respeta `notification_preferences.mail_enabled`; sin fila, se asume que sí.
4. **Idempotencia por clave.** Un aviso con la misma `dedup_key` no se repite, ni suelto ni ya agrupado.
5. **La fila de la bandeja se crea en el momento y el push y el correo van por la cola.** Así cada fila de `notification_deliveries` apunta a una `notifications` que ya existe, sin depender del orden en que corran los trabajos de cada canal. Se registra la entrega por `database`, `broadcast` y `mail` con su `sent_at`, que es la medición de [[RNF12]].
6. **Respaldo por consulta condicional.** La campana consulta cada 15 s **solo mientras el WebSocket no esté conectado**. Con Reverb conectado no hace ninguna consulta periódica.
7. **Las cuentas sin rol no reciben avisos** ([[ADR-038 Autorregistro sin rol]]).

## Alternativas consideradas
- **Solo la deduplicación exacta** (lo que ya existía): cumple «sin duplicados» pero no «se agrupan en uno»; habría cambiado el requisito.
- **Columnas nuevas (`group_count`, `grouped_until`):** más fácil de consultar, pero exige migración de una tabla ya en uso y el contador cabe sin problema en `data`.
- **Resumen diferido cada pocos minutos:** reduce el volumen de correo, pero agrega un job y un estado intermedio, y el «≤ 5 min» queda con poco margen.
- **Consulta permanente cada 15 s:** más simple de probar, pero con 500 usuarios son ~33 peticiones por segundo solo de la campana.

## Consecuencias
- Un usuario que recibe una ráfaga de avisos del mismo tipo ve **una** fila con un contador y recibe **un** correo.
- Hay una consecuencia que conviene tener presente: los avisos agrupados **no tienen entrega propia** en `notification_deliveries`, porque no hay canal que ejecutar; solo el primero del grupo se mide.
- Las pruebas usan los fakes de Laravel; ninguna levanta Reverb ni un servidor de correo. El tiempo real de entrega (30 s y 5 min) se mide en staging con la tabla de entregas.
- Para ver la campana en vivo en local hay que levantar Reverb: `php artisan dev --reverb`.
