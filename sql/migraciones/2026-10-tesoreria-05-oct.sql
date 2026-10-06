-- Tesorería al 05-10-2026 (planilla): agrega los pagos y gastos posteriores al 29-04-2026.
-- Para bases que ya tienen los datos de 2-datos.sql. Ejecutar UNA sola vez (no es re-ejecutable).
-- Resultado esperado: recaudado $661.900 (producción ya tenía el dominio de $10.000 registrado: este script NO lo inserta), gastos $198.740, en banco $463.160.
SET NAMES utf8mb4;

INSERT INTO pagos (usuario_id, fecha, monto, forma_pago, observacion) VALUES
(3, '2026-04-30', 3000, NULL, NULL),
(4, '2026-04-30', 3000, NULL, NULL),
(3, '2026-05-15', 12000, NULL, NULL),
(15, '2026-07-02', 15000, NULL, NULL),
(10, '2026-09-22', 15000, 'Efectivo', NULL),
(8, '2026-09-22', 50000, 'Efectivo', NULL),
(17, '2026-09-22', 18000, 'Transferencia', NULL),
(21, '2026-09-22', 7900, NULL, 'Compra de insumos para la reunión (equivale al gasto del 22-09)'),
(16, '2026-10-05', 30000, NULL, 'Hosting anual del sitio web, pagado por Matías Lobos (equivale al gasto)');

INSERT INTO gastos (fecha, concepto, monto) VALUES
('2026-09-22', 'Agua, vasos, servilletas y papel higiénico', 7900),
('2026-10-05', 'Hosting anual del sitio web', 30000);

SELECT (SELECT SUM(monto) FROM pagos) AS recaudado, (SELECT SUM(monto) FROM gastos) AS gastos;
