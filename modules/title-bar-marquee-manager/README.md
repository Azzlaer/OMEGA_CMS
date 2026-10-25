# Title Bar Marquee Manager Professional v1.0.1 — OmegaCMS

Adaptación nativa para OmegaCMS del módulo original de WebEngine.

## Funciones conservadas

- Sin jQuery.
- No modifica HTML visible.
- Compatible con HTTPS/CSP.
- Usa el título generado por OmegaCMS o un texto personalizado.
- Prefijo y sufijo.
- Separador configurable.
- Modos: caracteres, palabras y rebote.
- Dirección izquierda/derecha.
- Velocidad y espera inicial.
- Pausa/restauración al perder foco.
- Restauración al pasar el mouse.
- Detención después de X ciclos.
- Modo diagnóstico.

## Integración Omega

El módulo usa:

- `site.head` para cargar el JavaScript.
- `/module-assets/title-bar-marquee-manager/...` para servir assets.
- `admin.panel` para la configuración visual.
- `admin.action` para guardar/resetear/verificar runtime.
- `cms_title_marquee_settings` en la base CMS.

No utiliza:
- output buffering
- jQuery
- WebEngine `Connection::Database`
- archivos globales en `includes/classes`

## Instalación

1. AdminCP → Módulos.
2. Instalar este ZIP.
3. Activar `Title Bar Marquee Manager Professional`.
4. Abrir su panel.
5. Guardar la configuración.

## Configuración recomendada

- Plugin activo: Sí.
- Usar título actual: Sí.
- Modo: Caracteres.
- Dirección: Izquierda.
- Velocidad: 180 ms.
- Separador: ✦.
- Espacios: 3.
- Espera inicial: 700 ms.
- Ciclos: 0.

## Ejemplo

`⚔️ Omega MU Online ✦ Temporada activa ✦`


## Hotfix v1.0.1

Corrige el warning de PHP 8.2:

`The use statement with non-compound name 'RuntimeException' has no effect`

Se eliminó `use RuntimeException;` de `TitleMarqueeService.php` porque el archivo
está en el namespace global y esa importación no es necesaria.
