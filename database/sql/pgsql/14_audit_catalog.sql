-- H-08 (informe de QA del rol Administrador, 27/10/2026): el catálogo y la estructura institucional no se
-- auditaban, así que crear, editar o desactivar una asignatura no dejaba rastro ([[RF05]], RNF02).
-- Mismo patrón que 12_audit_enrollment_deadlines.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).
-- subjects y careers llevan department_id, así que fn_audit_department las ubica en su departamento (RLS, ADR-029);
-- departments, study_plans, subject_prerequisites, career_subjects y classrooms quedan sin departamento: solo
-- las ve el Administrador.

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'departments', 'careers', 'study_plans', 'subjects', 'subject_prerequisites', 'career_subjects', 'classrooms'
    ] LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I', t || '_audit', t);
        EXECUTE format('CREATE TRIGGER %I AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_audit_row()', t || '_audit', t);
    END LOOP;
END;
$$;
