# Duelos PRO v2.0.0

Evolución de `Duel History Professional` para OMEGA CMS.

## Ubicación principal

**Rankings → Sistemas → Duelos**

URL:

`/rankings?type=duel`

El módulo ya no aparece en el menú de Mi Cuenta.

## Fuentes de datos

Modo READ ONLY sobre:

- `dbo.DuelRanking`
- `dbo.DuelHistory`
- `dbo.Character`

OMEGA no modifica estas tablas.

## Center de Duelos

El ranking público conserva la tabla nativa de OMEGA y ahora añade un center configurable por encima con:

- jugadores visibles;
- duelos acumulados;
- victorias;
- mejor rating;
- mejor racha opcional.

## AdminCP

Desde:

**AdminCP → Módulos → Duelos PRO**

se configura:

- Diseño Duelos
- Banner personalizado
- Sin cabecera
- Eyebrow
- Título
- Descripción
- Cantidad de jugadores del ranking
- Mostrar/ocultar resumen PvP
- Mostrar/ocultar mejor racha

## Banner

Formatos:

- JPG
- PNG
- WEBP

Máximo:

- 8 MB

Dimensiones aceptadas:

- mínimo 640×160
- máximo 7680×2160

Ruta:

`/public/uploads/duel-system-pro/`

### Ajuste

- Rellenar / `cover`
- Encajar / `contain`
- Estirar / `fill`
- Tamaño natural / `scale-down`

También permite:

- Centro
- Arriba
- Abajo
- Izquierda
- Derecha
- Altura entre 160 y 720 px

## Persistencia CMS

La configuración se almacena en:

`dbo.cms_duel_system_settings`

## Compatibilidad

La lógica histórica de `DuelHistory` se conserva internamente para no perder funcionalidad existente, pero la navegación principal del sistema queda centrada en Rankings → Sistemas → Duelos.

## Seguridad

- GameServer en modo READ ONLY
- CSRF del Core para acciones AdminCP
- validación real de imágenes
- no SVG
- nombres aleatorios para uploads
- no se expone AccountID públicamente
