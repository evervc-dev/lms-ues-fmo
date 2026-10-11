---
tipo: adr
id: ADR-031
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Modelo entidad-relación]]", "[[Estructura del código]]", "[[ADR-022 Convenciones del modelo de datos]]"]
fuentes: ["Diseño de BD de @Alex, 09/10/2026"]
---

# ADR-031 · Modelo de datos v2: nombres en inglés y mejoras del diseño de @Alex

**Reemplaza a [[ADR-022 Convenciones del modelo de datos]]**; conserva sus decisiones salvo el idioma.

## Decisión
*Decidido por el equipo el 10/10/2026:*

**Se mantiene de ADR-022:**
- PK `bigint` + `ulid` público en las entidades expuestas en URL; `timestamptz` en UTC. La app trabaja en hora de El Salvador y PostgreSQL guarda el instante: ver [[ADR-041 Zona horaria de El Salvador]].
- Soft delete solo en `units` y `resources`.
- GPS sin coordenadas: solo distancia, precisión y si estaba dentro.
- **Equipos por tarea** y **bancos de preguntas del docente** (compartibles).
- Tabla única de actividades calificables.
- Reporte de Meet completo: archivo, filas y resultado.

**Cambia:**
- **Tablas y columnas en inglés snake_case**, como Laravel y el diseño de @Alex. Los modelos van en inglés singular: `Enrollment`, `Section`, `AttendanceRecord`. El vault sigue en español.
- Diccionario de datos: el ER por módulo con los campos principales. El diccionario completo de columnas está en el documento de @Alex y en las migraciones.

**Se adoptan del diseño de @Alex:**
- **Asignaciones con vigencia:**
  - `teaching_assignments`, con un responsable y `ended_at`;
  - `department_heads` y `career_coordinators`, con `valid_from/valid_to`;
  - perfiles separados (`student_profiles`, `teacher_profiles`) y `student_careers`.
- **Matrícula:** con contexto completo (estudiante, sección, período y materia), estados `active`, `withdrawn` y `transferred`, e historial inmutable en `enrollment_changes`.
- **Registros ligados a la matrícula (`enrollment_id`):** notas, asistencia, entregas, intentos, progreso y accesos.
- **Notas:** `grading_components` (cómputos) y `grade_items` (actividades); `enrollment_results` con `policy_snapshot`.
- **Entregas:** `submission_files`, `submission_events`, `submission_feedback` y `submission_rubric_scores`.
- **Cuestionarios:** `question_options` y `quiz_question_rules` (varios bancos por tema o dificultad).
- **Parámetros y alertas:** `system_settings` con `is_confirmed`, y `academic_alerts`.
- **Notificaciones:** `notifications` con `dedup_key` único y `notification_deliveries` por canal.
- **Trazabilidad:**
  - `import_batches`/`import_batch_errors`, `device_events` y `login_history`;
  - `period_calendar_events`, `google_sync_refs`, `mass_messages`/`mass_message_recipients` y `content_copies`.
- **Vistas:** `v_section_capacity`, `mv_offer_stats` y `v_teacher_kpis`. `citext` en correos y códigos.
- **Particiones mensuales** en `audit_logs` y `resource_access_logs`.

**No se adoptan** (contradicen ADR vigentes):
- `password_reset_requests` y contraseñas de usuarios ([[ADR-007 Inicio de sesión con Google]]); solo existe la cuenta de emergencia.
- `virtual_attendance_config/evidence` ([[ADR-003 Asistencia virtual por reporte de Meet]]); en su lugar, `meet_reports` y `meet_report_rows`.
- `support_tickets` (la mesa de ayuda no se aprobó); en su lugar, `device_change_requests`.
