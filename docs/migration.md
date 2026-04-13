# Estrategia de Migración de Catálogo: Jumpseller -> WooCommerce

Este documento detalla el proceso técnico para la transferencia masiva y sincronizada de productos entre plataformas utilizando sus respectivas REST APIs.

## 1. Arquitectura del Proceso (ETL)

La migración sigue un modelo de **Extract, Transform, Load**:

### A. Extracción (Jumpseller API)

Se utiliza la API V1 de Jumpseller. El proceso debe ser iterativo para evitar desbordamientos de memoria.

- **Endpoint:** `GET /v1/products.json?limit=100&page={n}`
- **Parámetros Críticos:** `status: 'all'` (para incluir productos ocultos) y `count` para calcular el total de iteraciones.
- **Autenticación:** Basic Auth (API Key + Secret).

### B. Transformación (Mapping Logic)

Es el núcleo de la migración. Se debe mapear el objeto JSON de Jumpseller al esquema de WooCommerce. 

- **Mapeo de Variantes:** Jumpseller maneja variantes de forma plana; WooCommerce requiere la creación del producto "Variable" y luego sus "Variations" vinculadas por el `parent_id`.
- **Atributos:** Conversión de opciones (Talla, Color) en Atributos de producto global o local.

### C. Inyección (WooCommerce API)

Uso de la librería `automattic/woocommerce-rest-api`.

- **Batch Update:** Para optimizar, se deben enviar paquetes de hasta 100 productos por petición usando el endpoint `/wp-json/wc/v3/products/batch`.
- **Sideloading de Imágenes:** No es necesario descargar la imagen manualmente. Al enviar el array de URLs en el campo `images`, el Core de WordPress ejecuta un `media_sideload_image()` de forma interna.

## 2. Flujo de Control de Imágenes

Para evitar duplicidad y errores de timeout:

1. Validar que la URL de origen sea accesible.
2. WooCommerce descargará la imagen, la procesará (thumbnails) y la asignará a la librería de medios.
3. **Optimización SRE:** Se recomienda ejecutar este proceso mediante **Jobs/Colas** (por ejemplo, usando un sistema de colas en base de datos o Redis) para manejar reintentos en caso de fallas de red.

## 3. Consideraciones de SKU y Sincronía

El **SKU** actúa como la "Unique Key" entre sistemas. Antes de cada inyección, el script debe verificar si el SKU ya existe en WooCommerce para decidir entre un `POST` (crear) o un `PUT` (actualizar).