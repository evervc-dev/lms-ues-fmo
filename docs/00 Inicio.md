---
tipo: moc
tags: [inicio]
---

# Plataforma LMS · UES FMO

Sistema de gestión del aprendizaje para Ingeniería de Sistemas Informáticos de la Facultad Multidisciplinaria de Oriente. Es un monolito Laravel + Livewire integrado con Google Workspace.

> [!info] Estado
> Las 12 fases tienen su primera versión. Lo pendiente está al final de [[00 Plan de documentación]].
> El vault vive en el repositorio `lms-ues`, carpeta `docs/`. El plan de trabajo está en [[00 Plan de desarrollo]]. El avance se lleva en [[00 Plan de documentación]].

## Mapa
- **Proyecto:** [[Visión y contexto]] · [[Alcance]] · [[Actores]] · [[Glosario]]
- **Investigación:** [[Metodología]] · [[Hallazgos]] · [[Encuesta docentes]] · [[Encuesta estudiantes]] · [[Entrevista jefatura]] · Entrevistas [[Entrevista E1|E1]]–[[Entrevista E7|E7]]
- **Requisitos:** [[Trazabilidad.base|Trazabilidad]] · [[RF01]]…[[RF28]] · [[RNF01]]…[[RNF14]] · [[Requisitos propuestos]]
- **Roles:** [[Administrador]] · [[Estudiante]] · [[Docente]] · [[Coordinador]] · [[Jefe de Departamento]] · [[Soporte Técnico]] · [[Matriz RBAC]]
- **Módulos:** [[Identidad y acceso]] · [[Gestión académica]] · [[Contenidos]] · [[Actividades y evaluaciones]] · [[Calificaciones y progreso]] · [[Asistencia]] · [[Comunicación y notificaciones]] · [[Dashboards y reportes]] · [[Auditoría]]
- **Técnico:** [[Visión de arquitectura]] · [[Stack y paquetes]] · [[Seguridad]] · [[Integraciones con Google]] · [[Modelo entidad-relación]]
- **Flujos:** [[Flujo Asistencia con QR]] · [[Flujo Asistencia virtual con reporte de Meet]] · [[Ciclo de vida del período académico]] · [[Flujo Cálculo de nota final]] (12 en `08 Flujos/`)
- **Layout:** [[Layout base]] · [[Vista de sección]] · [[Mapa de vistas]] · por rol: [[Layout Estudiante|Estudiante]] · [[Layout Docente|Docente]] · [[Layout Coordinador|Coordinador]] · [[Layout Jefe de Departamento|Jefe]] · [[Layout Administrador|Administrador]] · [[Layout Soporte Técnico|Soporte]]
- **Calidad:** [[Estrategia de pruebas]] · [[Flujo de ramas y CI]] · [[Definition of Done]] · [[Casos de prueba]]
- **Manuales:** [[Guía de bienvenida]] · [[Manual Estudiante]] · [[Manual Docente]] · [[Instalación local]] · [[Despliegue]]
- **Desarrollo:** [[Roadmap]] ([tablero de avance](https://claude.ai/artifact/1QNY5M8X5WMjDYvNDDno98)) · [[00 Plan de desarrollo]] · [[OpenProject]] · [[Setup del entorno]] · [[Directrices de IA]] · [[Plantilla del repositorio]] · [[Bitácora]] · [[realeseMVPFaltante|Falta para el MVP 1.0]]
- **Decisiones:** [[ADR-001 Stack Laravel y Livewire|ADR-001]] … [[ADR-012 Validación con cámara|ADR-012]] (tabla de estados en [[00 Plan de documentación]])

## Decisiones pendientes
```base
filters:
  and:
    - tipo == "adr"
    - estado == "pendiente"
views:
  - type: table
    name: ADR pendientes
    order: [file.name, afecta, fecha]
```

## Fuentes
- [[Requerimientos.pdf]]
- [[Alcance - Aula virtual.pdf]] · [[Roles y permisos.pdf]] · [[Instrumentos de investigación.pdf]]
