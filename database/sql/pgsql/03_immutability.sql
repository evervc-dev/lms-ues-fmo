-- ADR-027: historiales inmutables y registros que nunca se borran.

CREATE OR REPLACE FUNCTION fn_block_mutation() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'La tabla % es inmutable', TG_TABLE_NAME USING ERRCODE = 'AC901';
END;
$$;

CREATE OR REPLACE FUNCTION fn_block_delete() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'No se borran filas de %: desactive o retire en su lugar', TG_TABLE_NAME USING ERRCODE = 'AC902';
END;
$$;

-- Período cerrado: sin matrículas, notas ni asistencia nuevas, salvo la corrección auditada del Coordinador (RF17).
CREATE OR REPLACE FUNCTION fn_block_closed_period() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_status text;
BEGIN
    IF COALESCE(current_setting('app.allow_closed_period', true), '') = 'on' THEN
        RETURN NEW;
    END IF;

    SELECT ap.status INTO v_status
    FROM sections s JOIN academic_periods ap ON ap.id = s.period_id
    WHERE s.id = NEW.section_id;

    IF v_status = 'closed' THEN
        RAISE EXCEPTION 'El período de la sección % está cerrado', NEW.section_id USING ERRCODE = 'AC203';
    END IF;

    RETURN NEW;
END;
$$;

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY['audit_logs', 'enrollment_changes', 'attendance_scan_attempts', 'login_history', 'submission_events', 'device_events'] LOOP
        EXECUTE format('CREATE TRIGGER %I BEFORE UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_block_mutation()', t || '_immutable', t);
    END LOOP;

    FOREACH t IN ARRAY ARRAY['users', 'enrollments', 'grades', 'attendance_records', 'academic_periods'] LOOP
        EXECUTE format('CREATE TRIGGER %I BEFORE DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_block_delete()', t || '_no_delete', t);
    END LOOP;

    FOREACH t IN ARRAY ARRAY['enrollments', 'grades', 'attendance_records'] LOOP
        EXECUTE format('CREATE TRIGGER %I BEFORE INSERT OR UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION fn_block_closed_period()', t || '_closed_period', t);
    END LOOP;
END;
$$;
