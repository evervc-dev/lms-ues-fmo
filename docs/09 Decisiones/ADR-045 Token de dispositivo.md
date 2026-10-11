---
tipo: adr
id: ADR-045
estado: aceptada
fecha: 2026-10-23
afecta: ["[[RF27]]", "[[RF20]]", "[[ADR-016 Paquetes]]", "[[ADR-028 QR rotativo]]", "[[ADR-011 Prioridad de RF26 a RF28]]"]
fuentes: ["Decisión del equipo (23/10/2026)"]
---

# ADR-045 · Token de dispositivo en lugar de WebAuthn

**Reemplaza la parte WebAuthn de [[ADR-016 Paquetes]]** (`laragear/webauthn`) **y de [[ADR-028 QR rotativo]]** («dispositivo WebAuthn»). El resto de [[ADR-028 QR rotativo]] sigue vigente: sesión de 5 a 15 min, token del QR que rota cada 10 s y 3 intentos por estudiante.

## Contexto
[[RF27]] pide que cada estudiante tenga **un solo teléfono autorizado** para marcar asistencia con QR ([[RF20]]). Las decisiones anteriores lo resolvían con una Passkey (WebAuthn, paquete `laragear/webauthn`). El equipo la considera **costosa de implementar y de dar soporte**: el comportamiento de WebAuthn varía entre navegadores y teléfonos del alumnado (versión del sistema, gestor de contraseñas, biometría disponible), cada fallo exige ayuda de Soporte y las pruebas de navegador necesitan un autenticador virtual.

## Decisión
*Decidido por el equipo el 23/10/2026 y aceptado el 23/10/2026:*

La asistencia se ata a un **token de dispositivo** propio, sin WebAuthn.

1. **Token.** Al registrar el teléfono, el servidor genera un **token aleatorio de 32 bytes** (`random_bytes(32)`, codificado para transportarlo).
2. **Qué se guarda.** Solo el **SHA-256 del token**, en `student_devices.credential_id` (`UNIQUE`). **No hay migración ni columnas nuevas**: la columna ya existe en el [[Modelo entidad-relación]]; cambia solo lo que contiene. El token en claro no se guarda.
3. **Dónde vive en el teléfono.** Una cookie `device_token` (`HttpOnly`, `Secure`, `SameSite=Lax`, de 1 año) **más una copia en `localStorage`**.
4. **Cuándo vale.** Un intento de asistencia es del dispositivo activo si **cualquiera de los dos** (la cookie o la copia de `localStorage`) coincide con el hash del dispositivo activo del estudiante. Si solo coincide uno, el servidor vuelve a escribir el otro.
5. **Si se pierden ambos** (cambio de teléfono, datos del navegador borrados), el estudiante **solicita el cambio a Soporte** con el flujo y la regla de 30 días de [[RF27]]. Mientras tanto marca con **código temporal** ([[RF28]]) o de forma **manual** ([[RF19]]).
6. **Sin cambios en el resto.** Un solo dispositivo activo por estudiante, revocación solo por Soporte, `device_events` y auditoría siguen como en [[RF27]]. En [[RF20]] el motivo de rechazo sigue siendo `invalid_device`.

## Alternativas consideradas
- **Mantener WebAuthn / Passkey** (`laragear/webauthn`): más fuerte, porque la clave privada no sale del teléfono, pero es lo que el equipo descartó por costo y soporte.
- **Solo cookie:** más simple, pero se pierde al limpiar el navegador. La copia en `localStorage` reduce esos falsos rechazos.
- **Huella del navegador (fingerprint):** frágil, cambia con las actualizaciones y es peor para la privacidad.

## Consecuencias
- La prioridad Alta de [[RF27]] ([[ADR-011 Prioridad de RF26 a RF28]]) no cambia: sigue siendo una de las validaciones que hacen seguro a [[RF20]].
- Se elimina el paquete `laragear/webauthn` del stack y el autenticador virtual de las pruebas de navegador.
- El registro no pide huella, rostro ni PIN: basta pulsar «Registrar este teléfono» con la sesión de Google activa.
- **Riesgo aceptado.** El token vive en el cliente, así que **se puede copiar o borrar**, y es **más débil que WebAuthn**: quien copie el token podría usarlo desde otro aparato, y quien borre la cookie y la copia pierde su dispositivo.
- **Mitigaciones** (ninguna depende del token solo):
  - **Un solo dispositivo activo** por estudiante y cambio autorizado por Soporte.
  - **GPS** dentro del área ([[RF26]]).
  - **Máximo 3 intentos** por sesión.
  - **Token del QR rotativo de 10 s**: no sirve una foto.
  - **Sesión de Google `@ues.edu.sv` obligatoria**: el token por sí solo no identifica a nadie.
  - Todo intento queda en `attendance_scan_attempts` y el cambio de dispositivo, en la auditoría.
- Si el equipo acepta esta ADR, hay que actualizar [[Stack y paquetes]] y las guías de `.ai/guidelines/` (Boost) para quitar WebAuthn.
