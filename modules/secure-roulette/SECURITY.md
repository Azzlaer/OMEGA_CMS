# Security Model

Secure Roulette está diseñado bajo una regla:

> El cliente puede mentir; el servidor no debe confiar en él.

## Autoridad

La única identidad válida es:

`payload['account_id']`

inyectada por Omega después de autenticar la sesión.

No existe campo HTML para AccountID.

## Resultado

No existe campo HTML para premio.

El servidor selecciona el slot.

## Cooldown

Se valida dentro de la misma transacción que acredita el premio.

## Doble solicitud

El estado de la cuenta se lee con:

`UPDLOCK,HOLDLOCK`

evitando que dos transacciones de la misma cuenta puedan completar el giro
simultáneamente.

## Auditoría

Cada premio queda persistido en:

`cms_secure_roulette_log`

## Limitación honesta

Ningún sitio puede impedir que el usuario abra DevTools.

La protección consiste en que DevTools no tenga autoridad sobre operaciones
sensibles del servidor.
