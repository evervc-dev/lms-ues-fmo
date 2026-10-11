---
tipo: rol
nombre: Soporte Técnico
slug: soporte-tecnico
estado: borrador
fuente: Roles y permisos.pdf p.5–6
---

# Rol · Soporte Técnico

> **Enfoque:** Resolver incidencias de acceso, gestionar los dispositivos de asistencia y monitorear la operación.

**Alcance de datos:** Global, pero sin privilegios académicos: no ve notas ni contenidos. No matricula ([[ADR-032 Ajustes por el documento de roles]]).

**Navegación:** [[Layout Soporte Técnico]] (menú, vistas y flujos).

## Funcionalidades
### Dispositivos
- Aprobar o rechazar las solicitudes de cambio de teléfono que los estudiantes hacen con su sesión de Google ([[RF27]], [[Flujo Registro y cambio de dispositivo]]).
### Diagnóstico de asistencia
- Consultar en solo lectura los intentos de marcación QR y su motivo exacto: token vencido, fuera del área, dispositivo inválido, sin intentos ([[RF20]]).

### Cuentas
- Crear y corregir cuentas de estudiantes, y la **carga masiva** de estudiantes de cualquier carrera ([[RF04]], [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]). Docentes solo con el permiso directo `usuarios.crear-docente` ([[ADR-037 Jerarquía de creación y asignación de roles]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `usuarios.ver`
- `usuarios.gestionar`
- `usuarios.importar`
- `dispositivos.gestionar`
- `asistencia.diagnostico.ver`

## Notas
- Con el inicio de sesión de Google, los problemas de contraseña los resuelve TI de la UES, no este rol.
- No se aprobó una mesa de ayuda dentro del sistema (P08); las solicitudes llegan por los canales habituales.

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
