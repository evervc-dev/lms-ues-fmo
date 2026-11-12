-- RN-014 / RF08: un aula no puede tener dos secciones del mismo período en horarios que se solapan.
-- (Los choques de docente y del estudiante cruzan tablas: se validan en el Service.)
ALTER TABLE section_schedules
    ADD CONSTRAINT section_schedules_classroom_no_overlap
    EXCLUDE USING gist (
        period_id WITH =,
        classroom_id WITH =,
        weekday WITH =,
        tsrange(DATE '2000-01-01' + start_time, DATE '2000-01-01' + end_time) WITH &&
    ) WHERE (classroom_id IS NOT NULL);
