-- Solo si ya habías importado 1-schema.sql ANTES de que existiera el formulario de postulación completo.
-- En una instalación nueva no hace falta (1-schema.sql ya incluye estos campos).
ALTER TABLE postulaciones
  MODIFY rut VARCHAR(20) NULL,
  ADD COLUMN direccion VARCHAR(200) NULL AFTER rut,
  ADD COLUMN nacionalidad ENUM('chileno','extranjero') NULL AFTER direccion,
  ADD COLUMN estado_civil VARCHAR(30) NULL AFTER nacionalidad,
  ADD COLUMN formato ENUM('familiar','unipersonal') NULL AFTER email,
  ADD COLUMN causal VARCHAR(30) NULL AFTER formato,
  ADD COLUMN doc_cedula VARCHAR(100) NULL AFTER causal,
  ADD COLUMN doc_rsh VARCHAR(100) NULL AFTER doc_cedula,
  ADD COLUMN tiene_ahorro TINYINT(1) NOT NULL DEFAULT 0 AFTER causal,
  ADD COLUMN doc_serviu VARCHAR(100) NULL AFTER doc_rsh,
  ADD COLUMN usuario_id INT NULL UNIQUE AFTER id,
  ADD COLUMN enviada DATETIME NULL AFTER estado,
  ADD COLUMN actualizado DATETIME NULL AFTER enviada,
  ADD FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;
