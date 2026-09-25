-- Comité de Vivienda Olas del Horizonte — esquema
SET NAMES utf8mb4;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rut VARCHAR(12) NULL UNIQUE,            -- normalizado: 12345678-9
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  telefono VARCHAR(30) NULL,
  direccion VARCHAR(200) NULL,
  rol ENUM('admin','miembro') NOT NULL DEFAULT 'miembro',
  cargo VARCHAR(40) NULL,                 -- cargo en la directiva, si tiene
  fecha_ingreso DATE NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_baja DATE NULL,                   -- desde aquí dejan de contar cuotas (socio inactivo)
  password_hash VARCHAR(255) NULL,        -- NULL = aún no puede ingresar
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  fecha DATE NOT NULL,
  monto INT UNSIGNED NOT NULL,
  forma_pago VARCHAR(30) NULL,
  observacion VARCHAR(255) NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  concepto VARCHAR(200) NOT NULL,
  monto INT UNSIGNED NOT NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reuniones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  lugar VARCHAR(150) NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencias (
  reunion_id INT NOT NULL,
  usuario_id INT NOT NULL,
  estado ENUM('presente','representante','justificado') NOT NULL DEFAULT 'presente',  -- sin fila = ausente
  PRIMARY KEY (reunion_id, usuario_id),
  FOREIGN KEY (reunion_id) REFERENCES reuniones(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria ENUM('acta','asistencia','tesoreria','otro') NOT NULL DEFAULT 'acta',
  titulo VARCHAR(200) NOT NULL,
  fecha DATE NOT NULL,
  archivo VARCHAR(100) NOT NULL,          -- nombre interno en /uploads
  nombre_original VARCHAR(200) NOT NULL,
  solo_admin TINYINT(1) NOT NULL DEFAULT 0,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comunicados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  publico TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = se muestra en el sitio público
  fecha DATE NOT NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE postulaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL UNIQUE,             -- si es la ficha de un socio
  nombre VARCHAR(150) NOT NULL,
  rut VARCHAR(20) NULL,                   -- RUT normalizado o N° de pasaporte
  direccion VARCHAR(200) NULL,
  nacionalidad ENUM('chileno','extranjero') NULL,
  estado_civil VARCHAR(30) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  formato ENUM('familiar','unipersonal') NULL,
  causal VARCHAR(30) NULL,                -- solo postulación unipersonal
  tiene_ahorro TINYINT(1) NOT NULL DEFAULT 0,  -- declara tener cuenta de ahorro vivienda (buena fe)
  doc_cedula VARCHAR(100) NULL,           -- archivos en /uploads (solo admin)
  doc_rsh VARCHAR(100) NULL,
  doc_serviu VARCHAR(100) NULL,           -- declaraciones SERVIU (solo unipersonal)
  mensaje TEXT NULL,
  estado ENUM('nueva','contactada','aceptada','rechazada') NOT NULL DEFAULT 'nueva',
  enviada DATETIME NULL,                  -- ficha de socio enviada a la directiva
  actualizado DATETIME NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ajustes (
  clave VARCHAR(50) PRIMARY KEY,
  valor TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- límite de intentos (login, formulario público)
CREATE TABLE intentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(80) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (clave, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
