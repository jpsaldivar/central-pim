
# 📦 CentralPIM: Sistema de Gestión de Catálogo Multi-Tienda (MVP)

## 📝 Descripción General

CentralPIM (Product Information Management) es un sistema centralizado desarrollado en PHP (CodeIgniter 4) diseñado para administrar el catálogo de productos, inventario y proveedores. Su objetivo principal es servir como la "fuente de la verdad" del negocio, permitiendo gestionar los productos en un solo lugar y sincronizar automáticamente las actualizaciones (precios, stock, detalles) hacia múltiples plataformas de e-commerce conectadas.

## 🛠️ Stack Tecnológico Propuesto

- Backend: PHP 8.x con Framework CodeIgniter 4 (ligero, rápido y con ORM básico ideal para el MVP).
- Base de Datos: MySQL / MariaDB.
- Sincronización: Webhooks / Peticiones HTTP cURL (Guzzle) nativas para notificar a las tiendas.
- Frontend (Panel Admin): Bootstrap 5 o Tailwind CSS (para interfaces de administración limpias y responsivas sin mucha fricción).

## 🗄️ Modelo de Datos (Diagrama Lógico)

A continuación, se define la estructura de las tablas principales.

1. Tabla: usuarios

Gestiona el acceso administrativo al sistema.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(100) | Nombre completo del administrador | |
| email | VARCHAR(100) | Correo electrónico (login) | UNIQUE |
| password | VARCHAR(255) | Hash de la contraseña | |
| created_at | TIMESTAMP | Fecha de registro | |

2. Tabla: tiendas

Registra las plataformas e-commerce (nodos) que deben ser notificadas.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(100) | Nombre de la tienda (Ej: Tienda Principal) | |
| url_api | VARCHAR(255) | Endpoint para envío de actualizaciones | |
| token_auth | TEXT | Token para validación de seguridad | |

3. Tabla: proveedores

Empresas encargadas del suministro de productos.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(100) | Razón social o nombre comercial | |
| tiempo_encargo | INT | Días promedio que demora el pedido | |
| contacto | VARCHAR(100) | Información de contacto | |

4. Tabla: marcas

Catálogo de las marcas asociadas a los productos.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(100) | Nombre comercial de la marca | |

5. Tabla: categorias

Estructura jerárquica para la clasificación de productos.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(100) | Nombre de la categoría | |
| descripcion | TEXT | Detalles de la categoría | |
| parent_id | INT | ID de la categoría padre (soporta jerarquía) | FK (categorias.id) |

6. Tabla: productos

Entidad principal con la información maestra del catálogo.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| id | INT | Identificador único | PK |
| nombre | VARCHAR(200) | Nombre comercial del producto | |
| marca_id | INT | Vínculo con la marca | FK (marcas.id) |
| precio | DECIMAL(10,2) | Precio base general | |
| precio_oferta| DECIMAL(10,2) | Precio de oferta general | |
| costo | DECIMAL(10,2) | Costo de adquisición del producto | |
| stock_general| INT | Inventario total disponible | |
| proveedor_id | INT | Vínculo con el proveedor | FK (proveedores.id) |

7. Tabla: producto_categoria

Relación muchos a muchos entre productos y sus categorías.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| producto_id | INT | ID del producto | PK, FK (productos.id) |
| categoria_id | INT | ID de la categoría | PK, FK (categorias.id) |

8. Tabla: producto_tienda

Relación muchos a muchos que permite la personalización de datos por cada canal de venta.

| Campo | Tipo | Descripción | Llave |
| :--- | :--- | :--- | :--- |
| producto_id | INT | ID del producto | PK, FK (productos.id) |
| tienda_id | INT | ID de la tienda | PK, FK (tiendas.id) |
| valor_especifico| DECIMAL(10,2)| Precio diferenciado para esta tienda | Nullable |
| valor_oferta_esp| DECIMAL(10,2)| Precio oferta diferenciado para esta tienda | Nullable |
| stock_especifico| INT | Stock asignado a esta tienda | Nullable |

## 💡 Lógica de Negocio Clave: "Fallback" de Precios y Stock

A nivel de código (en el Modelo o Servicio de Producto), cuando se prepare el JSON para enviar a una tienda específica, la lógica debe evaluar la tabla producto_tienda:

Si precio_personalizado es NULL -> Usar productos.precio_base.

Si stock_personalizado es NULL -> Usar productos.stock_general.

Esto permite flexibilidad sin obligar a duplicar datos si las tiendas manejan las mismas condiciones.

## ⚙️ Funcionalidades Principales (CRUD & Lógica)

1. Gestión de Catálogo Base (CRUDs Estándar)

- Módulo de Marcas, Proveedores y Categorías: Formularios simples para crear, leer, actualizar y eliminar estos diccionarios de datos. Soportar la jerarquía infinita (o a 2 niveles) de las categorías usando el parent_id.
- Módulo de Productos: * Creación de ficha técnica del producto.
- Asignación múltiple a categorías (UI: Select múltiple o checkboxes).
- Definición de costos y precios base.

2. Distribución y Configuración por Tienda

- Módulo de Tiendas: Registro de los endpoints y credenciales de los e-commerce.
- Matriz de Producto-Tienda: Una vista dentro de la edición del producto donde se listan las tiendas activas. Aquí el usuario puede habilitar el producto para una tienda específica y sobrescribir opcionalmente el precio y el stock.

3. Motor de Sincronización (Eventos de Actualización)

- Eventos del Modelo (Hooks): En CodeIgniter, se utilizarán los eventos afterInsert y afterUpdate del modelo Producto y ProductoTienda.
- Proceso de Notificación (Push):
  - Al guardar un producto, el sistema identifica a qué tiendas está asociado.
  - Construye un Payload (JSON) con los datos del producto, aplicando la lógica de fallback (precios por defecto vs personalizados).
  - Realiza una petición POST/PUT al endpoint_url de cada tienda afectada.
- Registro de Errores (Logs): Dado que la comunicación con APIs externas puede fallar, es crítico implementar un log de transacciones básico para saber si la actualización llegó correctamente a la tienda o si dio un error (Timeout, 404, 500).
