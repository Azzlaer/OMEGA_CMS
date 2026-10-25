# Inventario y Baúl Viewer v2.0.0 — Kayito Sync

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


## v1.0.3 — Packs divididos por límite de 500 archivos

OmegaCMS limita los módulos a menos de 500 archivos.

El pack de imágenes original contiene 947 GIF, por lo que ahora se instala
en dos módulos:

- `inventory-item-images-1`
- `inventory-item-images-2`

El visor busca automáticamente en ambos y muestra el estado de cada parte
en su diagnóstico.


## v1.1.0 — Corrección basada en warehouse.sql real

Se analizó una exportación real de la tabla `warehouse`.

Resultado:

- 70 registros analizados.
- Todos los `Items` contienen exactamente 1200 bytes.
- Cada baúl se divide exactamente en 120 bloques.
- Cada bloque ocupa 10 bytes / 20 caracteres HEX.
- Formato confirmado: MU 0.97k legacy.

Ejemplo real:

`85A043000100C1410004`

= 10 bytes.

Por lo tanto se reemplazó la configuración incorrecta anterior:

- 16 bytes/item → 10 bytes/item.
- Warehouse 1920 → 1200 bytes.
- Inventory legacy previsto: 76 × 10 = 760 bytes.

El decoder soporta también 16 bytes como fallback, pero auto-detecta primero
1200/760 para la base 0.97k.

### Importante

El Market Manager v1.0.x anterior NO debe utilizarse para publicar o retirar
objetos sobre esta base, porque fue construido inicialmente con bloques de
16 bytes. Debe actualizarse a formato 10-byte antes de habilitar operaciones.


## v1.1.1 — Decoder Fix crítico

Para objetos de 10 bytes de MU 0.97k, el último byte se interpreta como:

- nibble bajo: Item Type / Group
- nibble alto: refinery/flags legacy

Ejemplo real del dump: `85A043000100C1410004` -> Type 4, Index 133.

La v1.1.0 leía el nibble alto y lo interpretaba como Type 0, causando nombres e imágenes incorrectos.

Se agregó `Diagnóstico HEX / slot` para mostrar en tooltip el slot, Type:Index, HEX crudo y decoder.


## v1.2.0 — Strict Kayito

Esta versión deja de inferir el identificador del item con fórmulas WebEngine.

El decoder replica la fórmula del GameServer Kayito:

`m_Index = byte0 | ((byte9 & 0xF0) * 32) | ((byte7 & 0x80) * 2)`

y obtiene:

- `Type = floor(m_Index / 512)`
- `Index = m_Index % 512`
- Skill = bit 7 de byte1
- Luck = bit 2 de byte1
- Level = bits 3..6 de byte1
- Additional = bits 0..1 de byte1 + bit 6 de byte7
- Excellent = bits 0..5 de byte7
- Serial = bytes 3..6

### Perfiles DB comprobados

- Character.Inventory: varbinary(760), 76 slots de 10 bytes.
- warehouse.Items: varbinary(1200), 120 slots de 10 bytes.
- ExtWarehouse.Items: columna varbinary(1920), pero los 75 registros del dump suministrado
  contienen 1200 bytes reales. Se decodifican por `DATALENGTH`, no por tamaño máximo de columna.

### Modo estricto

Si un Type:Index no existe en el catálogo:

- no se inventa nombre;
- no se busca una imagen "parecida";
- aparece `Item desconocido Type:Index`;
- en diagnóstico muestra HEX, slot, AbsoluteIndex, Byte7 y Byte9.

### Baúles extendidos

Se añadió navegación por `ExtWarehouse.Number` y visualización separada de cada
baúl extendido.

### Seguridad

Este módulo continúa siendo solo lectura.
No modifica Character.Inventory, warehouse.Items ni ExtWarehouse.Items.


## v1.3.0 — Item.txt autoritativo

Se integró el `Item.txt` real suministrado para este GameServer.

- Items parseados: 464
- Type:Index únicos: 464
- Secciones: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13
- SHA-256 del Item.txt fuente: `45aeb5c6bd50fe65c7d75c200cec054146c3264378e96395aeab74034beb0ada`

