-- Solo si ya habías importado la base antes de que existiera el tramo RSH.
ALTER TABLE postulaciones
  ADD COLUMN tramo_rsh TINYINT UNSIGNED NULL AFTER tiene_ahorro,
  ADD COLUMN rsh_fecha DATE NULL AFTER tramo_rsh;
