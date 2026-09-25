-- Solo si ya habías importado la base antes de que existieran los estados de asistencia.
-- Lo registrado hasta ahora queda como "presente".
ALTER TABLE asistencias
  ADD COLUMN estado ENUM('presente','representante','justificado') NOT NULL DEFAULT 'presente' AFTER usuario_id;
