-- Kjøres kun ved første oppstart av databasen
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- fritekstsøk (LIKE/ILIKE-indekser)
