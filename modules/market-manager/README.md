# Market Manager Professional v1.0.1 — OmegaCMS

Adaptación nativa del WebEngine Market Manager Professional v0.4.4 Object ID Detection Fix.

## Filosofía conservada

- Mercado desactivado por defecto.
- Publicaciones desactivadas por defecto.
- Compras desactivadas por defecto.
- Diagnóstico antes de activar.
- Cuenta offline para operaciones sensibles.
- Warehouse protegido con transacciones y bloqueos SQL.
- Multi-moneda con mapeo explícito y validado.
- Comisión por moneda.
- Bóveda de Entregas.
- Cancelar una publicación NO devuelve directamente el objeto al Warehouse:
  lo protege primero en la Bóveda.
- Compra: el objeto nunca se pierde entre comprador/vendedor; pasa a Bóveda.

## Warehouse MuEmu 0.97k

Configuración canónica usada:

- warehouse.Items = 1920 bytes
- objeto = 16 bytes
- 120 bloques físicos

Esta decisión evita el problema histórico del módulo WebEngine que llegó a tratar
el Warehouse como 60 bloques de 32 bytes.

## Object ID Detection Fix

Se conserva la idea del v0.4.4:

1. Si el objeto tiene serial válido:
   `SERIAL:XXXXXXXX`

2. Si el serial es inválido/ausente:
   se genera una identidad determinista:
   `HEX:SHA256(item-block)`

La publicación guarda además:
- ItemHex
- ItemType
- ItemIndex
- ItemLevel
- ObjectKey
- SourceSlot

## Monedas

Cada moneda define:
- tabla
- columna de cuenta
- columna de saldo
- mínimo
- máximo
- comisión
- estado
- MappingVerified

Por defecto intenta mapear Zen a:

warehouse.AccountID
warehouse.Money

solo si ambas columnas existen.

## Retiro desde Bóveda

v1.0.0 usa un modo conservador 1x1.

Sin un catálogo completo de tamaños X/Y de objetos no es seguro colocar
automáticamente objetos de 2x2, 1x3, etc. Por eso esta primera adaptación
no pretende adivinar dimensiones.

La siguiente integración natural es conectar el Market con un módulo
Inventory Viewer para obtener x/y reales, igual que hacía WebEngine v0.4.4.

## Tablas creadas

- cms_market_settings
- cms_market_currencies
- cms_market_listings
- cms_market_transactions
- cms_market_delivery_vault
- cms_market_audit_log

Todas viven en MuOnline97 para que las transacciones que involucran Warehouse
y monedas sean atómicas.

## Página de usuario

Mi Cuenta → Mercado

Pestañas:
- Mercado
- Publicar
- Mis publicaciones
- Entregas

## Seguridad

Antes de habilitar:
1. Ejecuta Diagnóstico seguro.
2. Comprueba Warehouse 1920 bytes.
3. Verifica cada moneda.
4. Prueba publicar/cancelar/comprar con cuentas de laboratorio.
5. Solo después activa publicaciones y compras.


## Hotfix v1.0.1 — Diagnóstico

La v1.0.0 comprobaba `warehouse.Items` usando `SELECT TOP 1`.
Una cuenta cuyo Warehouse aún no estuviera inicializado podía devolver `NULL`
y producir un falso `Warehouse 1920 bytes -> REVISAR`.

v1.0.1 comprueba:

- definición de la columna `warehouse.Items`
- cantidad de warehouses inicializados
- longitud mínima
- longitud máxima
- cantidad exacta de filas con 1920 bytes

Además `Publicaciones` y `Compras` ya no se muestran como error:
- `SEGURO / OFF` cuando están apagadas
- `ACTIVO` cuando están habilitadas