El visor ya no usa `MUCore_Shop_Items` para decidir nombre o dimensiones.
Esa tabla se conserva únicamente por compatibilidad con módulos antiguos.

### Regla anti-fantasma

Un bloque decodificado que no exista en el Item.txt real:

- no recibe nombre de otro catálogo;
- no recibe GIF aproximado;
- no participa en el mapa de ocupación;
- no se muestra en modo normal;
- aparece únicamente si se activa `Diagnóstico HEX / slot` o
  `Mostrar Type:Index desconocidos`.

### Ejemplo real

`Type 0 / Index 21 = Dark Reign Blade`, tamaño `2x4`.

El nombre y las dimensiones salen directamente del Item.txt integrado.


## v1.4.0 — Unknown Item Mapper

Esta versión agrega una capa de mapeo manual para `Type:Index` desconocidos.

### Qué hace

- escanea `Character.Inventory`, `warehouse.Items` y `ExtWarehouse.Items`;
- lista todos los `Type:Index` que no existen en el `Item.txt` ni en mapeos personalizados;
- permite registrar manualmente:
  - nombre,
  - ancho,
  - alto,
  - imagen,
  - notas.

### Prioridad de identificación

1. mapeo manual del administrador
2. `Item.txt` real del GameServer
3. desconocido

### Imágenes personalizadas

Se aceptan tres formatos:

- `https://servidor/mi_item.gif`
- `assets/custom-items/mi_item.gif`
- `mi_item.gif` (se interpreta como `assets/custom-items/mi_item.gif`)

Se incluye la carpeta `assets/custom-items/` para que puedas subir tus GIF manualmente por FTP si quieres que queden dentro del módulo.


## v1.5.0 — Discovery Center

Se amplió el administrador de items desconocidos con:

- escaneo de hasta 500 Type:Index desconocidos;
- exportación JSON de mappings;
- importación JSON de mappings;
- compatibilidad con la herramienta Python `Omega MU Item Discovery Tool`;
- explorador visual de imágenes instaladas;
- copia rápida de URL de imagen;
- mapeo manual con nombre, X/Y, imagen y notas.

Los mapeos manuales tienen prioridad sobre Item.txt.


## v1.5.1 — Corrección crítica de identificación

El source real Kayito define:

`MAX_ITEM_TYPE = 32`

`GET_ITEM(section,index) = section * 32 + index`

Por eso un índice interno `399` es:

- Section: `12`
- Index: `15`

y NO `0:399`.

El Item.txt suministrado contiene 134 definiciones con `Index > 31`.
Esas entradas se marcan como conflictivas porque matemáticamente se pisan con
la siguiente sección en esta fuente 0.97k. No reemplazan IDs canónicos 0..31.


## v2.0.0 — Kayito Sync

Versión limpia para eliminar diferencias entre el Python GUI y el módulo web.

### Cambios fundamentales

- Nuevo slug: `inventory-vault-viewer-kayito`.
- Nueva tabla de configuración:
  `cms_inventory_viewer_kayito_settings`.
- Nueva tabla de mappings:
  `cms_inventory_viewer_kayito_custom_items`.
- No reutiliza mappings incorrectos de versiones anteriores.
- Item.txt tiene prioridad absoluta sobre mappings manuales.
- Decoder idéntico al Python GUI:
  - `Type = AbsoluteIndex // 32`
  - `Index = AbsoluteIndex % 32`
- Panel `Kayito Item Scanner` muestra TODOS los Type:Index detectados.
- Muestra:
  - estado conocido/desconocido,
  - AbsoluteIndex,
  - nombre,
  - X/Y,
  - conteo total,
  - Character,
  - warehouse,
  - ExtWarehouse,
  - HEX,
  - imagen.
- Muestra cobertura total del catálogo.

Con el dump usado durante desarrollo:
- Character: 2121/2121 reconocidos.
- warehouse: 816/816 reconocidos.
- ExtWarehouse: 683/683 reconocidos.
- cobertura: 100%.
