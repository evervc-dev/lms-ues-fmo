---
tipo: prueba
rf: "[[RF23]]"
nombre: Notificaciones y comunicación centralizada
prioridad: Media
nivel: feature
casos: 5
estado: automatizado
---

# CP-RF23 · Notificaciones y comunicación centralizada

Casos derivados de los criterios de aceptación de [[RF23]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF23-01 | **Dado** un evento, **entonces** la notificación en la plataforma llega en menos de 30 s y el correo en menos de 5 min ([[RNF12]]). | automatizado |
| CP-RF23-02 | **Dado** que un usuario desactivó el correo para un tipo de evento, **entonces** solo lo recibe en la plataforma. | pasa |
| CP-RF23-03 | **Dado** varios avisos del mismo tipo en poco tiempo, **entonces** se agrupan en uno. | pasa |
| CP-RF23-04 | **Dado** un aviso con pantalla, **cuando** se abre desde la bandeja o la campana, **entonces** queda leído y lleva a esa pantalla; **dado** uno sin pantalla, solo queda leído y no muestra «Ir al evento». | pasa |
| CP-RF23-05 | **Dado** un usuario con el permiso `notificaciones.preferencias`, **cuando** abre «Mi perfil», **entonces** ve la pestaña de notificaciones; si para su rol no hay ningún aviso configurable, la pantalla lo explica en lugar de desaparecer. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF23-01 …')`.

> **CP-RF23-01 queda en `automatizado`, no en `pasa`:** las pruebas verifican el mecanismo (la fila de la bandeja se crea al instante, el push y el correo salen por la cola `notificaciones` y cada canal queda en `notification_deliveries` con su `sent_at`), pero ninguna levanta Reverb ni un servidor de correo. Los 30 s y los 5 min se miden en staging con esa tabla ([[ADR-043 Agrupación y correo de las notificaciones]]).
