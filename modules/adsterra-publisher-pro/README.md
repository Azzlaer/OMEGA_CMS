# Adsterra Publisher Center PRO

Módulo Publisher para OMEGA CMS.

## Formatos

- Popunder
- Social Bar
- In-Page Push / Interstitial mediante Social Bar
- Native Banner
- Display Banner
- Smartlink / Direct Link

## Flujo

Adsterra continúa siendo la fuente oficial de los ad units. El Publisher crea el website/ad unit en su cuenta Adsterra, espera aprobación y copia el código con GET CODE.

OMEGA administra dónde y cuándo ejecutar ese código.

## Zonas OMEGA

- head
- global_header
- below_navigation
- content_top
- content_bottom
- global_footer

## Reglas

Cada unidad puede limitarse por:

- audiencia: todos, invitados o miembros;
- dispositivo: todos, desktop o mobile;
- rutas;
- fecha inicial/final;
- consentimiento de publicidad;
- orden de render.

## Publisher API

La Publisher API se consume server-side mediante:

`https://api3.adsterratools.com/publisher`

El token se envía en `X-API-Key`.

OMEGA utiliza actualmente endpoints documentados para:

- comprobar dominios mediante `domains.csv`;
- estadísticas mediante `stats.json`.

El API Key se almacena usando el tipo `secret` del sistema modular de OMEGA, cifrado con APP_KEY/AES-256-GCM.

## Seguridad CSP

OMEGA añade un nonce por request a scripts pegados desde Adsterra.

Los hosts externos presentes en src/href se extraen al guardar la unidad. Se pueden añadir hosts secundarios manualmente desde AdminCP. Solo orígenes HTTPS válidos se incorporan a CSP.

No se habilita `unsafe-inline` para JavaScript.

## Smartlink

Para diferenciar una misma Smartlink entre páginas se puede configurar Sub ID. OMEGA añade automáticamente:

`psid=<sub_id>`

al enlace.

## Limitaciones

La Publisher API de Adsterra es actualmente de lectura. La creación/aprobación de websites y ad units se sigue haciendo desde el dashboard de Adsterra.


## Banners rápidos v1.1.0

El AdminCP incluye presets directos para dos unidades configuradas:

### Banner 320x50

- Key: `db7c51e2503600358d28fe5dde1df677`
- Script: `https://bicea.org/22/db7c51e2503600358d28fe5dde1df677`
- Recomendado para:
  - Global Header
  - Debajo de navegación
  - Inicio del contenido
  - Final del contenido
  - Global Footer

### Banner 160x300

- Key: `0b579ac111e6a011e44b71f87ed216fc`
- Script: `https://bicea.org/22/0b579ac111e6a011e44b71f87ed216fc`
- Recomendado para:
  - Inicio del contenido
  - Final del contenido
  - Footer
  - Desktop

Cada preset genera automáticamente el bloque `atOptions`, el `<script src>`, el host CSP `https://bicea.org` y permite seleccionar audiencia, dispositivo, rutas y orden.

El código manual continúa disponible para otros tamaños y formatos.
