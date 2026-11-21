-- RF19: auditoría de las justificaciones de inasistencia (solicitud, aceptación y rechazo).
-- Mismo patrón que 15_audit_attendance_sessions.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS attendance_justifications_audit ON attendance_justifications;
CREATE TRIGGER attendance_justifications_audit AFTER INSERT OR UPDATE OR DELETE ON attendance_justifications
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
