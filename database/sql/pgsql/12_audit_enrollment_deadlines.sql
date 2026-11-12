-- RF29 (ADR-051): auditoría de la fecha límite de inscripción por departamento (y su prórroga) y de las
-- prórrogas por estudiante. Mismo patrón que 11_audit_meet.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).
-- enrollment_deadlines lleva department_id, así que fn_audit_department la ubica en su departamento (RLS, ADR-029).

DROP TRIGGER IF EXISTS enrollment_deadlines_audit ON enrollment_deadlines;
CREATE TRIGGER enrollment_deadlines_audit AFTER INSERT OR UPDATE OR DELETE ON enrollment_deadlines
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();

DROP TRIGGER IF EXISTS enrollment_extensions_audit ON enrollment_extensions;
CREATE TRIGGER enrollment_extensions_audit AFTER INSERT OR UPDATE OR DELETE ON enrollment_extensions
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
