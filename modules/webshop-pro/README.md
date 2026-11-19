# WebShop PRO

WebShop nativo para OMEGA CMS inspirado conceptualmente en el antiguo **AdvancedWebShop v0.8**, pero reescrito para la arquitectura, seguridad y base de datos actuales de OMEGA.

## Qué se rescató del sistema antiguo

El paquete histórico contiene módulos orientados a:

- créditos/puntos;
- VIP;
- resets;
- unban;
- Zen;
- casino de créditos/Zen;
- seriales;
- refund;
- item manager / item adder;
- logs;
- rankings/top;
- exportación.

También utiliza una billetera antigua basada en `MEMB_CREDITS` y un procedimiento `XCredits`.

OMEGA no copia esa implementación directamente. La idea se reemplaza por una billetera con ledger, órdenes y auditoría.

## Arquitectura nueva

Tablas CMS:

- `cms_webshop_settings`
- `cms_webshop_categories`
- `cms_webshop_products`
- `cms_webshop_wallets`
- `cms_webshop_ledger`
- `cms_webshop_orders`
- `cms_webshop_deliveries`
- `cms_webshop_audit`

## Tipos de producto

- ITEM
- VIP
- RESET
- ZEN
- UNBAN

## Funciones v1.0.0

### Usuario

- Catálogo.
- Categorías.
- Búsqueda.
- Productos destacados.
- Stock.
- Precio en Credits.
- Saldo de billetera.
- Compra segura.
- Historial de órdenes.
- Estado de entregas.
- Límite por cuenta.
- Requisito de cuenta offline.

### AdminCP

- Activar/desactivar tienda.
- Modo mantenimiento.
- Nombre de moneda.
- Cantidad máxima por compra.
- Crear categorías.
- Crear productos.
- Ajustar Credits.
- Catálogo.
- Órdenes recientes.
- Cola de entregas.
- Diagnóstico.

## Entregas

La compra descuenta Credits dentro de una transacción CMS y genera una orden + entrega.

Procesamiento implementado actualmente:

- VIP: actualiza AccountLevel y AccountExpireDate.
- ZEN: acredita Warehouse.Money con límite seguro.
- UNBAN: limpia MEMB_INFO.bloc_code.

ITEM y RESET quedan en estado PENDING hasta conectar:

- inserter de Warehouse de 16 bytes y seriales;
- selector seguro de personaje para Reset.

Esto es deliberado: no se escriben ítems ni resets sin validar primero el formato exacto del servidor Kayito.

## Seguridad

- PDO preparado.
- Transacciones para saldos/compras.
- Locks durante débitos y stock.
- Ledger inmutable de movimientos.
- Auditoría.
- IP de compra almacenada únicamente como SHA-256.
- Cuenta offline para operaciones sensibles.
- Stock revalidado durante la transacción.
- Límite de compra por cuenta.

## Créditos

La idea de Credits proviene conceptualmente del AdvancedWebShop histórico. La implementación OMEGA es nueva y no depende del procedimiento antiguo XCredits.

Créditos del material de referencia original deben conservarse según corresponda.
