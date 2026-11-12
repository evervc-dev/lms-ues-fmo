---
tipo: plan
estado: en-curso
actualizado: 2026-10-10
tags: [plan]
---

# Plan de documentación del LMS

Documentación funcional y técnica de la **Plataforma LMS** de Ingeniería de Sistemas Informáticos de la UES FMO. Se escribe a partir de los cuatro documentos que están en `Fuentes/`. Cubre lo que exige [[RNF13]]: arquitectura, base de datos, manual por rol, instalación, despliegue y flujos principales.

## Decisiones de partida

| Tema | Decisión |
|---|---|
| Herramienta | Vault de Obsidian `LMS-UES`, sin plugins de la comunidad. Usa Properties, Bases, Mermaid y Canvas del núcleo. |
| Fuente de verdad | `Requerimientos.pdf`. El alcance y la matriz de roles lo complementan. |
| Contradicciones | Cada una va a una nota de decisión (ADR) con estado `pendiente` en `09 Decisiones/`. El equipo la resuelve y luego se actualiza el requisito. |
| Stack | Laravel + Livewire, monolito sin API pública (RNF09). Ver [[ADR-001 Stack Laravel y Livewire]]. |
| Idioma | Español. Las fechas van como DD/MM/YYYY y la zona horaria es America/El_Salvador. |

## Convenciones del vault

- **Una nota por cosa.** Cada RF, RNF, rol, módulo, entidad, flujo y ADR tiene su propia nota, para poder enlazarlas con `[[RF20]]`.
- **Propiedades (frontmatter).** Toda nota lleva `tipo`, y además los campos de su plantilla: `prioridad`, `modulo`, `roles`, `rnf` y `estado`. Las vistas de Bases se arman con esos campos.
- **Estados:** `borrador` → `revisado` → `aprobado`. Las ADR usan `pendiente`, `aceptada` o `rechazada`.
- **Diagramas** en Mermaid dentro de la nota (`flowchart`, `sequenceDiagram`, `erDiagram`, `stateDiagram`). Los mapas grandes van en Canvas.
- **Citas a la fuente.** Cada nota indica el documento y la página de donde sale, por ejemplo `Fuente: Requerimientos.pdf p.10`.
- **Plantillas** en `Plantillas/`, que se insertan con *Templates: Insert template*.

## Estructura

```
00 Inicio.md                  Mapa del vault (MOC)
00 Plan de documentación.md   Este plan
01 Proyecto/                  Visión, alcance, glosario, actores
02 Investigación/             Metodología, entrevistas, encuestas, hallazgos
03 Requisitos/                RF01–RF28, RNF01–RNF14, trazabilidad (.base)
04 Roles/                     6 roles y la matriz RBAC
05 Módulos/                   9 módulos que agrupan los RF
06 Arquitectura/              Stack, componentes, integraciones, seguridad, infraestructura
07 Datos/                     Modelo ER y diccionario de datos
08 Flujos/                    Diagramas de secuencia de los procesos clave
09 Decisiones/                ADR, tanto decididas como pendientes
10 Calidad/                   Estrategia de pruebas y casos de prueba
11 Manuales/                  Manual por rol, instalación y despliegue
13 Layout/                    Layouts, menú por rol, vistas y flujos de navegación (ADR-034)
Plantillas/                   Plantillas de cada tipo de nota
Fuentes/                      PDFs originales
```

## Fases

Las fases 0 a 6 salen de los PDFs y se pueden escribir ya. Las fases 7 a 11 son diseño: se proponen en borrador y el equipo las valida.

### Fase 0: Base del vault
- [x] Crear el vault y las carpetas, copiar los PDFs a `Fuentes/` (el PDF de roles venía duplicado y se dejó una sola copia)
- [x] Escribir este plan
- [x] Crear las plantillas RF, RNF, Rol, Módulo, Flujo, ADR y Caso de prueba (la de Entidad se retiró por ADR-022)
- [x] Crear `00 Inicio.md` como mapa del vault

### Fase 1: Proyecto (`01 Proyecto/`) ✅
- [x] [[Visión y contexto]]: problema, situación actual, objetivos y restricciones.
- [x] [[Alcance]]: qué incluye, qué no y fase posterior, ajustado a las ADR.
- [x] [[Actores]]: usuarios, sistemas externos (diagrama) e interesados.
- [x] [[Glosario]]

### Fase 2: Investigación (`02 Investigación/`) ✅
- [x] [[Metodología]] y limitaciones.
- [x] [[Entrevista E1]] a [[Entrevista E7]], con respuestas, hallazgos clave y RF que sustentan.
- [x] [[Entrevista jefatura]]
- [x] [[Encuesta docentes]] (Tablas 9–26) y [[Encuesta estudiantes]] (Tablas 27–36).
- [x] [[Hallazgos]]: los 12 de la Tabla 37 y 5 adicionales, enlazados a RF y ADR.

