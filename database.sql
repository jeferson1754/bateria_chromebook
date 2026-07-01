
CREATE TABLE IF NOT EXISTS `registros_bateria` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `porcentaje_actual` INT NOT NULL,
    `porcentaje_faltante` INT NOT NULL,
    `minutos_carga` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
