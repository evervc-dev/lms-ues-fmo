---
tipo: adr
id: ADR-016
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Stack y paquetes]]", "[[RF05]]", "[[RF27]]", "[[RF25]]", "[[RNF04]]"]
fuentes: []
---

# ADR-016 · Paquetes principales e interfaz

> [!warning] Esta ADR ha sido superada en casi todo
> De las cinco elecciones solo sigue vigente la última. Revisado el 25/10/2026:
>
> | Elección | Hoy |
> |---|---|
> | `laragear/webauthn` | superada por [[ADR-045 Token de dispositivo]]: token propio |
> | `owen-it/laravel-auditing` | superada por [[ADR-027 Base de datos híbrida]]: triggers de PostgreSQL |
> | `maatwebsite/excel` (leer) | superada por [[ADR-048 Lector propio de hojas para el reporte de Meet]]: `App\Support\HojaCalculo` |
> | `maatwebsite/excel` (escribir) | superada por [[ADR-049 Exportación a Excel y PDF]] |
> | `barryvdh/laravel-dompdf` | **vigente**, y [[ADR-049 Exportación a Excel y PDF]] lo pone en uso |
> | `spatie/laravel-backup` | sin instalar; no hay ADR que lo supere |
> | Livewire + Tailwind propios | **vigente** |

## Decisión
*Decidido por el equipo el 10/10/2026:*

| Necesidad | Elección |
|---|---|
| WebAuthn | `laragear/webauthn` |
| Auditoría | `owen-it/laravel-auditing` |
| Excel y PDF | `maatwebsite/excel` + `barryvdh/laravel-dompdf` |
| Respaldos | `spatie/laravel-backup` |
| Interfaz | **Livewire + Tailwind con componentes propios**, sin kit de UI |

Los paquetes de apoyo (Socialite, google/apiclient, google2fa, HTMLPurifier, php-qrcode, ZipStream) se listan en [[Stack y paquetes]] como propuesta técnica.

## Consecuencias
- La accesibilidad WCAG 2.1 AA de los componentes propios es responsabilidad del equipo ([[RNF05]]).
