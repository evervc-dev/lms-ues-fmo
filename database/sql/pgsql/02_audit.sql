-- ADR-027 / RNF02: auditoría por triggers con el actor de la transacción (App\Support\DbContext).

CREATE OR REPLACE FUNCTION fn_audit_department(p_row jsonb) RETURNS bigint
LANGUAGE plpgsql STABLE AS $$
DECLARE
    v_section bigint;
    v_department bigint;
BEGIN
    IF p_row ? 'department_id' AND p_row->>'department_id' IS NOT NULL THEN
        RETURN (p_row->>'department_id')::bigint;
    END IF;
    IF p_row ? 'section_id' AND p_row->>'section_id' IS NOT NULL THEN
        v_section := (p_row->>'section_id')::bigint;
        SELECT sub.department_id INTO v_department
        FROM sections sec JOIN subjects sub ON sub.id = sec.subject_id
        WHERE sec.id = v_section;
        RETURN v_department;
    END IF;
    RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION fn_audit_row() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_actor text := NULLIF(current_setting('app.user_id', true), '');
    v_system text := COALESCE(current_setting('app.system_job', true), '');
    v_old jsonb;
    v_new jsonb;
    v_row jsonb;
    v_id bigint;
BEGIN
    IF v_actor IS NULL AND v_system <> 'on' THEN
        RAISE EXCEPTION 'Escritura en % sin actor: use DbContext o declare app.system_job', TG_TABLE_NAME
            USING ERRCODE = 'AC900';
    END IF;

    IF TG_OP <> 'INSERT' THEN v_old := to_jsonb(OLD); END IF;
    IF TG_OP <> 'DELETE' THEN v_new := to_jsonb(NEW); END IF;
    v_row := COALESCE(v_new, v_old);

    IF v_row ? 'id' AND (v_row->>'id') ~ '^[0-9]+$' THEN
        v_id := (v_row->>'id')::bigint;
    END IF;

    INSERT INTO audit_logs (occurred_at, actor_user_id, department_id, ip_address, request_id,
                            event_type, entity_type, entity_id, action, old_values, new_values, reason)
    VALUES (now(), v_actor::bigint, fn_audit_department(v_row),
            NULLIF(current_setting('app.ip', true), '')::inet,
            NULLIF(current_setting('app.request_id', true), ''),
            NULLIF(current_setting('app.event_type', true), ''),
            TG_TABLE_NAME, v_id, lower(TG_OP), v_old, v_new,
            NULLIF(current_setting('app.reason', true), ''));

    RETURN COALESCE(NEW, OLD);
END;
$$;

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'users', 'model_has_roles', 'role_has_permissions', 'system_settings',
        'academic_periods', 'teaching_assignments', 'enrollments', 'grades',
        'grade_sheets', 'department_closures', 'attendance_records', 'student_devices'
    ] LOOP
        EXECUTE format('CREATE TRIGGER %I AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_audit_row()', t || '_audit', t);
    END LOOP;
END;
$$;