### Fase 3: Requisitos (`03 Requisitos/`) ✅
- [x] **33 notas RF** (28 originales y 5 nuevos, RF29–RF33) con criterios de aceptación Dado/Cuando/Entonces, reglas, origen y ADR.
- [x] **14 notas RNF** con métricas verificables.
- [x] **[[Trazabilidad.base|Trazabilidad]]**, con vistas por módulo, por prioridad, afectados por decisiones y RNF.
- [x] **[[Requisitos propuestos]]**, todos resueltos: 5 RF nuevos, 5 fusionados, 1 rechazado (mesa de ayuda) y 1 opcional.
- [ ] Confirmar las prioridades propuestas para RF29–RF33.

### Fase 4: Roles y permisos (`04 Roles/`) ✅
- [x] Las 6 notas de rol.
- [x] **[[Matriz RBAC]]** con 47 permisos y su alcance (G/D/C/S/M/P).
- [ ] Confirmar los 2 puntos abiertos de la matriz: si el Jefe asigna docentes y si el Administrador ve contenidos y notas.

### Fase 5: Decisiones (`09 Decisiones/`) ✅
Todas resueltas el 10/10/2026.

| ADR | Decisión | Estado |
|---|---|---|
| [[ADR-001 Stack Laravel y Livewire\|001]] | Monolito Laravel + Livewire, sin API pública | ✅ aceptada |
| [[ADR-002 Vigencia del QR\|002]] | El QR dura **15 minutos** | ✅ aceptada |
| [[ADR-003 Asistencia virtual por reporte de Meet\|003]] | El docente **sube el reporte de Meet**; Presente si ≥ 70 % de la duración programada | ✅ aceptada |
| [[ADR-004 Acceso a la auditoría\|004]] | El Jefe ve la auditoría igual que el Administrador | ✅ aceptada |
| [[ADR-005 Coordinador y Jefe como roles separados\|005]] | Coordinador y Jefe son roles separados | ✅ aceptada |
| [[ADR-006 Rúbricas y calificación anónima\|006]] | Rúbricas sí; calificación anónima opcional | ✅ aceptada |
| [[ADR-007 Inicio de sesión con Google\|007]] | Solo Google `@ues.edu.sv`, más una cuenta de emergencia con 2FA | ✅ aceptada |
| [[ADR-008 Entregas tardías\|008]] | Entrega tardía configurable por tarea | ✅ aceptada |
| [[ADR-009 Tipos de pregunta\|009]] | 4 tipos de pregunta, con ensayo | ✅ aceptada |
| [[ADR-010 Notificaciones en tiempo real\|010]] | Reverb, con polling de respaldo | ✅ aceptada |
| [[ADR-011 Prioridad de RF26 a RF28\|011]] | RF26–RF28 con prioridad Alta | ✅ aceptada |
| [[ADR-012 Validación con cámara\|012]] | Validar con cámara encendida | ❌ rechazada |

### Fase 6: Módulos (`05 Módulos/`) ✅
Cada módulo tiene objetivo, funciones, tabla automática de RF, roles, entidades, flujos, decisiones y notas técnicas.

| Módulo | RF |
|---|---|
| [[Identidad y acceso]] | RF01, RF02 (descartado), RF03, RF04, RF27 |
| [[Gestión académica]] | RF06, RF07, RF08, RF09, RF10, RF29 |
| [[Contenidos]] | RF11, RF12, RF13, RF14 |
| [[Actividades y evaluaciones]] | RF15, RF16 |
| [[Calificaciones y progreso]] | RF17, RF18 |
| [[Asistencia]] | RF19, RF20, RF21, RF22, RF26, RF28, RF32 |
| [[Comunicación y notificaciones]] | RF23, RF30, RF31 |
| [[Dashboards y reportes]] | RF24, RF25, RF33 |
| [[Auditoría]] | RF05 |

### Fase 7: Arquitectura (`06 Arquitectura/`) ✅
- [x] [[Visión de arquitectura]] (C4 de contexto y de contenedores) · [[Stack y paquetes]] · [[Estructura del código]] · [[Integraciones con Google]] · [[Seguridad]] · [[Colas, programador y tiempo real]] · [[Almacenamiento de archivos]] · [[Entornos]] · [[Respaldos y recuperación]] · [[Capacidad y rendimiento]] · [[Observabilidad]]
- [x] ADR-015 a ADR-021.

