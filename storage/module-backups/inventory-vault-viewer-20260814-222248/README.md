# Inventario y Baúl Viewer v1.0.2 — OmegaCMS

Adaptación nativa del WebEngine Inventario y Baúl Viewer v1.0.7 FINAL POLISH.

## Conservado del original

- Solo lectura.
- Character.Inventory.
- warehouse.Items.
- 16 bytes por objeto.
- 120 bloques.
- Warehouse 1920 bytes.
- Equipo equipado slots 0–11.
- Mochila.
- Baúl clásico 8x15.
- Skin clásico MU Online.
- Diseño moderno alternativo.
- Estadísticas.
- Clan.
- Online/Offline.
- Bloqueo de visualización si el personaje está conectado.
- Serial opcional.
- Excellent / Ancient / Harmony / Socket.
- Contadores de objetos y casillas.
- Resolución de imágenes GIF por tipo, ID, nivel y variante.
- Catálogo MUCore_Shop_Items.

## Catálogo incluido

Se extrajeron **544 registros** del SQL original suministrado.

El módulo conserva la tabla `MUCore_Shop_Items` por compatibilidad y la rellena
solo si faltan registros. No borra ni reemplaza personalizaciones existentes.

## Imágenes

La adaptación incluye exactamente los archivos de imagen/skin que venían en el
ZIP proporcionado. El README del WebEngine original aclara que el pack grande
de imágenes también podía distribuirse por separado.

## Integración futura con Market Manager

`OmegaInventoryViewerService::decodeItem()` devuelve:

- id / index
- type
- name
- x / y
- level
- option
- skill
- luck
- excellent
- ancient
- harmony
- serial
- image_url
- decoder_source

Esto permite que Market Manager reemplace su modo conservador 1x1 y calcule
rectángulos reales para objetos 1x2, 2x2, 2x3, 2x4, etc.


## Hotfix v1.0.1 — Instalador seguro Omega

Se eliminaron archivos `.html` auxiliares incluidos dentro de los assets originales,
por ejemplo:

`assets/items/index.html`

OmegaCMS no permite `.html` dentro de paquetes de módulos por seguridad.
Estos archivos no son necesarios para el funcionamiento del visor.


## v1.0.2 — Integración con Item Images Pack

El visor detecta automáticamente el módulo hermano:

`inventory-item-images`

y busca primero las imágenes en:

`/module-assets/inventory-item-images/assets/items/`

Si el pack no está instalado, continúa usando las imágenes locales incluidas
dentro del visor.

El pack suministrado contiene 947 GIF.
