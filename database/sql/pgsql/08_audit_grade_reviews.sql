-- RF35 / ADR-040: auditoría de las solicitudes de revisión de calificaciones y de las prórrogas.
-- Mismo patrón que 06_audit_assignments.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY['grade_review_requests', 'grade_review_items', 'assessment_extensions'] LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I', t || '_audit', t);
        EXECUTE format('CREATE TRIGGER %I AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_audit_row()', t || '_audit', t);
    END LOOP;
END;
$$;
