# Desarrollo local — central-pim

## Requisitos

- PHP 8.1+ (probado con 8.5)
- MySQL 8 o MariaDB 10.6+ corriendo en `localhost:3306`
- Composer

---

## Primera vez

### 1. Base de datos

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS central_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Luego importar los archivos SQL en orden (phpMyAdmin o CLI):

```bash
mysql -u root central_pim < database.sql    # schema inicial
mysql -u root central_pim < migration.sql   # alteraciones y tablas nuevas
```

### 2. Dependencias

```bash
composer install --ignore-platform-reqs
```

> `--ignore-platform-reqs` es necesario porque `laminas-escaper` (dependencia de CI4) declara soporte hasta PHP ~8.4, aunque funciona en 8.5.

### 3. Levantar el servidor

```bash
php spark serve
# http://localhost:8080
```

### 4. Seeds iniciales

```bash
php spark db:seed InitialSeeder          # usuario admin + datos de ejemplo
php spark db:seed ScraperTiendasSeeder   # tiendas de monitoreo (Dronestore, GoPro, Sony)
```

**Credenciales admin:**
- Email: `admin@centralpim.com`
- Contraseña: `admin123`

---

## Día a día

```bash
php spark serve          # levantar servidor
vendor/bin/phpunit       # correr todos los tests
vendor/bin/phpunit tests/SomeTest.php   # test específico
```

---

## Configuración (.env)

El archivo `.env` ya tiene valores funcionales para desarrollo. Los únicos que podrías necesitar cambiar:

| Variable | Por qué cambiarla |
|---|---|
| `database.default.hostname` | Cambiar a `127.0.0.1` si MySQL falla por socket en macOS |
| `database.default.password` | Si tu MySQL local tiene contraseña |
| `jumpseller.*` | Para probar sync con Jumpseller real |
| `woocommerce.*` | Para probar sync con WooCommerce real |

---

## Problema frecuente: MySQL en macOS (Homebrew)

Cuando `hostname = localhost`, PHP intenta conectar por socket Unix y falla con `No such file or directory`. Solución:

```
# .env
database.default.hostname = 127.0.0.1
```

---

## Agregar una nueva migración

1. Crear el archivo PHP en `app/Database/Migrations/` siguiendo el patrón de fecha: `YYYY-MM-DD-NNNNNN_NombreDescriptivo.php`
2. Añadir el SQL equivalente al final de `migration.sql` (para aplicar vía phpMyAdmin sin usar `spark migrate`)

El bloque en `migration.sql` debe incluir:
- El `CREATE TABLE` o `ALTER TABLE`
- Un `INSERT INTO migrations ... WHERE NOT EXISTS` para que `spark migrate` no lo re-ejecute si algún día se conecta

Ver los bloques existentes en `migration.sql` como referencia de formato.

---

## Módulo Scrapers

Las tiendas de monitoreo (Dronestore, GoPro, Sony) se configuran en `app/Config/Scrapers.php`. Para agregar una categoría nueva a cualquier tienda, añadir una entrada al array `categorias` de esa tienda — sin tocar modelos ni adapters.

Para ejecutar un scraping manualmente ir a `/scrapers` en el navegador.