| ADR | Decisión | Estado |
|---|---|---|
| [[ADR-015 Plataforma base\|015]] | Laravel 12, PHP 8.4, **Livewire 4**, **PostgreSQL 17**, Redis + **Horizon**, sesiones en PostgreSQL | ✅ |
| [[ADR-016 Paquetes\|016]] | laragear/webauthn, owen-it/auditing, maatwebsite/excel + dompdf, spatie/backup, **Tailwind propio** | ✅ |
| [[ADR-017 Código modular por dominio\|017]] | `app/Modules/<Modulo>` | ✅ |
| [[ADR-018 Calendar con OAuth del docente\|018]] | Calendar con OAuth incremental del docente | ✅ |
| [[ADR-019 Entornos\|019]] | Producción + staging, sin previews | ✅ |
| [[ADR-020 Hosting y almacenamiento\|020]] | Hosting y almacenamiento de archivos | ⏳ pendiente |
| [[ADR-021 Monitoreo\|021]] | Sin monitoreo por ahora (riesgo para RNF03) | ✅ |

### Fase 8: Modelo de datos (`07 Datos/`) ✅ · v2
- [x] [[Modelo entidad-relación]] **v2**, en inglés: parte del diseño de @Alex (84 tablas) adaptado a las ADR vigentes. Incluye vista general, 8 diagramas por módulo, reglas de integridad en SQL, triggers, RLS, parámetros (`system_settings`), vistas y volumen.
- [x] **Migraciones implementadas** el 11/10/2026 (14, adaptativas SQLite/PostgreSQL) — [[ADR-033 Migraciones adaptativas]].
- [x] Decisiones del 10/10/2026 al revisar el diseño de @Alex:

| ADR | Decisión |
|---|---|
| [[ADR-027 Base de datos híbrida\|027]] | Restricciones y triggers de auditoría e inmutabilidad en PostgreSQL; operaciones en los Services con `DbContext`; pruebas de BD en PostgreSQL |
| [[ADR-028 QR rotativo\|028]] | Sesión de 5–15 min con token que rota cada 10 s y 3 intentos (reemplaza ADR-002) |
| [[ADR-029 Auditoría departamental del Jefe\|029]] | El Jefe ve solo su departamento, con RLS (reemplaza ADR-004) |
| [[ADR-030 Matrícula ampliada\|030]] | Inscriben 6 roles en su ámbito; choque de horario del estudiante; sin validar prerrequisitos; carga masiva por el Coordinador; parámetros provisionales |
| [[ADR-031 Modelo de datos v2 en inglés\|031]] | Nombres en inglés y mejoras del diseño de @Alex (reemplaza ADR-022) |
| [[ADR-032 Ajustes por el documento de roles\|032]] | Actas y cierre departamental (RF34), Soporte sin matrícula, diagnóstico QR, calificación anónima, boleta por unidad, clave por asignatura, roles seguros |
| [[ADR-033 Migraciones adaptativas\|033]] | Migraciones adaptativas: SQLite por defecto, PostgreSQL completo | ✅ aceptada |
| [[ADR-034 Navegación y layouts\|034]] | 4 layouts, menú unificado por grupos, ☰ en el teléfono y una sola Vista de sección | ✅ aceptada |
| [[ADR-035 Vistas con Livewire\|035]] | Cada vista es un componente Livewire de página; wire:navigate; esqueleto navegable de 68 rutas; una página por pestaña; construcción por RF | ✅ aceptada |

### Fase 9: Flujos (`08 Flujos/`) ✅
12 flujos con diagramas validados por el parser de Mermaid (23 diagramas sin errores).

| Flujo | Decisiones de esta fase |
|---|---|
| [[Flujo Inicio de sesión con Google]] | — |
| [[Flujo Carga masiva de usuarios]] | Columnas: carnet, nombres, apellidos, correo, rol, carrera, plan, ciclo |
| [[Flujo Registro y cambio de dispositivo]] | Cambio solicitado con la sesión de Google + visto bueno de Soporte; 1 cada 30 días sin justificación extra |
| [[Ciclo de vida del período académico]] | 5 estados; solo el Admin reabre, con motivo |
| [[Flujo Matrícula y cambio de grupo]] | Cambio de grupo aprobado por el Coordinador, hasta la 3.ª semana |
| [[Flujo Asistencia con QR]] | Sin permiso de ubicación se rechaza; usar código temporal |
| [[Flujo Código temporal]] | — |
| [[Flujo Asistencia virtual con reporte de Meet]] | — |
| [[Flujo Alerta de inasistencias]] | Avisos al 85 % (preventivo) y al 78 % (crítico) |
| [[Flujo Entrega de tarea]] | — |
| [[Flujo Resolución de cuestionario]] | — |
| [[Flujo Cálculo de nota final]] | 6.0 mínima, 1 decimal, mitad hacia arriba (provisional) |

