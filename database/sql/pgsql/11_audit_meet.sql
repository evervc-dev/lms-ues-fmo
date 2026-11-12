-- RF21: auditoría de las clases virtuales (programación, estado y grabación) y de los reportes de Meet
-- (subida, confirmación y reemplazo, ADR-003). Mismo patrón que 10_audit_class_cancellations.sql;
-- idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS video_sessions_audit ON video_sessions;
CREATE TRIGGER video_sessions_audit AFTER INSERT OR UPDATE OR DELETE ON video_sessions
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();

DROP TRIGGER IF EXISTS meet_reports_audit ON meet_reports;
CREATE TRIGGER meet_reports_audit AFTER INSERT OR UPDATE OR DELETE ON meet_reports
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
