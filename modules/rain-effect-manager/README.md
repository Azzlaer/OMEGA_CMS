# Rain Effect Manager Professional v1.0.0 — OmegaCMS

Adaptación nativa del módulo original de WebEngine CMS.

## Funciones conservadas

- Llovizna.
- Lluvia normal.
- Tormenta.
- Densidad configurable.
- Velocidad mínima/máxima.
- Largo y ancho de gotas.
- Viento.
- Opacidad y color.
- Salpicaduras.
- Relámpagos.
- Audio ambiental opcional.
- Densidad específica para móvil.
- Desactivación en móviles.
- FPS máximo.
- Pausa cuando la pestaña está oculta.
- Respeto por `prefers-reduced-motion`.
- Efecto en toda la pantalla o dentro de un selector CSS.
- Modo diagnóstico.

## Adaptación Omega

Usa:

- `site.head`
- `admin.panel`
- `admin.action`
- `/module-assets/rain-effect-manager/...`
- tabla `cms_rain_effect_settings`

No utiliza:

- jQuery
- output buffering
- `Connection::Database()`
- loaders de WebEngine
- clases globales de WebEngine

## Mejora incluida

El JavaScript original contenía una comprobación de `d.height`, propiedad que no
existe en cada gota. La adaptación usa directamente la altura real del Canvas
para reciclar las gotas al llegar al fondo.

## Audio

Por la CSP de Omega se recomienda un archivo alojado en el mismo sitio, por ejemplo:

`/public/assets/audio/rain.mp3`

El navegador exige una interacción del usuario antes de reproducir audio.

## Presets

Llovizna:
- 45 gotas
- 1300–2200 ms
- opacidad 0.35

Lluvia:
- 110 gotas
- 650–1200 ms
- opacidad 0.65

Tormenta:
- 220 gotas
- 350–850 ms
- relámpagos activos
