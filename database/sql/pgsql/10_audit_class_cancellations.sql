-- RF32: auditoría de las cancelaciones de clase y los cambios de modalidad, con su motivo (app.reason).
-- Mismo patrón que 09_audit_quiz_attempts.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS class_cancellations_audit ON class_cancellations;
CREATE TRIGGER class_cancellations_audit AFTER INSERT OR UPDATE OR DELETE ON class_cancellations
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
