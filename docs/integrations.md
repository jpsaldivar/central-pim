# Arquitectura del Sistema de Integraciones

Este módulo define la estructura para conectar el Core de la aplicación (CodeIgniter) con proveedores externos, garantizando escalabilidad para futuros canales de venta.

## 1. Diseño Estructural

Se implementa una **Service Layer** que actúa como orquestador transversal.

### Core Integration Service (CIS)

Es el componente central que maneja la lógica de negocio independiente de la plataforma:

- Cálculo de precios (impuestos, márgenes).
- Reglas de negocio para el stock (mínimos de seguridad).
- Registro de logs y auditoría de sincronización.

### Adapters (JumpsellerAdapter / WooAdapter)

Cada proveedor externo tiene su propio Adapter que implementa una interfaz común `IntegrationInterface`.

- **Responsabilidad:** Traducir los métodos genéricos (`updateStock()`, `fetchProducts()`) en llamadas específicas de la API del proveedor.

## 2. Componentes de Conexión

### Connection Manager

Encargado de gestionar las credenciales y tokens OAuth/API Keys almacenados en la base de datos. Implementa el manejo de **Rate Limiting** para evitar bloqueos (429 Too Many Requests) mediante algoritmos de *Exponential Backoff*.

### Webhook Listener

Componente para la sincronización en tiempo real.

- **Jumpseller:** Recibe notificaciones de `product.updated`.
- **Acción:** El listener activa el CIS para replicar el cambio en WooCommerce de forma inmediata.

## 3. Manejo de Errores y Resiliencia

- **Logging:** Registro detallado de cada payload enviado y recibido en `writable/logs/integrations/`.
- **Retry Policy:** En caso de error 5xx, el sistema reintentará la operación 3 veces con intervalos crecientes antes de marcar la tarea como fallida.
