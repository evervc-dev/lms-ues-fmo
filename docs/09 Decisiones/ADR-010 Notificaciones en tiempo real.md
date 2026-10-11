---
tipo: adr
id: ADR-010
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF23]]", "[[RNF12]]"]
fuentes: [Requerimientos.pdf p.21]
---

# ADR-010 · ¿Cómo llega una notificación en menos de 30 s?

> **18/10/2026:** el contador y el autoguardado de los exámenes **no** usan Reverb: ver [[ADR-042 Flujo del examen sin depender de Reverb]]. Reverb queda para avisos y monitores.
>
> **22/10/2026:** la consulta de respaldo de 15 s corre **solo mientras el WebSocket no esté conectado**: ver [[ADR-043 Agrupación y correo de las notificaciones]].

## Contexto
[[RNF12]] exige notificaciones en la plataforma en menos de 30 s, sin duplicados.

## Opciones
- **A. Laravel Reverb (WebSockets).** Instantáneo y oficial de Laravel, con integración en Livewire mediante Echo. A cambio, es un proceso más que desplegar y vigilar.
- **B. Polling de Livewire (`wire:poll.15s`).** No requiere infraestructura extra y cumple los 30 s. A cambio, con 500 usuarios son unas 33 peticiones por segundo solo de polling ([[RNF03]]).

## Recomendación
**A (Reverb)**, con B como respaldo si el WebSocket no conecta. Reverb también sirve para el autoguardado y el contador de los exámenes ([[RNF10]]).

## Decisión
**A. Laravel Reverb**, con consulta cada 15 s como respaldo si el WebSocket no conecta. Reverb también se usa para el contador y el autoguardado de los exámenes ([[RNF10]]). *Decidido por el equipo el 10/10/2026.*
