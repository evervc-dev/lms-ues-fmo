-- Se ejecuta solo al crear el volumen de PostgreSQL por primera vez.
-- Base de pruebas (ADR-027: las suites Feature y Browser corren en PostgreSQL)
CREATE DATABASE lms_test OWNER lms;
-- Extensiones del modelo v2 (ADR-031)
\connect lms
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS btree_gist;
\connect lms_test
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS btree_gist;
