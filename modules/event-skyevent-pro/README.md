# Evento SkyEvent PRO

Extensión de presentación y configuración de Sky Event para OMEGA CMS.

## Ubicación

Sky Event aparece en:

**Rankings → Eventos → Sky Event**

No pertenece al grupo Sistemas.

## Fuente de datos

Se conserva la fuente existente:

`dbo.SkyEventRankingTotal`

y el driver:

`Driver::skyEventRanking()`

El módulo no modifica datos del GameServer.

## AdminCP

Desde **AdminCP → Módulos → Evento SkyEvent PRO** se configura:

- Diseño SkyEvent
- Banner personalizado
- Sin cabecera
- eyebrow
- título
- descripción
- cantidad de jugadores del ranking
- resumen estadístico

Solo un modo de cabecera puede mostrarse a la vez.

## Banner

Formatos:

- JPG
- PNG
- WEBP

Límites:

- máximo 8 MB
- mínimo 640×160
- máximo 7680×2160
- recomendado 1600×420

Ruta:

`/public/uploads/event-skyevent-pro/`

## Persistencia

La configuración se guarda en:

`dbo.cms_skyevent_module_settings`
