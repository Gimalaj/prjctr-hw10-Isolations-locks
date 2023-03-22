-- Adminer 4.8.1 PostgreSQL 15.2 (Debian 15.2-1.pgdg110+1) dump

\connect "prjctr";

CREATE TABLE "public"."users" (
    "id" integer NOT NULL,
    "name" character(50) NOT NULL,
    "age" integer NOT NULL,
    CONSTRAINT "users_id_idx" UNIQUE ("id")
) WITH (oids = false);


-- 2023-03-20 18:27:37.267338+00