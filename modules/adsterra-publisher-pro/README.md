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
