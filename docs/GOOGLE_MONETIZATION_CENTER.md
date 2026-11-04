# Google & Monetization Center PRO

OMEGA CMS integra monetización y servicios Google sin depender de WordPress/Site Kit.

## Funciones

- GA4 Measurement ID.
- Google Ads tag ID.
- Google Tag Manager.
- Google Site Verification / Search Console.
- AdSense Publisher ID.
- Auto Ads opcional.
- Bloques AdSense manuales.
- Banners propios / House Ads / HTML seguro.
- `/ads.txt` administrado desde AdminCP.
- Consent manager con Analytics, Publicidad y Personalización.
- Google Consent Mode v2.
- Restricción de anuncios por audiencia, dispositivo, fechas y rutas.
- Opción para ocultar publicidad a usuarios conectados o administradores.

## Placements globales

- `global_header`
- `below_navigation`
- `content_top`
- `content_bottom`
- `global_footer`

Las reglas de ruta permiten reutilizar esos placements en cualquier sección. Por ejemplo:

- `/guides*`
- `/rankings`
- `/news*`
- `/module/*`

## Configuración

Ingresar como SuperAdmin y abrir:

`AdminCP > Plataforma > Google & Monetization`

Para AdSense manual:

1. Configurar el Publisher ID `ca-pub-...`.
2. Crear un bloque con proveedor Google AdSense.
3. Copiar el Ad Slot numérico entregado por AdSense.
4. Elegir placement y, opcionalmente, limitar rutas.
5. Activar el bloque.

Para ads.txt, habilitar la opción y pegar las líneas exactamente como fueron entregadas por el proveedor publicitario. OMEGA publica el contenido en `/ads.txt`.

## Privacidad

OMEGA bloquea las etiquetas opcionales hasta que la categoría correspondiente ha sido aceptada cuando el Consent Manager está activo. También inicializa Consent Mode v2 para Analytics/Ads.

Si se usa Google Tag Manager, el contenedor solo se carga cuando Analytics y Publicidad han sido aceptadas. Las reglas de consentimiento internas del contenedor siguen siendo responsabilidad de la configuración de GTM.

## Google Ads API

La versión inicial integra etiquetado y medición de Google Ads. La creación/edición remota de campañas no se incluye porque requiere OAuth, Google Ads Developer Token y permisos de cuenta; debe implementarse como una fase separada sin almacenar secretos en el repositorio.
