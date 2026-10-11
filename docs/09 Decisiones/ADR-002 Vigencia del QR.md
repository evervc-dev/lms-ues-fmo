---
tipo: adr
id: ADR-002
estado: reemplazada
fecha: 2026-10-10
afecta: ["[[RF20]]", "[[RF28]]", "[[Docente]]"]
fuentes: [Roles y permisos.pdf p.3, Requerimientos.pdf p.10]
---

# ADR-002 · Vigencia del QR de asistencia: 15 minutos

> [!warning] Reemplazada
> Reemplazada el 10/10/2026 por [[ADR-028 QR rotativo]]. Se conserva como historial.

## Contexto
Las fuentes se contradicen:
- **Roles y permisos** (Docente): «código QR dinámico temporal (token de 5 segundos)».
- **[[RF20]]**: «tiempo de vigencia configurable (ej. 5–15 minutos)».

## Opciones
- **A. Token que rota cada 5 s.** Una foto del QR enviada por WhatsApp caduca enseguida. A cambio, el docente debe mantener la pantalla proyectada y una mala conexión del estudiante hace fallar el escaneo.
- **B. Vigencia fija de 15 minutos.** Es simple y tolera redes lentas y filas de estudiantes. A cambio, una foto del QR sirve durante 15 minutos.

## Decisión
**B: el QR dura 15 minutos** desde que el docente abre la sesión. *Decidido por el equipo el 10/10/2026.*

## Consecuencias
- El riesgo de compartir una foto del QR se cubre con las otras dos validaciones de [[RF20]]: **ubicación GPS dentro del área** ([[RF26]]) y **dispositivo registrado con su token** ([[RF27]], [[ADR-045 Token de dispositivo]]). Un estudiante en casa con la foto falla por GPS, y otro teléfono falla por dispositivo.
- Quien llega después de los 15 minutos se registra con código temporal ([[RF28]]) o de forma manual ([[RF19]]).
- El docente puede cerrar la sesión antes de tiempo.
- El motivo de rechazo «token de 5 s expirado» pasa a ser «QR vencido (más de 15 min)».
