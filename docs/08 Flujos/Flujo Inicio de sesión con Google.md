---
tipo: flujo
nombre: Inicio de sesión con Google
modulo: "[[Identidad y acceso]]"
rf: ["[[RF01]]"]
adr: ["[[ADR-007 Inicio de sesión con Google]]"]
estado: revisado
---

# Flujo · Inicio de sesión con Google

**Actores:** cualquier usuario, Google. **Precondición:** ninguna. Si la cuenta `@ues.edu.sv` no existe, se crea sin rol ([[ADR-038 Autorregistro sin rol]]); el alta previa ([[Flujo Carga masiva de usuarios]]) sirve para que entre ya con su rol.

```mermaid
sequenceDiagram
  actor U as Usuario
  participant L as LMS
  participant G as Google
  U->>L: Iniciar sesión con Google
  L->>G: Redirige (hd=ues.edu.sv, state)
  G-->>U: Selector de cuenta
  U->>G: Elige su cuenta y autoriza
  G-->>L: Callback con code
  L->>G: Intercambia el code por el perfil (sub, email, hd, email_verified)
  alt hd ≠ ues.edu.sv o email no verificado
    L-->>U: «Use su correo institucional @ues.edu.sv»
    L->>L: login_history (fallido: dominio)
  else Usuario no existe (ADR-038)
    L->>L: Crea la cuenta sin rol (auditoría: user.self_registered)
    L->>L: Vincula el sub, regenera la sesión, login_history (éxito)
    L-->>U: Inicio con el aviso «aún no tiene un rol asignado»
  else Usuario desactivado
    L-->>U: «Su cuenta está desactivada»
    L->>L: login_history (fallido: inactivo)
  else Válido
    L->>L: Vincula el sub en el primer ingreso, regenera la sesión
    L->>L: login_history (éxito, IP, user agent)
    L-->>U: Panel de su rol
  end
```

## Reglas
- El dominio se valida **en el servidor**; el parámetro `hd` de la URL solo filtra el selector.
- Se compara el parámetro `state` para evitar CSRF en OAuth.
- La sesión expira a los 30 minutos de inactividad ([[RNF01]]).

## Alterno: cuenta de emergencia
Ruta aparte, con contraseña y TOTP. Cada ingreso se audita y se avisa a los Administradores. Ver [[Operación - Cuenta de emergencia]].

## Postcondición
Sesión activa con el rol del usuario y un registro en `login_history`.
