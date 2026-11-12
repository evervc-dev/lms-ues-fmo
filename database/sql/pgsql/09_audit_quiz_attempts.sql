-- RF16 / ADR-042: auditoría de los intentos de cuestionario (minutos extra y cierres incluidos).
-- Mismo patrón que 07_audit_permissions.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS quiz_attempts_audit ON quiz_attempts;
CREATE TRIGGER quiz_attempts_audit AFTER INSERT OR UPDATE OR DELETE ON quiz_attempts
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
