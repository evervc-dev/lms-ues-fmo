---
tipo: adr
id: ADR-046
estado: aceptada
fecha: 2026-10-23
afecta: ["[[RF20]]", "[[ADR-028 QR rotativo]]", "[[ADR-045 Token de dispositivo]]", "[[RF26]]", "[[Stack y paquetes]]", "[[Flujo Asistencia con QR]]"]
fuentes: ["Decisión del equipo (23/10/2026)"]
---

# ADR-046 · Lector QR en la página, rotación por polling y sin tardanza con QR

Completa [[ADR-028 QR rotativo]] para implementar [[RF20]]. Ajusta el criterio de CP-RF20-01: el token vale dos rotaciones. No cambia la vigencia de 5 a 15 min, la rotación ni los 3 intentos.

## Contexto
El vault se contradecía: [[Stack y paquetes]] decía que el QR era una URL firmada que se lee con la cámara nativa y el [[Plan de implementación]] proponía un lector en la página. El [[Flujo Asistencia con QR]] actualizaba la proyección por Reverb, y la tardanza con QR (`late_after_minutes`) no tenía valor definido.

## Decisión
*Decidido por el equipo el 23/10/2026 y aceptado el 24/10/2026:*

1. **Lector en la página.** El estudiante abre «Marcar asistencia» en el mismo navegador donde registró su teléfono ([[ADR-045 Token de dispositivo]]), así llegan la cookie y la copia de `localStorage`. Primero se pide la ubicación y después se abre la cámara.
   - Lee con el `BarcodeDetector` nativo cuando el navegador lo trae y, si no, con `barcode-detector` (ZXing en WebAssembly, servido desde la app).
   - El QR se genera en SVG en el servidor con `chillerlan/php-qrcode`.
   - El QR contiene la URL de «Marcar asistencia» con `?qr=<sesión>.<token>`: si alguien lo lee con la cámara nativa, al menos abre la página correcta.
2. **Rotación por polling.** La pantalla de proyección pide el QR cada 2 s con `wire:poll`, sin depender de Reverb.
   - El token se deriva con HMAC de la sesión y de la ventana de rotación (`qr_rotation_seconds`), así que todas las pantallas de la sesión muestran el mismo.
   - Solo se guarda su SHA-256 en `qr_tokens`.
   - **Se acepta el token vigente y el inmediatamente anterior**, para cubrir la red lenta.
3. **Sin tardanza con QR.** Toda marca aceptada por QR queda `present`. La tardanza queda para el registro manual ([[RF19]]) y el código temporal ([[RF28]]).
4. **Ubicación.** Vale si la distancia al centro del área es ≤ al radio. El margen por la precisión ya va incluido en el radio ([[RF26]]). La precisión se guarda, pero no cambia el resultado. Las áreas de polígono, que aún no existen ([[RF26]]), se rechazan como `out_of_range`. *Actualizado por [[ADR-055 Leaflet y OpenStreetMap para las áreas de asistencia]]: desde el 28/10/2026 el polígono vale como cualquier área.*
   - > [!warning] Discrepancia detectada el 27/10/2026, pendiente de decidir
     > El código no valida contra **el** área de la sesión, sino contra **cualquiera** de las áreas activas: `AsistenciaQrService.php:256-258` acepta la ubicación si cae dentro de alguna, y también acepta la de la sesión aunque esté desactivada. Esta ADR y [[RF26]] hablan de «el área», en singular. La decisión no está escrita en ninguna parte: vive en el commit `b1632e1` y en la prueba `tests/Feature/Asistencia/AsistenciaQrTest.php:291`. Hay que confirmar la unión de áreas (y entonces corregir este punto) o restringirla al área de la sesión (y entonces corregir el código).

## Alternativas consideradas
- **URL firmada y cámara nativa:** no necesita lector en JS. Pero la cámara puede abrir otro navegador sin el token de dispositivo, y sale `invalid_device`.
- **`html5-qrcode`:** trae la cámara lista, pero no recibe cambios desde abril de 2023.
- **Reverb para la proyección:** actualiza en vivo, pero necesita Reverb en marcha en cada clase. El polling basta y es lo que ya usa la pantalla de la sesión.
- **Solo el token vigente:** es más estricto, pero un escaneo que llega un segundo tarde se rechaza.

## Consecuencias
- Nuevas dependencias: `chillerlan/php-qrcode` (composer) y `barcode-detector` (bun). El lector y el `.wasm` se cargan solo al escanear.
- Un token vale como máximo dos rotaciones (20 s con la rotación de 10 s).
- Con QR no hay `late`; el porcentaje de asistencia por QR solo cuenta presentes y ausentes.
