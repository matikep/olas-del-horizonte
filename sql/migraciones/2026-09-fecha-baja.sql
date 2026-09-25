-- Solo si ya habías importado la base antes de que existiera la fecha de baja.
ALTER TABLE usuarios ADD COLUMN fecha_baja DATE NULL AFTER activo;
