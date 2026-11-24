# Evento TvT PRO v1.2.0

Módulo de estadísticas Team vs Team para OMEGA CMS.

## Ubicación

TvT vive exclusivamente en:

**Rankings → Eventos → TvT**

URL:

`/rankings?type=tvt`

No aparece en el menú de perfil ni en Mi Cuenta.

## AdminCP propio

La configuración genérica anterior fue reemplazada por un panel propio dentro de:

**AdminCP → Módulos → Evento TvT PRO**

Desde ahí se controla toda la presentación y comportamiento del módulo.

### Cabecera exclusiva

Solo puede existir un modo activo a la vez:

- **Diseño TvT**
- **Banner personalizado**
- **Sin cabecera**

Diseño y banner nunca se renderizan juntos.

### Diseño TvT

Permite configurar:

- eyebrow;
- título;
- descripción.

El diseño utiliza la clase aislada `tvt-hero` para evitar colisiones con estilos globales `.hero`.

### Banner personalizado

Se puede subir directamente desde AdminCP.

Formatos:

- JPG
- PNG
- WEBP

Límite:

- 8 MB
- mínimo 640×160
- máximo 7680×2160

Se recomienda aproximadamente **1600×420**.

Los banners se guardan en:

`/public/uploads/event-tvt-pro/`

Subir un banner lo activa automáticamente como cabecera.

Eliminarlo restaura automáticamente el modo **Diseño TvT**.

No es posible activar el modo Banner si todavía no existe una imagen cargada.

## Configuración del ranking

Desde el mismo panel se configura:

- cantidad de partidas recientes;
- cantidad de jugadores del ranking;
- cantidad de acciones del timeline;
- mostrar/ocultar recompensas Zen;
- mostrar/ocultar objetivos;
- mostrar/ocultar selector “Mis personajes”.

## Mis personajes

Cuando está activo y existe una sesión iniciada, OMEGA obtiene los personajes por:

`dbo.Character.AccountID`

El selector solo acepta personajes pertenecientes a la cuenta autenticada.

## Datos TvT

El módulo trabaja en modo READ ONLY sobre:

- `dbo.TvTMatch`
- `dbo.TvTResult`
- `dbo.TvTAction`
- `dbo.Character`

La configuración visual se almacena en la base CMS:

`dbo.cms_tvt_module_settings`

## Integración Core v1.2

`/admin/modules/action` ahora entrega también `$_FILES` al hook `admin.action`.

Esto permite que módulos confiables implementen formularios de subida propios manteniendo:

- CSRF del Core;
- validación del módulo;
- aislamiento por slug.

## Seguridad del banner

- solo imágenes reales detectadas por PHP;
- extensiones limitadas;
- tamaño y dimensiones máximas;
- nombre aleatorio;
- no se acepta SVG;
- eliminación restringida a `/public/uploads/event-tvt-pro/`;
- CSRF obligatorio en todas las acciones AdminCP.
