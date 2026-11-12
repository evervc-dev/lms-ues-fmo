---
tipo: prueba
rf: "[[RF01]]"
nombre: Autenticación con Google institucional
prioridad: Alta
nivel: browser + feature
casos: 6
estado: pasa
---

# CP-RF01 · Autenticación con Google institucional

Casos derivados de los criterios de aceptación de [[RF01]]. Nivel: **browser + feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF01-01 | **Dado** un usuario registrado y activo con cuenta `@ues.edu.sv`, **cuando** inicia sesión con Google, **entonces** entra al panel de su rol ([[RF24]]). | pasa |
| CP-RF01-02 | **Dado** una cuenta Google de otro dominio (p. ej. `@gmail.com`), **cuando** intenta entrar, **entonces** se rechaza con el mensaje «Use su correo institucional @ues.edu.sv» y se registra un intento fallido. | pasa |
| CP-RF01-03 | **Dado** una cuenta `@ues.edu.sv` que no existe en el sistema, **cuando** inicia sesión con Google, **entonces** se crea sin rol con sus nombres de Google (una sola vez), entra al inicio con el aviso «aún no tiene un rol asignado» y el alta queda en la auditoría como `user.self_registered` ([[ADR-038 Autorregistro sin rol]]). | pasa |
| CP-RF01-04 | **Dado** una cuenta desactivada, **cuando** intenta entrar, **entonces** se rechaza y se registra el intento. | pasa |
| CP-RF01-05 | **Dado** cualquier intento, **entonces** se registra fecha, hora, IP, dispositivo (user agent) y resultado ([[RNF02]]). | pasa |
| CP-RF01-06 | **Dado** una sesión sin actividad por 30 minutos, **entonces** expira ([[RNF01]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF01-01 …')`.
>
> **17/10/2026:** los 6 casos están automatizados en `tests/Feature/Identidad/LoginGoogleTest.php` con Socialite simulado y pasan en SQLite. CP-RF01-02 cubre cuatro variantes (otro dominio, sin `hd`, `hd` ajeno y correo sin verificar). CP-RF01-06 verifica la configuración de la sesión (30 min, sin expirar al cerrar el navegador). El ingreso real con Google se prueba a mano cuando TI entregue el cliente OAuth (`php artisan google:verificar`).
