-- ============================================================
-- CentralPIM - Tabla: migration_logs
-- Equivalente a la migración:
--   2024-01-02-000001_CreateMigrationLogs.php
-- Ejecutar en phpMyAdmin sobre la base de datos: central_pim
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `migration_logs` (
  `id`               INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tipo`             VARCHAR(50)         NOT NULL COMMENT 'Ej: jumpseller_to_woo',
  `sku`              VARCHAR(100)        NOT NULL DEFAULT '',
  `nombre_producto`  VARCHAR(200)        NOT NULL DEFAULT '',
  `accion`           VARCHAR(50)         NOT NULL COMMENT 'create, update, skip, upsert, migration_start, migration_end, page_processed',
  `estado`           VARCHAR(20)         NOT NULL COMMENT 'success, error, warning, info',
  `mensaje`          TEXT                NOT NULL,
  `created_at`       DATETIME            NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tipo_estado` (`tipo`, `estado`),
  KEY `idx_sku`         (`sku`),
  KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Registrar la migración en la tabla de control de CodeIgniter
-- para que `php spark migrate` no la intente ejecutar de nuevo.
--
-- IMPORTANTE: ejecuta este bloque solo si la tabla `migrations`
-- ya existe en tu base de datos (es decir, si alguna vez corriste
-- `php spark migrate` antes). Si no existe, omítelo.
-- ============================================================

-- ============================================================
-- Tabla: producto_tienda — nuevo campo external_id
-- Almacena el ID del producto en cada plataforma externa
-- (Ej: ID en Jumpseller, ID en WooCommerce) para poder
-- hacer cruces y sincronizaciones futuras sin depender del SKU.
-- ============================================================

ALTER TABLE `producto_tienda`
  ADD COLUMN `external_id` VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'ID del producto en la plataforma externa (Jumpseller, WooCommerce, etc.)'
  AFTER `stock_especifico`;

-- ============================================================
-- Registrar la migración en la tabla de control de CodeIgniter
-- para que `php spark migrate` no la intente ejecutar de nuevo.
--
-- IMPORTANTE: ejecuta este bloque solo si la tabla `migrations`
-- ya existe en tu base de datos (es decir, si alguna vez corriste
-- `php spark migrate` antes). Si no existe, omítelo.
-- ============================================================

INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT
  '2024-01-02-000001',
  'App\\Database\\Migrations\\CreateMigrationLogs',
  'default',
  'App',
  UNIX_TIMESTAMP(),
  COALESCE((SELECT MAX(batch) FROM migrations m2), 0) + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `class` = 'App\\Database\\Migrations\\CreateMigrationLogs'
);

-- ============================================================
-- Tabla: productos — nuevo campo sku
-- SKU universal del producto, único a nivel de sistema.
-- Equivalente a la migración:
--   2024-01-03-000001_AddSkuToProductos.php
-- ============================================================

ALTER TABLE `productos`
  ADD COLUMN `sku` VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'SKU único del producto, transversal a todas las plataformas'
  AFTER `id`,
  ADD UNIQUE KEY `sku_unique` (`sku`);

-- ============================================================
-- Registrar la migración en la tabla de control de CodeIgniter
-- ============================================================

INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT
  '2024-01-03-000001',
  'App\\Database\\Migrations\\AddSkuToProductos',
  'default',
  'App',
  UNIX_TIMESTAMP(),
  COALESCE((SELECT MAX(batch) FROM migrations m2), 0) + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `class` = 'App\\Database\\Migrations\\AddSkuToProductos'
);

-- ============================================================
-- Tabla: productos — nuevo campo stock_ilimitado
-- Indica si el producto tiene stock sin límite (infinito).
-- Cuando es 1, no se hace seguimiento de stock_general y en
-- WooCommerce se marca con existencias sin rastreo de stock.
-- Equivalente a la migración:
--   2024-01-04-000001_AddStockIlimitadoToProductos.php
-- ============================================================

ALTER TABLE `productos`
  ADD COLUMN `stock_ilimitado` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Stock ilimitado (sin seguimiento). 1 = infinito, 0 = rastreado'
  AFTER `stock_general`;

-- ============================================================
-- Registrar la migración en la tabla de control de CodeIgniter
-- ============================================================

INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT
  '2024-01-04-000001',
  'App\\Database\\Migrations\\AddStockIlimitadoToProductos',
  'default',
  'App',
  UNIX_TIMESTAMP(),
  COALESCE((SELECT MAX(batch) FROM migrations m2), 0) + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `class` = 'App\\Database\\Migrations\\AddStockIlimitadoToProductos'
);

-- ============================================================
-- Tabla: tiendas — nuevo campo plataforma
-- Indica la plataforma de e-commerce a la que pertenece la
-- tienda (woocommerce, jumpseller, otro).
-- El listado de opciones válidas se gestiona en:
--   app/Config/platforms.json
-- Equivalente a la migración:
--   2024-01-05-000001_AddPlataformaToTiendas.php
-- ============================================================

