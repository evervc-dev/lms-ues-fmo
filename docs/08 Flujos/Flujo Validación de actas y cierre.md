---
tipo: flujo
nombre: Validación de actas y cierre
modulo: "[[Calificaciones y progreso]]"
rf: ["[[RF34]]", "[[RF17]]", "[[RF06]]"]
adr: ["[[ADR-032 Ajustes por el documento de roles]]"]
estado: revisado
---

# Flujo · Validación de actas y cierre del período

**Actores:** [[Docente]], [[Coordinador]], [[Jefe de Departamento]], [[Administrador]].

## Estados del acta
```mermaid
stateDiagram-v2
  state "draft" as draft
  state "published" as published
  state "returned" as returned
  state "validated" as validated
  [*] --> draft: Notas en registro
  draft --> published: Docente publica el acta
  published --> validated: Coordinador valida
  published --> returned: Coordinador devuelve con observaciones
  returned --> published: Docente corrige y vuelve a publicar
  validated --> returned: Coordinador reabre antes del cierre
  validated --> [*]: Período cerrado
```

## Del acta al cierre
```mermaid
sequenceDiagram
  actor D as Docente
  actor C as Coordinador
  actor J as Jefe
  actor A as Administrador
  participant L as LMS
  D->>L: Publicar acta de la sección
  L->>L: grade_sheets published (aviso si hay notas faltantes, que cuentan 0)
  L-->>C: Notificación: acta por validar
  alt Hay observaciones
    C->>L: Devolver con observaciones
    L-->>D: Notificación: acta devuelta
  else Correcta
    C->>L: Validar acta
  end
  J->>L: Autorizar cierre del departamento
  L->>L: ¿Todas las actas del departamento validated?
  L->>L: department_closures authorized
  A->>L: Cerrar período
  L->>L: ¿Todos los departamentos autorizados?
  L->>L: enrollment_results + policy_snapshot, período closed
```

## Reglas
- El Jefe solo autoriza su departamento (`department_heads` vigente). El Coordinador solo valida secciones de su carrera.
- Mientras el acta está `published` o `validated`, el docente no edita notas; para corregir hay que devolver el acta.
- Después del cierre, la única vía es la corrección auditada del Coordinador ([[Flujo Cálculo de nota final]]).
