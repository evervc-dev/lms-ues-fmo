-- H-03 (informe de QA del rol Administrador, caso P-04): dos períodos académicos no pueden cruzar sus fechas,
-- sea cual sea su estado. Se usa daterange(start_date, end_date), que es semiabierto «[inicio, fin)»: así el
-- período que empieza justo el día en que termina el anterior comparte el borde sin solaparse de verdad.
-- Necesita btree_gist (00_extensions.sql). Idempotente: DROP CONSTRAINT IF EXISTS antes de crearla.
--
-- Un EXCLUDE no admite NOT VALID: si ya hay períodos cruzados (staging los tenía, creados en la misma prueba de
-- QA que encontró el defecto), crearlo falla con 23P01 y tumba el despliegue. En ese caso no se crea, se avisa
-- con los pares que chocan y la regla queda solo en PeriodoService::crear(). Corregidas esas fechas, se vuelve a
-- correr este archivo: Adaptive::pgsqlFile('13_period_no_overlap.sql').

DO $$
DECLARE
    v_choques text;
BEGIN
    ALTER TABLE academic_periods DROP CONSTRAINT IF EXISTS academic_periods_no_overlap;

    SELECT string_agg(format('%s (%s – %s) con %s (%s – %s)', a.name, a.start_date, a.end_date, b.name, b.start_date, b.end_date), '; ')
    INTO v_choques
    FROM academic_periods a
    JOIN academic_periods b ON a.id < b.id AND daterange(a.start_date, a.end_date) && daterange(b.start_date, b.end_date);

    IF v_choques IS NOT NULL THEN
        RAISE WARNING 'academic_periods_no_overlap no se creó: hay períodos que se cruzan: %', v_choques;
        RETURN;
    END IF;

    ALTER TABLE academic_periods
        ADD CONSTRAINT academic_periods_no_overlap
        EXCLUDE USING gist (daterange(start_date, end_date) WITH &&);
END;
$$;