### Fase 10: Calidad (`10 Calidad/`) ✅
- [x] [[Estrategia de pruebas]]: Pest, pirámide, 5 flujos críticos en navegador, servicios simulados y SQLite en memoria (riesgo documentado).
- [x] [[Flujo de ramas y CI]]: `main` protegida, `test` y ramas `<modulo>-rfxx-<corto>`; PR a `test` con 1 aprobación y a `main` con 2; borrador del workflow de GitHub Actions.
- [x] [[Definition of Done]]
- [x] [[Pruebas de carga]]: 4 escenarios k6 con umbrales.
- [x] [[Registro de defectos]]: en OpenProject, tipo Bug ([[ADR-025 OpenProject como gestor del trabajo]]).
- [x] [[Casos de prueba]]: 166 casos en 32 notas `CP-RFxx`.
- [x] [[ADR-013 Pruebas con Pest]] y [[ADR-014 Ramas y CI en GitHub Actions]].

### Fase 11: Manuales (`11 Manuales/`) ✅ (esqueleto)
- [x] Manuales por tarea, redactados de usted, con marcadores de captura: [[Manual Estudiante]] (12 tareas) · [[Manual Docente]] (10) · [[Manual Coordinador]] (5) · [[Manual Jefe de Departamento]] (3) · [[Manual Administrador]] (5) · [[Manual Soporte Técnico]] (1)
- [x] [[Guía de bienvenida]] para nuevo ingreso, con diagramas y marcadores de ilustración.
- [x] [[Ayuda en la app]]: Markdown en `resources/ayuda`, ruta `/ayuda/{rol}` e ícono «?» por pantalla.
- [x] [[Instalación local]], que remite a [[Setup del entorno]] ([[ADR-023 Entorno de desarrollo estandarizado]]).
- [x] [[Despliegue]] (esqueleto hasta la fase 7).
- [x] [[Operación - Cuenta de emergencia]] (interna; no se publica).
- [ ] Capturas e ilustraciones cuando exista la interfaz.

### Fase 12: Desarrollo (`12 Desarrollo/`), en curso
- [x] [[Setup del entorno]]: Linux, WSL y Windows; Composer, Bun, migraciones, workers y Reverb.
- [x] [[Directrices de IA]]: Laravel Boost y modo orquesta; skill y subagentes de Claude Code.
- [x] [[Plantilla del repositorio]] y [[Bitácora]].
- [x] [[ADR-023 Entorno de desarrollo estandarizado]] y [[ADR-024 IA con Laravel Boost y modo orquesta]].
- [x] [[00 Plan de desarrollo]]: 5 equipos, hitos H0 a H4, entrega el 25/11, sin sprints.
- [x] [[OpenProject]]: configuración, carga desde el vault (`scripts/openproject.py`), uso diario ([[ADR-025 OpenProject como gestor del trabajo]]).
- [x] Vault movido al repositorio `~/Github/lms-ues/docs/`; plantilla aplicada en la raíz ([[Plantilla del repositorio]]).

## Orden acordado (de menor a mayor complejidad)

10 ✅ → 11 ✅ → 9 ✅ → 7 ✅ → 8 ✅. **Todas las fases tienen su primera versión.** Antes de cada fase se le pregunta al equipo.

## Orden anterior

1. ~~Fases 0, 3, 4 y 5~~ (hechas).
2. ~~Resolver las ADR y los [[Requisitos propuestos]]~~ (hecho el 10/10/2026).
3. ~~Fases 1, 2 y 6~~ (hechas).
4. Fases 8, 7 y 9. Diseño técnico.
5. Fases 10 y 11. Avanzan junto con el desarrollo.

## Pendiente de confirmar con el equipo
**Decisiones abiertas**
- [ ] [[ADR-020 Hosting y almacenamiento]]: dónde se hospeda y dónde se guardan los archivos.
- [ ] Prioridades de RF29–RF33 (propuestas: RF29 Media-Alta, el resto Media).
- [ ] 2 puntos de la [[Matriz RBAC]]: ¿el Jefe asigna docentes? ¿El Admin ve contenidos y notas?
- [x] Calendario de evaluaciones del período: en el del Administrador ([[ADR-068 Calendario de evaluaciones por ámbito]])
- [ ] Revisar [[ADR-021 Monitoreo]] antes de producción.

**Trámites con TI de la UES**
- [ ] Proyecto de Google Cloud, pantalla de consentimiento Interna, cliente OAuth y Calendar API.
- [ ] Confirmar que la edición de Workspace genera el reporte de asistencia de Meet.
- [ ] SMTP del correo institucional.
- [ ] Dominio `ues.edu.sv` y TLS.

**Insumos**
- [ ] Reglamento académico: nota mínima, redondeo, número de cómputos y % de asistencia (hoy provisionales: 6.0, 1 decimal, 75 %).
- [ ] Un reporte real de Meet, para fijar las columnas.
- [ ] Retención de la auditoría y de los datos personales.
