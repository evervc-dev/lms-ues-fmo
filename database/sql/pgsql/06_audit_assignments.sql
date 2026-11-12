-- ADR-036 (pendiente) / RF04: auditoría de las asignaciones atadas a los roles.
-- Mismo patrón que 02_audit.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'teacher_profiles', 'student_profiles', 'student_careers', 'career_coordinators', 'department_heads'
    ] LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I', t || '_audit', t);
        EXECUTE format('CREATE TRIGGER %I AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_audit_row()', t || '_audit', t);
    END LOOP;
END;
$$;
