---
tipo: rol
nombre: Administrador
slug: administrador
estado: borrador
fuente: Roles y permisos.pdf p.1–2
---

# Rol · Administrador

> **Enfoque:** Gestión global de la infraestructura institucional, la seguridad, la asignación de roles y la auditoría.

**Alcance de datos:** Global, sobre la infraestructura y la seguridad. **No** ve contenidos ni notas de los cursos ([[ADR-032 Ajustes por el documento de roles]]).

**Navegación:** [[Layout Administrador]] (menú, vistas y flujos).

## Funcionalidades
### Infraestructura académica
- Abrir y administrar períodos, ciclos o semestres con sus fechas límite ([[RF06]]).
- Mantener el catálogo general de asignaturas ([[RF07]]).
- Configurar las áreas geográficas de asistencia ([[RF26]]).
### Usuarios y seguridad
- Crear, editar, desactivar y consultar cuentas ([[RF04]]).
- Carga masiva por CSV con validación y prevención de duplicados ([[RF04]]).
- Configurar roles y permisos con spatie/laravel-permission ([[RF03]]).
### Auditoría e integraciones
- Consultar la auditoría completa ([[RF05]]).
- Revisar la configuración de Google Workspace (inicio de sesión, Calendar, Drive) y del correo en Integraciones, de solo lectura: se cambia en el `.env` del servidor ([[RNF09]]).
- Custodiar la cuenta de emergencia con 2FA ([[ADR-007 Inicio de sesión con Google]]).
- Cerrar el período cuando todos los departamentos autorizaron su cierre ([[RF34]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `usuarios.ver`
- `usuarios.gestionar`
- `usuarios.importar`
- `roles.gestionar`
- `integraciones.gestionar`
- `horizon.ver`
- `auditoria.ver`
- `periodos.gestionar`
- `asignaturas.gestionar`
- `secciones.gestionar`
- `secciones.asignar-docente`
- `matricula.gestionar`
- `oferta.estadisticas.ver`
- `areas-asistencia.gestionar`
- `clases.cancelar`
- `reportes.ver`
- `reportes.programar`

## Notas
- Con [[ADR-007 Inicio de sesión con Google]] ya no genera contraseñas temporales.

## Requisitos donde participa
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del rol
    order: [file.name, nombre, prioridad, modulo, estado]
```
