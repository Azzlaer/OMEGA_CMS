# Secure Roulette v1.0.2 — Omega Web

Módulo instalable para Omega MU CMS.

## Instalación

1. AdminCP → Módulos.
2. Instalar módulo.
3. Seleccionar `Omega-Module-Secure-Roulette-v1.0.2.zip`.
4. Activar.
5. Configurar premios y cooldown.
6. Aparecerá `🎰 Ruleta Segura` en Mi Cuenta.

No debes reemplazar archivos del core.

## Seguridad

El navegador NO envía:

- AccountID
- userid
- tipo de premio
- cantidad de premio
- slot ganador

El módulo recibe el `account_id` exclusivamente desde la sesión autenticada
que Omega pasa a `user.page`.

El premio se decide con:

```php
random_int(1, 8)
```

en el servidor.

## CSRF

Omega ya valida `_csrf` antes de ejecutar un POST de:

`/account/modules/{slug}`

El módulo usa el token entregado por el core.

## Concurrencia

Se utiliza una transacción SQL y:

```sql
WITH (UPDLOCK,HOLDLOCK)
```

sobre `cms_secure_roulette_state`.

La operación crítica incluye:

1. bloquear estado;
2. comprobar cooldown;
3. elegir premio;
4. acreditar saldo;
5. actualizar cooldown;
6. registrar historial;
7. commit.

## Tablas creadas automáticamente

### cms_secure_roulette_state

- account_id
- last_spin_at
- spin_count
- updated_at

### cms_secure_roulette_log

- id
- account_id
- slot_no
- reward_type
- reward_amount
- created_at

## Backends soportados

### CashShopData

- AccountID
- WCoinC
- WCoinP
- GoblinPoint

### memb_credits

- memb___id
- credits

El panel administrativo diagnostica si esos campos existen.

## Premios

8 posiciones configurables.

Tipos:

- WCoinC
- WCoinP
- GoblinPoint
- Credits

Cantidad máxima admitida por el módulo:

`1.000.000`

## Importantísimo

Modificar con Chrome DevTools:

- HTML
- CSS
- JavaScript
- rueda
- ángulo
- texto
- formulario visual

NO permite cambiar el premio real.

El premio se selecciona y acredita en SQL antes de que el navegador muestre
la animación ganadora.


## v1.0.2 — PHP 8.2 Compatibility Fix

Se eliminó:

```php
use RuntimeException;
```

porque `Module.php` se ejecuta en el namespace global y PHP 8.2 mostraba:

```text
The use statement with non-compound name 'RuntimeException' has no effect
```

Ahora las excepciones se referencian explícitamente como:

```php
\RuntimeException
```

No cambia la lógica ni la seguridad del módulo.


## v1.0.2 — SQL Server DATEADD / ODBC Fix

Se corrigió:

```text
SQLSTATE[42000]:
Argument data type nvarchar is invalid for argument 2 of dateadd function.
```

Con PDO_SQLSRV/ODBC Driver 18, los parámetros nombrados pueden llegar a SQL Server
como `nvarchar`.

Antes:

```sql
DATEADD(MINUTE,:cooldown,last_spin_at)
DATEADD(SECOND,:cooldown,CAST(:last_spin AS DATETIME2))
```

Ahora:

```sql
DATEADD(MINUTE,CAST(:cooldown AS INT),last_spin_at)
DATEADD(SECOND,CAST(:cooldown AS INT),CAST(:last_spin AS DATETIME2))
```

La corrección se aplicó tanto al cálculo visual del cooldown como a la validación
interna dentro de la transacción del giro.
