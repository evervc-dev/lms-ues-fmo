---
tipo: rnf
id: RNF01
nombre: Seguridad de datos
aliases: ["Seguridad de datos"]
categoria: Seguridad
prioridad: Alta
adr: ["[[ADR-007 Inicio de sesión con Google]]"]
estado: borrador
fuente: Requerimientos.pdf p.16
---

# RNF01 · Seguridad de datos

## Descripción
Toda comunicación va por HTTPS/TLS. Hay control de acceso por rol en cada módulo y protección de datos sensibles (notas, datos personales) contra accesos no autorizados, con mitigaciones contra inyección SQL, XSS y CSRF. Las sesiones expiran tras 30 minutos de inactividad y se pueden cerrar en remoto desde el perfil. La conservación de la información sigue las políticas institucionales.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Transporte | 100 % HTTPS con HSTS; HTTP redirige a HTTPS | Escaneo SSL Labs, nota A o mejor |
| Inactividad | Sesión expira a los 30 min | Prueba automatizada con `SESSION_LIFETIME=30` |
| Cierre remoto | El usuario cierra sus otras sesiones | Prueba de integración |
| Vulnerabilidades | 0 hallazgos altos del OWASP Top 10 | Escaneo OWASP ZAP antes de cada release |

## Implicaciones técnicas
- Con [[ADR-007 Inicio de sesión con Google]] el sistema no guarda contraseñas de usuarios; el hash bcrypt/argon2 y el 2FA solo aplican a la única cuenta de emergencia del Administrador.
- Sesiones en base de datos o Redis para poder listarlas y cerrarlas en remoto.
- Eloquent con consultas parametrizadas, escape de Blade y token CSRF de Laravel.
- Archivos subidos fuera de la carpeta pública, servidos con URL firmada.

## Requisitos funcionales relacionados
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF relacionados
    order: [file.name, nombre, prioridad, modulo]
```
