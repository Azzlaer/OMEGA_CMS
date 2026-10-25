# VIP Membership Pro v1.0.1 — OmegaCMS

Adaptación nativa del módulo WebEngine VIP Membership Pro v1.1.2 Native Credits Fix.

## Qué conserva

- Planes VIP configurables.
- VIP Type.
- Duración en días.
- Costos y descuentos.
- Tarjetas visuales.
- Iconos y estilos.
- Botón "Ver información".
- BBCode seguro.
- Compra desde Mi Cuenta.
- Cuenta offline obligatoria opcional.
- Cambio de tipo VIP configurable.
- Historial de compras.
- Activación directa en MEMB_INFO.AccountLevel / AccountExpireDate.
- Extensión del tiempo si se compra nuevamente el mismo tipo VIP.

## Diferencia importante: créditos

WebEngine v1.1.2 usa su clase interna `CreditSystem`.

OmegaCMS no posee esa clase.

Esta adaptación utiliza un adaptador explícito con dos backends:

1. Tabla de créditos:
   - tabla
   - columna de cuenta
   - columna de saldo

2. WCoinC:
   - CashShopData
   - AccountID
   - WCoinC

Por defecto queda preparado para:

MEMB_CREDITS
- memb___id
- credits

Si tu base no tiene esa tabla, cambia el backend/configuración desde AdminCP antes de habilitar compras.

## Seguridad

La compra:
- comprueba saldo
- exige cuenta offline si está configurado
- bloquea la fila de créditos con UPDLOCK/HOLDLOCK
- descuenta saldo y acredita VIP dentro de la misma transacción del MuOnline DB
- hace rollback completo si falla cualquier paso
- registra éxito/fallo en el log CMS
- usa parámetros PDO
- valida nombres de tablas/columnas mediante whitelist

## Tablas Omega

Se crean automáticamente:

- cms_vip_pro_settings
- cms_vip_pro_plans
- cms_vip_pro_log

## Configuración MuEmu 0.97k recomendada

VIP:
- MEMB_INFO
- memb___id
- AccountLevel
- AccountExpireDate

Estado:
- MEMB_STAT
- memb___id
- ConnectStat

## Importante

Antes de permitir compras ejecuta "Diagnóstico de tablas" desde el panel del módulo.


## Hotfix v1.0.1 — Página de usuario

OmegaCMS exige que el hook `user.page` retorne un array:

```php
[
    'title' => 'Membresías VIP',
    'html'  => $html
]
```

La v1.0.0 retornaba solamente el HTML como string. Por eso Omega mostraba:

`El módulo no ofrece una página de usuario.`

Esta versión corrige el contrato exacto de `ModuleRuntime`.
