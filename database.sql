-- ============================================================
-- CentralPIM - Script de Base de Datos
-- Ejecutar en phpMyAdmin o cualquier cliente MySQL/MariaDB
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabla: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: tiendas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiendas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(100) NOT NULL,
  `url_api`    VARCHAR(255) NOT NULL,
  `token_auth` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: proveedores
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(100) NOT NULL,
  `tiempo_encargo` INT NOT NULL DEFAULT 0,
  `contacto`       VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: marcas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marcas` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: categorias (jerarquía con parent_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `parent_id`   INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: productos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `productos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(200) NOT NULL,
  `marca_id`      INT UNSIGNED DEFAULT NULL,
  `precio`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_oferta` DECIMAL(10,2) DEFAULT NULL,
  `costo`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_general` INT NOT NULL DEFAULT 0,
  `proveedor_id`  INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marca_id`     (`marca_id`),
  KEY `idx_proveedor_id` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: producto_categoria (relación N:M)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `producto_categoria` (
  `producto_id`  INT UNSIGNED NOT NULL,
  `categoria_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`producto_id`, `categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: producto_tienda (relación N:M con datos extra)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `producto_tienda` (
  `producto_id`      INT UNSIGNED NOT NULL,
  `tienda_id`        INT UNSIGNED NOT NULL,
  `valor_especifico` DECIMAL(10,2) DEFAULT NULL,
  `valor_oferta_esp` DECIMAL(10,2) DEFAULT NULL,
  `stock_especifico` INT DEFAULT NULL,
  PRIMARY KEY (`producto_id`, `tienda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES (Seeds)
-- ============================================================

-- Usuario administrador
-- Contraseña: admin123
INSERT INTO `usuarios` (`nombre`, `email`, `password`) VALUES
('Administrador', 'admin@centralpim.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Marcas de ejemplo
INSERT INTO `marcas` (`nombre`) VALUES
('Samsung'),
('Apple'),
('Sony'),
('LG');

-- Proveedores de ejemplo
INSERT INTO `proveedores` (`nombre`, `tiempo_encargo`, `contacto`) VALUES
('Proveedor Central',    3, 'contacto@proveedor.com'),
('Distribuidora Norte',  7, '+1-555-0100'),
('Importadora Sur',     14, 'ventas@importadorasur.com');

-- Categorías de ejemplo (con jerarquía)
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `parent_id`) VALUES
(1, 'Electrónica',  'Productos electrónicos en general', NULL),
(2, 'Celulares',    'Teléfonos móviles y smartphones',   1),
(3, 'Laptops',      'Computadoras portátiles',           1),
(4, 'Audio',        'Audífonos, bocinas y accesorios',   1),
(5, 'Accesorios',   'Accesorios varios',                 NULL),
(6, 'Fundas',       'Fundas y protectores',              5),
(7, 'Cargadores',   'Cargadores y cables',               5);

-- Tiendas de ejemplo
INSERT INTO `tiendas` (`nombre`, `url_api`, `token_auth`) VALUES
('Tienda Principal',  'https://tienda1.ejemplo.com/api/sync', 'tok_abc123def456ghi789jkl012mno345pqr'),
('Tienda Online',     'https://tienda2.ejemplo.com/api/sync', 'tok_xyz987wvu654tsr321qpo098nml765kji');

SET FOREIGN_KEY_CHECKS = 1;
