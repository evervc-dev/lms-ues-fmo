---
tipo: adr
id: ADR-007
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF01]]", "[[RF02]]", "[[RF04]]", "[[RNF01]]", "[[RNF09]]"]
fuentes: [Requerimientos.pdf p.1, Instrumentos de investigación.pdf (jefatura)]
---

# ADR-007 · Inicio de sesión solo con Google, dominio ues.edu.sv

> **17/10/2026:** «No hay autorregistro» queda reemplazado por [[ADR-038 Autorregistro sin rol]] (aceptada): una cuenta `@ues.edu.sv` válida que no existe se crea sin rol al primer ingreso.

## Contexto
**[[RF01]]** y **[[RF02]]** pedían correo y contraseña propios del sistema, con recuperación por enlace. La **jefatura** pidió que la contraseña esté sincronizada con el correo institucional y que la plataforma esté bajo el dominio ues.edu.sv.

## Decisión
**Inicio de sesión exclusivamente con Google**, solo para cuentas del dominio **`@ues.edu.sv`** (por ejemplo `rr19118@ues.edu.sv`). *Decidido por el equipo el 10/10/2026.*

## Implementación
- Laravel Socialite con el driver de Google y `->with(['hd' => 'ues.edu.sv'])`. Esto solo filtra la pantalla de Google.
- **Validación obligatoria en el servidor** al volver de Google:
  - `hd === 'ues.edu.sv'`
  - `email_verified === true`
  - el correo termina en `@ues.edu.sv`
  - el usuario existe y está activo ([[RF04]])
- Si todo cumple, se vincula el `sub` de Google al usuario en el primer ingreso; si no, se rechaza y queda en la auditoría.
- No hay autorregistro: el Administrador da de alta por CSV.

## Consecuencias
- **[[RF02]] queda descartado**: las contraseñas las gestiona Google o TI de la UES.
- En [[RF04]] desaparece «restablecer contraseñas de otros usuarios».
- [[RNF01]]: el sistema no guarda contraseñas de usuarios.
- Hace falta un **cliente OAuth en un proyecto de Google Cloud de la UES**. El mismo proyecto sirve para crear los eventos en Calendar con su enlace de Meet ([[ADR-003 Asistencia virtual por reporte de Meet]]). Hay que tramitarlo con TI.
- Si Google falla, solo se puede entrar con la cuenta de emergencia.

## Cuenta de emergencia
**Aprobada** (10/10/2026): existe **una sola** cuenta local de Administrador con contraseña (hash bcrypt o argon2) y **2FA (TOTP)**.
- Se entra por una ruta aparte, que no aparece en la pantalla de inicio de sesión.
- Cada uso queda en la auditoría y se notifica a los Administradores.
- Se usa solo si falla Google u OAuth. Las credenciales se guardan fuera del sistema, según el procedimiento del manual de instalación.
