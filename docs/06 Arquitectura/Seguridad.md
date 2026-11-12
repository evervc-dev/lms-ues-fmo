---
tipo: arquitectura
estado: revisado
rnf: ["[[RNF01]]", "[[RNF02]]", "[[RNF08]]"]
---

# Seguridad

| Amenaza / requisito | Control |
|---|---|
| Acceso con cuentas ajenas | Solo Google `@ues.edu.sv`, validado en el servidor; sin autorregistro ([[ADR-007 Inicio de sesión con Google]]) |
| Sesiones | En PostgreSQL; expiran a los 30 min; cookie `Secure`, `HttpOnly` y `SameSite=Lax`; regenerada al entrar; listado y cierre remoto desde el perfil |
| Autorización | spatie/permission (middleware `permission:` + `can()` en el Service) y alcance aplicado en el Service con `App\Support\Alcance`; **sin Policies ni Gates**, salvo el Gate `viewHorizon` del panel de Horizon ([[ADR-026 Autorización sin Policies]]); se prueba por rol ([[Matriz RBAC]]) |
| CSRF / XSS / inyección SQL | Token CSRF de Laravel; escape de Blade; HTMLPurifier en el editor y los foros; Eloquent parametrizado |
| Fraude de asistencia | Sesión de 5–15 min con **token rotativo de 10 s** + GPS en el área + token del dispositivo ([[ADR-045 Token de dispositivo]]) + 3 intentos + un registro por sesión ([[Flujo Asistencia con QR]], [[ADR-028 QR rotativo]]) |
| Fuerza bruta | Rate limit en el inicio de sesión de emergencia, en las claves de matrícula (5 intentos / 15 min) y en los códigos temporales |
| Archivos | Lista blanca de extensiones y MIME real; tamaño máximo; nombre aleatorio; fuera de la carpeta pública; URL firmada temporal |
| Secretos | `.env` fuera del repo; tokens de Google cifrados; `APP_KEY` rotable |
| Auditoría inmutable | **Triggers de PostgreSQL** (`fn_audit_row` con el actor de `DbContext`, `fn_block_mutation`); rol `app_rw` **sin UPDATE ni DELETE** sobre los logs; **RLS**: el Jefe solo ve su departamento ([[RNF02]], [[ADR-027 Base de datos híbrida]], [[ADR-029 Auditoría departamental del Jefe]]) |
| Transporte | HTTPS obligatorio + HSTS; WSS para Reverb |
| Cabeceras | CSP, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` (`geolocation=(self)`, `camera=()`) |
| Cuenta de emergencia | Contraseña + TOTP, ruta oculta, custodia dividida ([[Operación - Cuenta de emergencia]]) |
| Datos personales | Mínimos necesarios; las coordenadas GPS se guardan solo en el registro de asistencia; la retención se define con la UES |

## Inmutabilidad de la auditoría en PostgreSQL
```sql
REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs, login_history, enrollment_changes, attendance_scan_attempts FROM app_rw;
GRANT INSERT, SELECT ON audit_logs, login_history, enrollment_changes, attendance_scan_attempts TO app_rw;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;  -- política: admin todo, jefe su department_id
```
La aplicación se conecta con el rol `app_rw`; las migraciones corren con otro rol, `app_migrator`.
