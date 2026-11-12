-- ADR-029: el Administrador ve toda la auditoría; el Jefe, solo su departamento.
-- La aplicación fija app.audit_scope = 'all' (Administrador) o app.department_ids = '3,7' (Jefe).
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY;

CREATE POLICY audit_logs_insert ON audit_logs FOR INSERT WITH CHECK (true);

CREATE POLICY audit_logs_select ON audit_logs FOR SELECT USING (
    COALESCE(current_setting('app.audit_scope', true), '') = 'all'
    OR department_id = ANY (string_to_array(NULLIF(current_setting('app.department_ids', true), ''), ',')::bigint[])
);
