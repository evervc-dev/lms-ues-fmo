---
tipo: desarrollo
estado: borrador
actualizado: 2026-10-24
rf: ["[[RF11]]", "[[RF12]]", "[[RF13]]", "[[RF14]]", "[[RF17]]", "[[RF18]]", "[[RF34]]"]
tags: [plan]
---

# Planes de implementación pendientes: Contenidos y Calificaciones

Surgen de la revisión del código del 24/10/2026 contra los criterios de [[RF11]] a [[RF14]], [[RF17]], [[RF18]] y [[RF34]]. Cada plan sigue el formato de [[Directrices de IA]] y **necesita aprobación antes de implementarse**. Lo que el vault no define está marcado como decisión abierta.

> [!note] Hecho antes de estos planes
> El cierre del período por el Administrador (CP-RF34-07, CP-RF34-08 y CP-RF17-04) quedó en `test` con el PR #45.

## Orden sugerido
Plan 1 → Plan 2 → Plan 6 → Plan 8 → Plan 3 → Plan 4 → Plan 7 → Plan 5 → Plan 9.

## Decisiones que desbloquean más planes
1. Quién configura los cómputos y cuándo se copia la plantilla (Plan 2).
2. Lista de tipos y tamaño máximo de archivo (Planes 6 y 7).
3. Cola, latido y deduplicación de aperturas (Plan 8).
4. Aprobar las dependencias `mews/purifier`, ZipStream y una librería de exportación (Planes 4 y 7).

Con ADR pendiente: «en riesgo» (Plan 3). El formato de `completion_rule` quedó en [[ADR-067 Formato de la regla de finalización de un recurso]] (Plan 5) y las fechas vacías de RF14, en [[ADR-066 Evaluaciones sin programar al reutilizar]] (Plan 9).

---

## Calificaciones

### Plan 1 · RF34 restante: bloqueo, aviso y devoluciones
- **Criterios:** CP-RF34-01, 02, 04, 05 y 09. Renombrar con su ID las pruebas existentes de CP-RF34-03 y 06.
- **Archivos:**
  - `CalificacionService`: método `actaBloqueada()`, que usan `calificar()`, `publicar()` de actividad, `ActividadService` y `CuestionarioService`. Con el acta `published` o `validated` se rechaza el cambio de notas.
  - `resolverActa()` permite `validated → returned` mientras el período no esté cerrado.
  - `acta()` devuelve el conteo de actividades sin nota; el componente `Acta` lo muestra y pide confirmar. **Solo advierte, no bloquea** (decisión del 24/10/2026).
  - `Cierre` lista solo las secciones pendientes.
  - Eventos `ActaPublicada` y `ActaDevuelta` en `Calificaciones/Events`, con listeners en `Comunicacion/Listeners` y su notificación (cola `notificaciones`, `event_key` ya sembrados).
- **Migraciones:** ninguna.
- **Permisos y alcance:** `actas.publicar` y `actas.validar`, ya existentes, aplicados en el Service.
- **Jobs y colas:** la notificación, en `notificaciones`.
- **Pruebas:** una por criterio, con un rol permitido y uno denegado. Las que dependen de triggers se saltan fuera de PostgreSQL.
- **Riesgos:** el bloqueo toca tres servicios; hay que cubrirlos todos.

### Plan 2 · RF17: cómputos por sección
- **Criterios:** CP-RF17-01 y la parte pendiente de CP-RF17-11.
- **Archivos:** `ComputoService` nuevo, que copia `grading_component_templates` a `grading_components` de la sección y valida que los pesos sumen exactamente 100 % (hoy solo se rechaza pasar de 100). Pantalla Livewire para ver y editar los pesos.
- **Migraciones:** ninguna.
- **Permiso:** la [[Matriz RBAC]] no define uno claro para configurar cómputos; confirmarlo, no inventarlo.
- **Decisiones abiertas:** quién configura (docente, Coordinador o ambos) y cuándo se copia la plantilla (al crear la sección o a demanda).
- **Pruebas:** CP-RF17-01 con la suma exacta, más un rol permitido y uno denegado.

### Plan 3 · RF17: «en riesgo»
- **Criterios:** CP-RF17-05.
- **Archivos:** listener de `NotasPublicadas` que encola un job en `default`, idempotente. El job abre y cierra alertas `grade_risk` en `academic_alerts`; el panel del docente ya las lee.
- **Migraciones:** ninguna.
- **Decisiones abiertas (ADR):** fórmula de la proyección (con o sin actividades futuras como 0), cuándo se cierra la alerta y qué hace `notified_at`.
- **Pruebas:** `Queue::fake()` y una prueba del job con datos reales.

