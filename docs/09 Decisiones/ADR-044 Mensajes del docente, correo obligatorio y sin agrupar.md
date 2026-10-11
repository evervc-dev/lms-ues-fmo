---
tipo: adr
id: ADR-044
estado: pendiente
fecha: 2026-10-22
afecta: ["[[RF31]]", "[[RF23]]", "[[RNF12]]", "[[ADR-043 Agrupación y correo de las notificaciones]]"]
fuentes: ["Decisión del equipo (22/10/2026)"]
---

# ADR-044 · Mensajes del docente, correo obligatorio y sin agrupar

## Contexto
[[RF31]] permite al docente escribir, desde el libro de calificaciones, a quienes no entregaron una actividad o sacaron menos de cierta nota. El vault dice que es «correo, no mensajería interna», pero también que RF31 depende de [[RF23]], y el catálogo de avisos ya trae el evento `mass_message.sent` («Mensaje del docente»). Faltaba definir por qué canales sale, qué pasa con las preferencias de correo de [[RF23]] y si los avisos se agrupan como dicta [[ADR-043 Agrupación y correo de las notificaciones]].

## Decisión
*Decidido por el equipo el 22/10/2026:*

1. **Correo y aviso en la bandeja.** Cada estudiante recibe un correo **individual** (dirigido solo a él, sin copia a nadie) y un aviso en su campana del evento `mass_message.sent`. Sigue sin haber mensajería interna: no hay chat ni respuestas dentro de la plataforma.
2. **El correo no se puede silenciar.** Aunque el estudiante haya apagado el correo para ese tipo de aviso en sus preferencias, el mensaje del docente le llega. Es justo el aviso que más importa que alcance a quien no entregó o va mal. Conserva su preferencia para los demás tipos de aviso.
3. **No se agrupa.** Cada mensaje del docente dice algo distinto: agruparlos en «3 avisos nuevos» ([[ADR-043 Agrupación y correo de las notificaciones]]) perdería lo que dijo cada uno. Es una excepción a la ventana de 10 minutos.
4. **El correo responde al docente** (`Reply-To`), para que el estudiante pueda contestar sin mensajería interna. El correo lo declara: «Puede responder a este correo».
5. **Solo dos condiciones:** «no entregaron» y «nota menor a X» (la nota por defecto es la mínima de aprobación, 6.0, y solo cuenta la nota **publicada**). «No han abierto el recurso», que menciona la descripción de [[RF31]], queda fuera: depende de `resource_access_logs`, que solo existe en PostgreSQL, y no tiene criterio de aceptación ni caso de prueba.
6. **La lista se calcula siempre en el servidor.** Lo único que manda el navegador es a quién quitó el docente; al enviar se vuelve a calcular y un id que no cumpla la condición se ignora.

## Alternativas consideradas
- **Respetar la preferencia de correo:** coherente con el resto de avisos, pero el docente podría no alcanzar a quien más lo necesita y no se enteraría.
- **Solo correo, sin aviso en la plataforma:** más literal con el vault, pero deja sin uso la dependencia de [[RF23]] y el estudiante no ve nada dentro de la plataforma.
- **Agrupar como cualquier otro aviso:** uniforme, pero pierde el contenido de cada mensaje.

## Consecuencias
- El docente ve el efecto antes de enviar: el último paso avisa que el correo no se puede silenciar.
- El estudiante no puede dejar de recibir estos mensajes. Si hiciera falta, la salida es limitar quién puede enviarlos, no permitir silenciarlos.
- El `Reply-To` expone el correo institucional del docente al estudiante, que ya lo conoce.
- Un docente puede enviar a toda la clase: no se fijó un tope de destinatarios porque el vault no lo define.
