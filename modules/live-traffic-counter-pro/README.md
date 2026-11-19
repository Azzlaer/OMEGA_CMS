# Live Traffic & Visitor Counter PRO

Módulo nativo de analítica ligera para OMEGA CMS, inspirado en la idea de los widgets públicos de servicios como LiveTrafficFeed pero sin depender de scripts externos.

## Métricas

- Visitantes online.
- Visitantes únicos de hoy.
- Visitantes únicos históricos.
- Pageviews de hoy.
- Pageviews históricos.
- Distribución online Desktop / Mobile / Other.

## Widgets

- Solo online.
- Visitantes únicos totales.
- Visitantes de hoy.
- Pageviews.
- Tarjeta resumen.
- Compacto.
- Barra horizontal.
- Pestaña flotante.

## Ubicaciones

- Footer.
- Antes del contenido.
- Después del contenido.
- Flotante.

## Privacidad

El módulo no almacena IPs en texto plano. Crea una cookie first-party aleatoria `omega_visitor` y guarda únicamente un hash HMAC basado en el token y `APP_KEY`.

No realiza geolocalización ni comparte datos con terceros.

## Tiempo real

El navegador consulta:

`/module-api/live-traffic-counter-pro/stats`

La consulta también funciona como heartbeat y mantiene actualizado el estado "online" sin incrementar pageviews.

## Tablas

- `cms_traffic_settings`
- `cms_traffic_visitors`
- `cms_traffic_daily`

Las tablas se crean automáticamente al activar/ejecutar el módulo.

## Bots

Por defecto se ignoran User-Agents comunes de bots, crawlers, monitores y navegadores headless.

## Mapas / países

La versión 1.0.0 no geolocaliza visitantes. Esto es deliberado para no enviar IPs a terceros. Una futura versión puede añadir GeoIP local o una integración externa opcional.