### Plan 4 · RF17: exportación y corrección
- **Criterios:** CP-RF17-06, 07 y 08.
- **Archivos:** botón de exportar en el libro, job en la cola `reportes` y registro en `exports`. `corregir()` recalcula y notifica al estudiante, como pide [[Flujo Cálculo de nota final]].
- **Decisión abierta:** la librería de Excel y PDF (necesita aprobación), el formato y las columnas.
- **Pruebas:** CP-RF17-08 con el job; rol permitido y denegado.

### Plan 5 · RF18: progreso por unidad — **hecho el 01/11/2026**
- **Criterios:** CP-RF18-01 a 03, los tres automatizados.
- **Hecho:** `ProgresoService` (reglas, descripción en palabras, evaluación retroactiva y avance por unidad); los eventos `EntregaRealizada` e `IntentoCalificado` con sus cuatro listeners en Contenidos; el paso «Cómo se completa» al publicar un recurso y su edición en la página del recurso; la pestaña **Progreso** con `progreso.ver`.
- **Decisiones:** [[ADR-067 Formato de la regla de finalización de un recurso]] (una regla por recurso, se evalúa con el evento, lo completado no se desmarca, al importar la regla que apunta a otra sección no se copia).
- **Migraciones:** ninguna: `completion_mode` y `completion_rule` ya existían.

## Contenidos

### Plan 6 · RF12 y RF11 base
- **Criterios:** CP-RF12-01 y 02; de RF11, CP-RF11-02, 03, 04, 05 y 08.
- **Archivos:**
  - Pruebas de RF12, más patrones de URL nuevos (`shorts/`, `embed/`, `m.youtube.com`).
  - `ContenidoService`: validar tipo y tamaño, cambiar el modo del PDF, unidades en la papelera y que `restaurar()` respete los 30 días.
  - Visor de PDF incrustado y dropzone con `x-ui.dropzone`.
  - Job `PurgarPapelera`, programado a diario en `routes/console.php`, que también borra el archivo físico.
  - Renombrar con ID las 8 pruebas existentes.
- **Migraciones:** ninguna; quizá un índice para la búsqueda.
- **Decisiones abiertas:** lista de tipos y tamaño máximo (hoy 50 MB sin lista) y qué pasa al restaurar un recurso cuya unidad está en la papelera.

### Plan 7 · RF11: editor y carpetas
- **Criterios:** CP-RF11-06 y 07.
- **Archivos:** editor de páginas con HTML sanitizado (`mews/purifier`) y tipo `folder` con `resource_files` y descarga en .zip (ZipStream).
- **Decisiones abiertas:** aprobar las dos dependencias, el editor (Trix, Tiptap o Quill), las etiquetas permitidas y el disco `archivos` (ADR-020, pendiente).

### Plan 8 · RF13: seguimiento
> [!success] Hecho el 01/11/2026 (rama `contenidos-rf13-seguimiento-recursos`)
> Decisiones del usuario: cola `default`, latido cada 30 s y una ventana de 5 minutos para no contar dos veces la misma visita.

- **Criterios:** CP-RF13-01, 02 y 03.
- **Archivos:** job `RegistrarAccesoRecurso`; separar «abrir» (lectura) de «registrar», que hoy duplica la apertura en cada recarga; registrar la descarga como `download`; latido con Alpine y `visibilitychange` hacia una acción Livewire que guarda `duration_seconds` (sin API, RNF09); mapa de calor con tiempo real.
- **Migraciones:** ninguna.
- **Decisiones abiertas:** la cola, cada cuántos segundos va el latido y cuántas aperturas cuenta una recarga.
- **Pruebas:** `Queue::fake()` para el job.

### Plan 9 · RF14: reutilización
> [!success] Hecho el 01/11/2026 (rama `contenidos-rf14-reutilizacion`)
> Decisiones en [[ADR-066 Evaluaciones sin programar al reutilizar]]: fechas opcionales («sin programar»), sin cómputo hasta programarla, banco compartido, rúbrica copiada con la tarea y una sola importación por sección de origen.

- **Criterios:** CP-RF14-01 y 02.
- **Archivos:** corregir que `importar()` comparte el mismo `file_path` (copiar el archivo físico); validar que el origen sea de un período anterior; impedir importar dos veces; copiar bancos y evaluaciones con un servicio en Evaluaciones.
- **Dependencia:** [[RF16]] estable.
- **Decisión abierta (ADR):** las fechas vacías al importar una evaluación chocan con `assessments.opens_at` NOT NULL; puede ser nullable, borrador no publicado o fechas ficticias.
