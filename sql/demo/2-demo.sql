-- DATOS DE DEMOSTRACIÓN (todo ficticio). Acceso: admin RUT 11.111.111-1 / miembro RUT 22.222.222-2, clave demo1234
SET NAMES utf8mb4;

INSERT INTO usuarios (id, rut, nombre, telefono, cargo, fecha_ingreso, rol, password_hash, activo, fecha_baja) VALUES
(1, '11111111-1', 'Administrador Demo', '+56911111111', 'Presidente/a', '2025-02-22', 'admin', '$2y$10$079quYNdXFw0MYeLf/by6eP48o8T200d.v7/PhJlFz6UnWacY1ame', 1, NULL),
(2, '22222222-2', 'Socia Demo', '+56922222222', 'Tesorero/a', '2025-02-22', 'miembro', '$2y$10$079quYNdXFw0MYeLf/by6eP48o8T200d.v7/PhJlFz6UnWacY1ame', 1, NULL),
(3, '15234871-1', 'Camila Rojas Fuentes', '+56930001001', 'Secretario/a', '2025-02-22', 'miembro', NULL, 1, NULL),
(4, '12876543-3', 'Jorge Muñoz Soto', '+56930001002', '1er Director', '2025-02-22', 'miembro', NULL, 1, NULL),
(5, '18765432-7', 'Valentina Díaz Araya', '+56930001003', '2do Director', '2025-02-22', 'miembro', NULL, 1, NULL),
(6, '10987654-2', 'Pedro Castillo Vera', '+56930001004', '3er Director', '2025-02-22', 'miembro', NULL, 1, NULL),
(7, '19345678-2', 'Fernanda Torres Pizarro', '+56930001005', NULL, '2025-02-22', 'miembro', NULL, 1, NULL),
(8, '8765432-K', 'Luis Contreras Molina', '+56930001006', NULL, '2025-02-22', 'miembro', NULL, 1, NULL),
(9, '17654321-3', 'Daniela Reyes Castro', '+56930001007', NULL, '2025-02-22', 'miembro', NULL, 1, NULL),
(10, '16543210-K', 'Andrés Silva Gómez', '+56930001008', NULL, '2025-02-22', 'miembro', NULL, 0, '2025-12-15'),
(11, '7654321-6', 'Rosa Herrera Vargas', '+56930001009', NULL, '2026-03-06', 'miembro', NULL, 1, NULL),
(12, '20123456-5', 'Matías Fernández Lagos', '+56930001010', NULL, '2026-03-06', 'miembro', NULL, 1, NULL);

INSERT INTO pagos (usuario_id, fecha, monto, forma_pago, observacion) VALUES
(1, '2025-03-05', 36000, 'Transferencia', 'marzo 2025 a febrero 2026'),
(2, '2025-03-10', 57000, 'Transferencia', 'al día a sep 2026'),
(3, '2025-04-02', 30000, 'Efectivo', NULL),
(4, '2025-05-15', 15000, 'Transferencia', NULL),
(5, '2025-06-01', 42000, 'Transferencia', NULL),
(7, '2025-09-11', 12000, 'Efectivo', NULL),
(9, '2026-01-20', 30000, 'Transferencia', 'marzo a diciembre 2025'),
(10, '2025-08-01', 18000, 'Efectivo', NULL),
(11, '2026-04-10', 21000, 'Transferencia', NULL);

INSERT INTO gastos (fecha, concepto, monto) VALUES
('2025-02-14', 'Libro de actas y registro de socios', 12000),
('2025-02-24', 'Arriendo de sede', 5000),
('2025-09-13', 'Fotocopias', 8500),
('2025-12-05', 'Convivencia de fin de año', 45000);

INSERT INTO reuniones (id, fecha, titulo, lugar) VALUES
(1, '2025-02-24', 'Asamblea de constitución', 'Sede vecinal'),
(2, '2025-09-11', 'Asamblea extraordinaria', 'Sede vecinal'),
(3, '2026-03-06', 'Reunión mensual', 'Sede vecinal'),
(4, '2026-04-29', 'Reunión mensual (online)', 'Google Meet');

-- reunión 4 sin lista de asistencia (no cuenta como ausencia)
INSERT INTO asistencias (reunion_id, usuario_id, estado) VALUES
(1, 1, 'presente'),
(1, 2, 'presente'),
(1, 3, 'presente'),
(1, 4, 'presente'),
(1, 5, 'presente'),
(1, 6, 'presente'),
(1, 7, 'presente'),
(1, 8, 'presente'),
(1, 9, 'presente'),
(1, 10, 'presente'),
(2, 1, 'presente'),
(2, 2, 'presente'),
(2, 3, 'presente'),
(2, 4, 'presente'),
(2, 5, 'presente'),
(2, 7, 'presente'),
(2, 9, 'presente'),
(2, 6, 'justificado'),
(2, 8, 'representante'),
(3, 1, 'presente'),
(3, 2, 'presente'),
(3, 3, 'presente'),
(3, 5, 'presente'),
(3, 11, 'presente'),
(3, 12, 'presente'),
(3, 4, 'representante'),
(3, 6, 'justificado'),
(3, 7, 'justificado');

