-- ADR-037 / RF04: auditoría de los permisos directos por usuario (p. ej. usuarios.crear-docente).
-- Mismo patrón que 06_audit_assignments.sql; idempotente (DROP TRIGGER IF EXISTS antes de crear).

DROP TRIGGER IF EXISTS model_has_permissions_audit ON model_has_permissions;
CREATE TRIGGER model_has_permissions_audit AFTER INSERT OR UPDATE OR DELETE ON model_has_permissions
    FOR EACH ROW EXECUTE FUNCTION fn_audit_row();
