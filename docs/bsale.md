# Integración Bsale

Este documento describe cómo configurar la integración entre WooCommerce y Bsale a través de central-pim. Cuando WooCommerce registra una venta, dispara un webhook hacia central-pim, que crea automáticamente el documento de venta en Bsale y baja el stock de cada producto involucrado.

---

## 1. Credenciales de Bsale

### 1.1 Obtener el Access Token

1. Ingresar al panel de Bsale como administrador.
2. Ir a **Configuración → Aplicaciones → API**.
3. Generar o copiar el token de acceso.

### 1.2 Obtener el Office ID

1. En el panel de Bsale ir a **Configuración → Sucursales**.
2. Copiar el ID numérico de la sucursal desde la que se emitirán los documentos.

Alternativamente, una vez configurado el token, la lista de sucursales disponibles se puede consultar desde la UI de central-pim en **Bsale → Documentos** (columna de configuración).

### 1.3 Obtener los Document Type IDs

El operador elige en el momento de emitir si el documento es **boleta** o **factura**. Cada tipo tiene un ID propio en Bsale.

1. En Bsale ir a **Configuración → Tipos de Documento**.
2. Identificar el tipo "Boleta electrónica" y anotar su ID.
3. Identificar el tipo "Factura electrónica" y anotar su ID.
4. Ambos tipos deben tener movimiento de stock habilitado.

### 1.4 Configurar el archivo `.env`

Abrir `central-pim/.env` y completar:

```ini
BSALE_ACCESS_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxx
BSALE_OFFICE_ID=1
BSALE_BOLETA_TYPE_ID=8
BSALE_FACTURA_TYPE_ID=9
BSALE_PRICE_LIST_ID=1
BSALE_WEBHOOK_SECRET=un_secreto_largo_y_aleatorio
```

| Variable | Descripción |
|---|---|
| `BSALE_ACCESS_TOKEN` | Token de la API de Bsale |
| `BSALE_OFFICE_ID` | ID de la sucursal emisora |
| `BSALE_BOLETA_TYPE_ID` | ID del tipo de documento "Boleta electrónica" en Bsale |
| `BSALE_FACTURA_TYPE_ID` | ID del tipo de documento "Factura electrónica" en Bsale |
| `BSALE_PRICE_LIST_ID` | ID de la lista de precios (por defecto `1`) |
| `BSALE_WEBHOOK_SECRET` | Secret compartido con WooCommerce para validar la firma HMAC |

> El `BSALE_WEBHOOK_SECRET` lo defines tú. Usa una cadena aleatoria larga (mínimo 32 caracteres). Se configurará en WooCommerce en el paso siguiente.

---

## 2. Configurar el Webhook en WooCommerce

### 2.1 Crear el webhook

1. En el panel de WordPress ir a **WooCommerce → Ajustes → Avanzado → Webhooks**.
2. Hacer clic en **Agregar webhook**.
3. Completar los campos:

| Campo | Valor |
|---|---|
| **Nombre** | Bsale — Pedido creado |
| **Estado** | Activo |
| **Topic** | Order created |
| **URL de entrega** | `https://tu-dominio.cl/webhooks/woocommerce/order-created` |
| **Secret** | El mismo valor de `BSALE_WEBHOOK_SECRET` en `.env` |
| **Versión de API** | WooCommerce REST API v3 |

4. Guardar. WooCommerce enviará un ping de verificación — si el servidor está accesible y la ruta existe, se marcará como activo.

### 2.2 Verificar conectividad

En el listado de webhooks, la columna **Entregas** muestra el historial de intentos. Si el ping inicial falla:

- Verificar que la URL sea accesible desde internet (no localhost).
- Verificar que no haya un firewall o regla `.htaccess` bloqueando `POST /webhooks/...`.
- Revisar los logs de central-pim en `writable/logs/`.

---

## 3. Mapear productos (paso obligatorio antes de la primera venta)

Bsale requiere el `variant_id` propio de su catálogo para crear líneas de documento. El mapeo entre el `product_id` de WooCommerce y el `variant_id` de Bsale se hace desde la UI.

### 3.1 Buscar variantes por SKU

