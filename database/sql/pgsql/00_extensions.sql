-- ADR-031: correos y códigos sin distinguir mayúsculas; EXCLUDE con igualdad sobre enteros.
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS btree_gist;