INSERT INTO ajustes (clave, valor) VALUES
('cuota_mensual', '3000'),
('inicio_cobro', '2025-03-01'),
('hero_eyebrow', 'Comité de Vivienda · Iquique'),
('hero_titulo', 'Organizados por una vivienda digna'),
('hero_bajada', 'Somos un comité de vivienda formado por familias de Iquique. Trabajamos en conjunto, con asambleas abiertas y cuentas claras.'),
('historia_titulo', 'De un grupo de WhatsApp a una organización con personalidad jurídica'),
('historia_intro', 'Todo comenzó en marzo de 2024 con un grupo de WhatsApp y la idea de reunir a familias que compartían una misma necesidad. Paso a paso, con actas, cuotas y constancia, fuimos formalizando el comité.'),
('hitos', 'Feb 2024 | La idea | Nace la propuesta de formar un comité para postular al subsidio habitacional en el sector de Punta Gruesa, con la meta de reunir a 50 familias, sin fines de lucro para quienes lo organizan.
Mar 2024 | Primer grupo de WhatsApp | Alrededor del 10 de marzo creamos el primer grupo de WhatsApp y comenzamos a contactar a familias interesadas.
May 2024 | Primer listado | Con recomendaciones entre los mismos participantes reunimos a las primeras 24 familias interesadas.
Sep 2024 | 40 familias | El listado de postulantes llegó a 40 familias y comenzamos a reunir los Registros Sociales de Hogares.
Feb 2025 | Primera reunión con la municipalidad | El 5 de febrero nos reunimos con la municipalidad para iniciar la formalización del comité.
Feb 2025 | Primera reunión | Un grupo de vecinas y vecinos nos reunimos por primera vez de forma online, elegimos una directiva provisoria y acordamos un aporte mensual para sostener el trabajo.
Feb 2025 | Constitución del comité | Realizamos la asamblea de constitución ante ministro de fe municipal. Fue nuestro primer paso formal, y dos días después ingresamos los documentos a la municipalidad.
Mar 2025 | Comienzan los aportes | Iniciamos las cuotas para sede, libro de actas y registro de socios, con cuentas abiertas a toda la asamblea.
May 2025 | Conocimos el plan maestro | En asamblea extraordinaria, la Oficina de Vivienda municipal nos presentó el plan maestro del sector Punta Gruesa.
Sep 2025 | Nace Olas del Horizonte | Tras dos rechazos del nombre anterior, en asamblea extraordinaria aprobamos por unanimidad el nombre definitivo del comité.
Sep 2025 | Personalidad jurídica | Obtuvimos nuestra personalidad jurídica como organización funcional sin fines de lucro.
Nov 2025 | Registro Civil | Nuestro directorio queda inscrito en el Registro Nacional de Personas Jurídicas sin Fines de Lucro.
Dic 2025 | Celebramos | Celebramos en asamblea la personalidad jurídica y confirmamos la directiva.
Mar 2026 | Reuniones mensuales | Acordamos reunirnos el primer viernes de cada mes y se sumaron nuevas familias al comité.
Sep 2026 | Nueva etapa | En reunión general con la municipalidad conocimos imágenes del sector donde se proyectan las viviendas y se sumaron nuevas familias.'),
('quienes_titulo', 'Un comité que se organiza en comunidad'),
('quienes_texto', 'Olas del Horizonte es un comité de vivienda constituido como organización funcional sin fines de lucro en Iquique, Región de Tarapacá.

Nos reunimos en asamblea para tomar decisiones en conjunto, mantener al día nuestra documentación y trabajar organizadamente frente a los programas habitacionales.'),
('valores', 'Transparencia | Cada acta, cuota y gasto queda a disposición de todos los socios.
Decisiones en asamblea | Lo importante se conversa y se vota entre todos.
Compromiso | Asistencia, participación y aportes al día sostienen el trabajo común.'),
('unete_titulo', 'Súmate al comité'),
('unete_texto', 'Si quieres participar y trabajar junto a otras familias organizadas, déjanos tus datos. Te contaremos cómo funcionamos y te invitaremos a la próxima asamblea.'),
('contacto_email', 'contacto@ejemplo.cl'),
('contacto_telefono', ''),
('datos_pago', 'Banco Ejemplo · Cuenta Corriente N° 00000000
Titular: Tesorería Demo · RUT 12.345.678-5
Envía el comprobante a tesoreria@ejemplo.cl');

INSERT INTO comunicados (titulo, cuerpo, publico, fecha) VALUES
('Bienvenidos al sitio del comité', 'Aquí publicamos avisos para la comunidad. Los socios pueden ingresar con su RUT para ver sus cuotas, asistencia y actas.', 1, '2026-09-01'),
('Reunión mensual', 'Recuerden que nos reunimos el primer viernes de cada mes a las 19:00 hrs. La asistencia es obligatoria; pueden enviar un representante o justificar (máximo 3 al año).', 0, '2026-09-10');

INSERT INTO postulaciones (nombre, telefono, email, mensaje) VALUES
('Persona Interesada Demo', '+56930009999', 'interesada@ejemplo.cl', 'Me gustaría participar en el comité.');
