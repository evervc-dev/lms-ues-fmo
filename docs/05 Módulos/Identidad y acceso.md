---
tipo: modulo
nombre: Identidad y acceso
estado: borrador
---

# Módulo · Identidad y acceso

> Quién entra al sistema, con qué rol y desde qué dispositivo.

## Qué hace
- Inicio de sesión **solo con Google `@ues.edu.sv`**, para usuarios dados de alta; más una cuenta de emergencia con 2FA.
- Alta individual y **carga masiva por Excel** (la hace el Coordinador para su carrera); activar, desactivar y reactivar sin perder historial.
- **RBAC** con 6 roles y alcance de datos por Policies.
- **Dispositivo único** del estudiante con token de dispositivo ([[ADR-045 Token de dispositivo]]); Soporte autoriza los cambios.
- Historial de inicios de sesión; cierre remoto de sesiones; expiración a los 30 min.

## Requisitos
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del módulo
    order: [file.name, nombre, prioridad, estado]
    sort:
      - property: file.name
        direction: ASC
```

## Roles
[[Administrador]] · [[Soporte Técnico]] · [[Estudiante]]

## Tablas
[[Modelo entidad-relación#Identidad y acceso|Diagrama ER]]: `users`, `student_profiles`, `teacher_profiles`, `login_history`, `import_batches`, `import_batch_errors`, `student_devices`, `device_events`, `device_change_requests`, `roles`

## Flujos
[[Flujo Inicio de sesión con Google]] · [[Flujo Carga masiva de usuarios]] · [[Flujo Registro y cambio de dispositivo]]

## Decisiones
[[ADR-007 Inicio de sesión con Google]] · [[ADR-005 Coordinador y Jefe como roles separados]]

## Notas técnicas
- Laravel Socialite (Google) con validación de `hd` en el servidor.
- spatie/laravel-permission + Policies.
- Token de dispositivo propio, sin paquete ([[ADR-045 Token de dispositivo]]).
- Importación en cola con informe por fila.
