---
tipo: arquitectura
estado: revisado
adr: ["[[ADR-015 Plataforma base]]", "[[ADR-016 Paquetes]]"]
---

# Stack y paquetes

## Base ([[ADR-015 Plataforma base]])
| Pieza | Versión / elección |
|---|---|
| PHP | 8.4 |
| Laravel | 12 |
| Livewire | **4**, con Alpine.js |
| CSS | Tailwind CSS con **componentes propios** (sin kit de UI) |
| Base de datos | **PostgreSQL 17** |
| Caché y colas | **Redis 7** + **Laravel Horizon** |
| Sesiones | **PostgreSQL** (tabla `sessions`), para listarlas y cerrarlas en remoto ([[RNF01]]) |
| Tiempo real | **Laravel Reverb** + Echo ([[ADR-010 Notificaciones en tiempo real]]) |

## Paquetes ([[ADR-016 Paquetes]])
| Necesidad | Paquete | RF / RNF |
|---|---|---|
| Inicio de sesión con Google | `laravel/socialite` | [[RF01]] |
| Google Calendar | `google/apiclient` (solo Calendar) | [[RF21]] |
| Roles y permisos | `spatie/laravel-permission` | [[RF03]] |
| Token de dispositivo | **Sin paquete**: `random_bytes(32)`, SHA-256 y cookie `device_token` ([[ADR-045 Token de dispositivo]]) | [[RF27]], [[RF20]] |
| Auditoría | **Triggers de PostgreSQL** + `App\Support\DbContext` (sin paquete) | [[RF05]], [[RNF02]], [[ADR-027 Base de datos híbrida]] |
| Extensiones de PostgreSQL | `citext`, `btree_gist` | [[Modelo entidad-relación]] |
| 2FA de la cuenta de emergencia | `pragmarx/google2fa` | [[ADR-007 Inicio de sesión con Google]] |
| Excel (leer) | `App\Support\HojaCalculo`, sin dependencias ([[ADR-048 Lector propio de hojas para el reporte de Meet]]) | [[RF04]], [[RF21]] |
| Excel (escribir) y PDF | `openspout/openspout` + `barryvdh/laravel-dompdf` ([[ADR-049 Exportación a Excel y PDF]]) | [[RF17]], [[RF33]], [[RF06]], [[RF25]] |
| PDF | `barryvdh/laravel-dompdf` | [[RF17]], [[RF25]] |
| Respaldos | `spatie/laravel-backup` | [[RNF04]] |
| Sanitizar HTML | `mews/purifier` (HTMLPurifier) | [[RF11]], [[RF30]] |
| QR | `chillerlan/php-qrcode` (genera el SVG en el servidor; la pantalla del docente pide el QR cada 2 s y el token rota cada 10 s) | [[RF20]], [[ADR-028 QR rotativo]], [[ADR-046 Lector QR en la página y rotación por polling]] |
| Escaneo del QR | Lector en la página: `BarcodeDetector` nativo y, si falta, `barcode-detector` (ZXing en WebAssembly, servido desde la app) | [[RF20]], [[ADR-046 Lector QR en la página y rotación por polling]] |
| Zip de carpetas | `ZipStream-PHP` (streaming, sin archivo temporal) | [[RF11]] |
| Calidad | `pestphp/pest`, `pest-plugin-browser`, `larastan/larastan`, `laravel/pint` | [[ADR-013 Pruebas con Pest]] |

> [!warning] Interfaz propia
> Sin kit de UI, la **accesibilidad WCAG 2.1 AA** ([[RNF05]]) recae en el equipo: foco visible, roles ARIA en modales y acordeones, contraste y navegación con teclado. Se recomienda una carpeta `resources/views/components/ui` con componentes Blade reutilizables y probarla con axe.