1. En central-pim ir a **Bsale → Mapeo de variantes**.
2. En el campo SKU escribir el código del producto tal como está en WooCommerce.
3. Hacer clic en la lupa — la UI consultará la API de Bsale en tiempo real y mostrará coincidencias.
4. Seleccionar la variante correcta: se autocompletarán el `bsale_variant_id` y el nombre.
5. Ingresar el `woo_product_id` (visible en WooCommerce → Productos → ID).
6. Guardar.

Repetir para cada producto que pueda aparecer en un pedido.

### 3.2 Qué ocurre si falta un mapeo

Si llega un pedido con un producto sin mapeo, el documento no se crea. El registro queda en `bsale_documents` con `estado = 'error'` y un mensaje que indica el SKU faltante. El flujo de recuperación es:

1. Agregar el mapeo faltante en **Bsale → Mapeo de variantes**.
2. Ir a **Bsale → Documentos**, ubicar el pedido en error.
3. Hacer clic en el botón **Reintentar** (ícono de flecha circular).

---

## 4. Flujo completo de una venta

```
Cliente paga en WooCommerce
        │
        ▼  POST /webhooks/woocommerce/order-created
        │  Header: X-WC-Webhook-Signature (HMAC-SHA256)
        │
central-pim
        ├─ Registra el evento en webhook_events
        ├─ Valida firma HMAC → rechaza si es inválida (401)
        ├─ Parsea el payload: extrae order_id, email, ítems
        ├─ Verifica idempotencia: si ya existe un registro para ese pedido, sale
        ├─ Crea registro en bsale_documents (estado = pendiente)
        └─ Responde 200 a WooCommerce ← fin del flujo automático

Operador (manual, desde la UI)
        ├─ Abre Bsale → Documentos → pedido en estado "pendiente"
        ├─ Revisa los ítems y elige: "Emitir boleta" o "Emitir factura"
        │
central-pim
        ├─ Mapea cada ítem a bsale_variant_id
        ├─ Construye el payload para POST /documents.json
        ├─ Llama a la API de Bsale
        │       └─ Éxito → estado = creado, guarda ID y URL del PDF
        │       └─ Error  → estado = error, guarda mensaje (reintentar desde la misma UI)
        └─ Redirige al detalle del documento
```

---

## 5. Panel de administración

Accesible desde el sidebar: **Bsale → Documentos**.

### Documentos (`/bsale`)

Tabla con los últimos 50 documentos generados. Columnas:

| Columna | Descripción |
|---|---|
| Pedido WC | Número de orden de WooCommerce |
| Email cliente | Extraído del billing del pedido |
| Estado | `pendiente` / `creado` / `error` |
| Doc. Bsale | ID del documento generado en Bsale |
| PDF | Enlace directo al PDF del documento |
| Acciones | Ver detalle · Reintentar (solo en estado error) |

Estadísticas en la parte superior: documentos hoy, últimos 7 días, total creados, total con error.

### Detalle de documento (`/bsale/show/{id}`)

Muestra:
- Datos del pedido (ítems, precios, email).
- Payload enviado a Bsale (JSON colapsable).
- Respuesta de Bsale (JSON colapsable).
- Payload original recibido de WooCommerce (JSON colapsable).
- Mensaje de error si aplica.

### Mapeo de variantes (`/bsale/variant-map`)

Alta y consulta del mapeo `woo_product_id → bsale_variant_id`. Incluye búsqueda AJAX contra la API de Bsale.

---

## 6. Consideraciones técnicas

### Precios con/sin IVA

WooCommerce puede enviar precios con o sin IVA según la configuración de la tienda. Bsale espera `unitValue` sin IVA cuando el tipo de documento tiene IVA incluido (`declare = 1`). Verificar en **WooCommerce → Ajustes → Impuestos** si los precios ingresados incluyen impuestos.

### Reintentos de WooCommerce

WooCommerce reintenta el webhook hasta 5 veces si no recibe HTTP 2xx en menos de 5 segundos. central-pim siempre responde 200 aunque ocurra un error interno, para evitar reintentos infinitos. Los errores quedan registrados en `bsale_documents` para revisión manual.

### Idempotencia

El campo `woo_order_id` tiene `UNIQUE KEY` en `bsale_documents`. Aunque lleguen múltiples webhooks para el mismo pedido, solo se crea un documento.

### Seguridad del endpoint

El endpoint `/webhooks/woocommerce/order-created` es público pero valida la firma HMAC-SHA256 de cada request. Todo evento —incluso los de firma inválida— queda registrado en `webhook_events` para detección de ataques.
