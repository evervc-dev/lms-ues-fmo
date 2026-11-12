---
tipo: prueba
rf: "[[RF27]]"
nombre: Registro y gestión de dispositivo móvil
prioridad: Alta
nivel: feature
casos: 8
estado: pasa
---

# CP-RF27 · Registro y gestión de dispositivo móvil

Casos derivados de los criterios de aceptación de [[RF27]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF27-01 | **Dado** un estudiante sin dispositivo, **cuando** registra su teléfono, **entonces** queda como su dispositivo activo. | pasa |
| CP-RF27-02 | **Dado** un estudiante con dispositivo activo, **cuando** intenta registrar otro, **entonces** se le indica solicitar el cambio. | pasa |
| CP-RF27-03 | **Dado** un estudiante con su sesión de Google, **cuando** solicita el cambio con un motivo, **entonces** queda pendiente para Soporte. | pasa |
| CP-RF27-04 | **Dado** un cambio en los últimos 30 días, **cuando** solicita otro, **entonces** debe adjuntar una justificación extra. | pasa |
| CP-RF27-05 | **Dado** Soporte, **cuando** aprueba, **entonces** revoca el dispositivo anterior, notifica al estudiante y queda en la auditoría; **cuando** rechaza, notifica con el motivo. | pasa |
| CP-RF27-06 | **Dado** una solicitud rechazada, **cuando** Soporte la abre en «Resueltas», **entonces** ve el motivo del rechazo que escribió, además de quién la resolvió y cuándo. | pasa |
| CP-RF27-07 | **Dado** un estudiante que solicita el cambio, **entonces** quienes atienden la bandeja reciben el aviso con el motivo; **cuando** uno de ellos la resuelve, el aviso de cierre le llega al resto del equipo y al estudiante, no a quien la resolvió. | pasa |
| CP-RF27-08 | **Dado** un estudiante sin dispositivo de confianza registrado, **cuando** navega en la plataforma, **entonces** se le presenta un modal de alerta con opción para ir a registrar su dispositivo. | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF27-01 …')`.