ALTER TABLE `tiendas`
  ADD COLUMN `plataforma` VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Plataforma de la tienda (woocommerce, jumpseller, otro)'
  AFTER `nombre`;

-- ============================================================
-- Registrar la migración en la tabla de control de CodeIgniter
-- ============================================================

INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT
  '2024-01-05-000001',
  'App\\Database\\Migrations\\AddPlataformaToTiendas',
  'default',
  'App',
  UNIX_TIMESTAMP(),
  COALESCE((SELECT MAX(batch) FROM migrations m2), 0) + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `class` = 'App\\Database\\Migrations\\AddPlataformaToTiendas'
);

-- ============================================================
-- Tabla: scraper_runs
-- Registra cada ejecución de scraping por tienda: estado,
-- totales y timestamps. Una fila por ejecución.
-- Equivalente a la migración:
--   2024-02-01-000001_CreateScraperRuns.php
-- ============================================================

CREATE TABLE IF NOT EXISTS `scraper_runs` (
  `id`                INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tienda_id`         INT(10) UNSIGNED    NOT NULL,
  `estado`            ENUM('en_progreso','completado','error')
                                          NOT NULL DEFAULT 'en_progreso',
  `total_encontrados` INT(10) UNSIGNED    NOT NULL DEFAULT 0,
  `total_nuevos`      INT(10) UNSIGNED    NOT NULL DEFAULT 0,
  `total_cambios`     INT(10) UNSIGNED    NOT NULL DEFAULT 0
                        COMMENT 'Productos con precio distinto al run anterior',
  `mensaje_error`     TEXT                    NULL DEFAULT NULL,
  `iniciado_en`       DATETIME            NOT NULL,
  `finalizado_en`     DATETIME                NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tienda_id` (`tienda_id`),
  CONSTRAINT `fk_scraper_runs_tienda`
    FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Tabla: scraper_productos
-- Snapshot de productos obtenidos en cada run. Vinculable al
-- catálogo interno mediante producto_id (nullable).
-- Equivalente a la migración:
--   2024-02-01-000002_CreateScraperProductos.php
-- ============================================================

CREATE TABLE IF NOT EXISTS `scraper_productos` (
  `id`            INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `run_id`        INT(10) UNSIGNED    NOT NULL,
  `tienda_id`     INT(10) UNSIGNED    NOT NULL,
  `external_ref`  VARCHAR(100)            NULL DEFAULT NULL
                    COMMENT 'ID o slug del producto en la plataforma origen',
  `ean`           VARCHAR(13)             NULL DEFAULT NULL,
  `sku`           VARCHAR(100)            NULL DEFAULT NULL,
  `nombre`        VARCHAR(300)        NOT NULL,
  `url`           VARCHAR(500)            NULL DEFAULT NULL,
  `precio_normal` INT(10) UNSIGNED        NULL DEFAULT NULL
                    COMMENT 'CLP entero sin decimales',
  `precio_oferta` INT(10) UNSIGNED        NULL DEFAULT NULL
                    COMMENT 'CLP entero sin decimales',
  `disponible`    TINYINT(1)          NOT NULL DEFAULT 1,
  `producto_id`   INT(10) UNSIGNED        NULL DEFAULT NULL
                    COMMENT 'FK al catálogo interno, se llena al vincular',
  `created_at`    DATETIME            NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_run_id`      (`run_id`),
  KEY `idx_tienda_id`   (`tienda_id`),
  KEY `idx_producto_id` (`producto_id`),
  KEY `idx_ean`         (`ean`),
  KEY `idx_sku`         (`sku`),
  CONSTRAINT `fk_scraper_productos_run`
    FOREIGN KEY (`run_id`)      REFERENCES `scraper_runs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scraper_productos_tienda`
    FOREIGN KEY (`tienda_id`)   REFERENCES `tiendas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scraper_productos_producto`
    FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
    ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed: tiendas scraper
-- Equivalente a: php spark db:seed ScraperTiendasSeeder
-- Inserta las 3 tiendas de monitoreo solo si no existen.
-- ============================================================

INSERT INTO `tiendas` (`nombre`, `plataforma`, `url_api`, `token_auth`)
SELECT 'Dronestore Chile', 'dronestore_scraper', 'https://dronestore.cl', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `tiendas` WHERE `plataforma` = 'dronestore_scraper'
);

INSERT INTO `tiendas` (`nombre`, `plataforma`, `url_api`, `token_auth`)
SELECT 'GoPro Chile', 'gopro_scraper', 'https://www.gopro.cl', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `tiendas` WHERE `plataforma` = 'gopro_scraper'
);

INSERT INTO `tiendas` (`nombre`, `plataforma`, `url_api`, `token_auth`)
SELECT 'Sony Store Chile', 'sony_scraper', 'https://store.sony.cl', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `tiendas` WHERE `plataforma` = 'sony_scraper'
);
