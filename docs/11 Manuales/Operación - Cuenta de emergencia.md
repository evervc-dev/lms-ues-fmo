---
tipo: operacion
estado: borrador
confidencial: true
---

# Operación · Cuenta de emergencia del Administrador

> [!danger] Nota interna
> **No** se publica en la ayuda de la app y **no** contiene credenciales. Solo los pasos y quién custodia qué.

Origen: [[ADR-007 Inicio de sesión con Google]].

## Cuándo se usa
Solo si **el inicio de sesión con Google no funciona** (caída de Google, cliente OAuth revocado o vencido, error de configuración) y hace falta entrar a administrar.

## Custodia
| Elemento | Quién lo custodia | Dónde |
|---|---|---|
| Usuario y contraseña | ‹por definir› | Gestor de secretos del equipo |
| Semilla 2FA (TOTP) | ‹otra persona distinta› | ‹por definir› |
| Ruta de acceso | Administradores | Este documento |

Ninguna persona debe tener sola la contraseña y el 2FA.

## Procedimiento de uso
1. Confirmar que el inicio de sesión con Google está realmente caído.
2. Reunir a los dos custodios.
3. Entrar por la ruta de emergencia (‹ruta, fase 7›) con la contraseña y el código 2FA.
4. Resolver la incidencia.
5. Cerrar la sesión.
6. Registrar en un paquete de trabajo de OpenProject qué se hizo y por qué.

El sistema audita el ingreso y avisa por correo a todos los Administradores.

## Después de cada uso
- [ ] Rotar la contraseña.
- [ ] Revisar la auditoría de lo hecho durante la sesión.
- [ ] Cerrar el paquete de trabajo con la causa raíz.

## Recuperación (si se pierde la contraseña o el 2FA)
Desde el servidor, con acceso de operador:
```bash
php artisan lms:emergencia:restablecer
```
Este comando genera una contraseña y una semilla 2FA nuevas, y queda auditado. ‹Se implementa en la fase 7.›
