-- Vistas solo de PostgreSQL (las portables están en la migración de vistas).

-- RF33: indicadores por docente y sección.
CREATE OR REPLACE VIEW v_teacher_kpis AS
SELECT
    ta.teacher_id,
    ta.section_id,
    sec.period_id,
    (
        SELECT avg(extract(epoch FROM (ats.opened_at - ((ats.session_date + ss.start_time) AT TIME ZONE 'America/El_Salvador'))) / 60)
        FROM attendance_sessions ats
        JOIN section_schedules ss ON ss.section_id = ats.section_id AND ss.weekday = extract(isodow FROM ats.session_date)
        WHERE ats.section_id = ta.section_id AND ats.opened_by = ta.teacher_id
    ) AS avg_minutes_to_open_attendance,
    (
        SELECT count(*)
        FROM resources r JOIN units u ON u.id = r.unit_id
        WHERE u.section_id = ta.section_id AND r.deleted_at IS NULL
    ) AS resources_count,
    (
        SELECT avg(extract(epoch FROM (g.published_at - sub.submitted_at)) / 3600)
        FROM submissions sub
        JOIN assessments a ON a.id = sub.assessment_id
        JOIN grades g ON g.grade_item_id = a.grade_item_id AND g.enrollment_id = sub.enrollment_id
        WHERE a.section_id = ta.section_id AND g.published_at IS NOT NULL
    ) AS avg_review_hours
FROM teaching_assignments ta
JOIN sections sec ON sec.id = ta.section_id
WHERE ta.ended_at IS NULL;

-- RF10: estadísticas de oferta (se refresca de noche con REFRESH MATERIALIZED VIEW CONCURRENTLY).
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_offer_stats AS
SELECT
    e.subject_id,
    e.period_id,
    count(*) AS enrolled,
    count(*) FILTER (WHERE er.result = 'passed') AS passed,
    count(*) FILTER (WHERE er.result = 'failed') AS failed
FROM enrollments e
LEFT JOIN enrollment_results er ON er.enrollment_id = e.id
WHERE e.status <> 'transferred'
GROUP BY e.subject_id, e.period_id;

CREATE UNIQUE INDEX IF NOT EXISTS mv_offer_stats_unique ON mv_offer_stats (subject_id, period_id);

-- Particiones mensuales (las crea el programador el día 25 para el mes siguiente).
CREATE OR REPLACE FUNCTION fn_ensure_month_partition(p_table text, p_month date) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
    v_from date := date_trunc('month', p_month)::date;
    v_to date := (date_trunc('month', p_month) + interval '1 month')::date;
    v_name text := format('%s_%s', p_table, to_char(v_from, 'YYYY_MM'));
BEGIN
    EXECUTE format('CREATE TABLE IF NOT EXISTS %I PARTITION OF %I FOR VALUES FROM (%L) TO (%L)', v_name, p_table, v_from, v_to);
END;
$$;
