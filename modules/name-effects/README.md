# Name Effects Professional v1.1.2 — OmegaMU CMS

Adaptación completa del módulo WebEngine original.

## Ahora incluye los 20 GIF originales

1. White Sparks
2. Green Twinkles
3. White Dots
4. Pink Bar
5. Red Cross
6. Rainbow Rain
7. Blue Gold Star
8. Fire Aura
9. Magic Star Purple
10. Golden Fairy
11. Candy Ribbons
12. Blue Snow
13. Blue Sparkles
14. Gold Line
15. White Twinkles
16. Ice Flame
17. Red Orb
18. Snowflakes
19. Gold Line II
20. Blue Twinkles Pair

Los archivos se incluyen en:
`assets/effects/*.gif`

## Precarga automática
Al activarse/ejecutarse el módulo, `seedOriginalEffects()` agrega los presets que
todavía no existan en `OmegaNameEffects`.

No duplica los efectos ya existentes porque usa `EffectKey` como identificador.

## Costos originales conservados
Desde 51.000.000 Zen hasta 70.000.000 Zen, todos por 30 días, igual que el
catálogo original de WebEngine. Luego puedes modificarlos desde AdminCP.

## Integración
Los efectos se muestran en:
- Rankings
- Usuarios Online
- Mis Personajes
- Ficha individual
- UserCP del módulo

## Requisito
OmegaMU CMS 0.13.1+ porque el Core debe servir imágenes GIF/PNG/WEBP desde
`/module-assets/` de forma segura.


## Corrección v1.1.1

Corrige las vistas previas del catálogo en:
- AdminCP
- Mi Cuenta > Efectos de Nombre

Antes esas dos vistas usaban directamente:
`assets/effects/nombre.gif`

Ahora convierten siempre la ruta mediante:
`$service->assetUrl()`

Resultado:
`/module-assets/name-effects/assets/effects/nombre.gif`

El renderer aplicado a Rankings/Online/Mis Personajes ya utilizaba esta ruta segura.


## Corrección v1.1.2 — SQL Server DATEADD

PDO_DBLIB puede enviar parámetros numéricos como VARCHAR.

SQL Server rechaza:
`DATEADD(DAY,:d,SYSUTCDATETIME())`

La versión 1.1.2 usa:
`DATEADD(DAY,CAST(:d AS INT),SYSUTCDATETIME())`

La misma corrección se aplica al extender un efecto que todavía no expiró.

Corrige el error:
`Argument data type varchar is invalid for argument 2 of dateadd function.`


## Corrección v1.1.3 — Activación unificada

Omega ya posee un estado oficial para cada módulo:
`cms_modules.is_enabled`.

Las versiones anteriores de Name Effects también tenían una opción interna:
`config_json.enabled`.

Eso podía producir:
- módulo = Activo
- servicio interno = Desactivado

y mostrar:
`No fue posible cargar el módulo — El servicio está desactivado.`

Desde v1.1.3 se elimina la segunda activación.

Ahora:
- Si `AdminCP -> Módulos -> Name Effects` está ACTIVO, el servicio funciona.
- Si el módulo está INACTIVO, Omega ni siquiera ejecuta sus hooks.

Los valores viejos `enabled=false` que hayan quedado en `config_json` son ignorados.


## v1.1.4 — Cache-safe para LiteSpeed / OPcache

Esta versión usa:
- `Module.php` como loader mínimo;
- `Module_v114.php` como implementación real.

El nombre nuevo de implementación evita que un OPcache agresivo pueda reutilizar
bytecode de versiones anteriores.

También agrega la acción administrativa:
`Verificar runtime v1.1.4`

Si devuelve:
`Runtime Name Effects v1.1.4 activo...`
queda confirmado que el servidor ejecuta el código correcto.
