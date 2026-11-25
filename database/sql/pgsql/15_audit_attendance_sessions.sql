-- RF19 / ADR-075: auditoría de las sesiones de asistencia. La apertura fuera del horario lleva su motivo (app.reason).
-- Mismo patrón que 10_audit_class_cancellations.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS attendance_sessions_audit ON attendance_sessions;
CREATE TRIGGER attendance_sessions_audit AFTER INSERT OR UPDATE OR DELETE ON attendance_sessions
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
