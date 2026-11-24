# Secure Roulette PRO v2

Evolución del módulo `secure-roulette` de OMEGA CMS, tomando como referencia funcional y de seguridad el plugin **MUCore Ruleta Secure v1.1.0** suministrado para análisis.

## Qué se conserva del enfoque seguro de MUCore

El plugin de referencia corrigió correctamente varios problemas de una versión anterior:

- el navegador no decide el premio;
- el navegador no envía AccountID con autoridad;
- el servidor usa la cuenta autenticada;
- el premio se decide con `random_int()`;
- cooldown validado en servidor;
- CSRF;
- protección contra doble submit / concurrencia;
- la animación es solamente visual.

OMEGA mantiene esos principios, pero reemplaza el estado en archivos JSON/`flock()` por bloqueo transaccional SQL Server con `UPDLOCK,HOLDLOCK`.

## Mejoras de OMEGA v2

### Probabilidades ponderadas

Cada uno de los 8 slots tiene:

- tipo de premio;
- cantidad;
- peso.

La probabilidad real es:

`peso_del_slot / suma_de_todos_los_pesos`

Peso 0 desactiva una casilla.

La selección se realiza exclusivamente en PHP con `random_int(1, totalWeight)`.

### Economía de giro

El administrador puede configurar un costo opcional por giro usando:

- WCoinC
- WCoinP
- GoblinPoint
- Credits

Costo y premio forman parte de la misma transacción.

Si el usuario no tiene saldo suficiente, el giro no ocurre.

### Límites

- cooldown configurable;
- anti doble-submit técnico cuando cooldown=0;
- máximo de giros diarios;
- cuenta offline opcional;
- campaña con fecha de inicio y fin.

### Auditoría

`cms_secure_roulette_log` registra:

- cuenta;
- slot;
- tipo de premio;
- cantidad;
- peso configurado al momento del giro;
- moneda y costo del giro;
- hash SHA-256 de IP;
- fecha UTC.

La IP nunca se almacena en texto plano.

### Compatibilidad

Backends de premios/costo:

- `CashShopData.WCoinC`
- `CashShopData.WCoinP`
- `CashShopData.GoblinPoint`
- `memb_credits.credits`

El panel diagnostica si cada backend existe.

### UI y CSP

La rueda sigue siendo únicamente una representación visual.

La animación JavaScript se movió a:

`assets/roulette-pro.js`

para respetar CSP estricto y evitar scripts inline.

## Migración desde 1.0.2

La migración es automática.

Si `cms_secure_roulette_log` ya existe, OMEGA agrega cuando faltan:

- `reward_weight`
- `cost_type`
- `cost_amount`
- `client_ip_hash`

No elimina el historial anterior.

## Configuración recomendada inicial

Para una ruleta gratuita diaria:

- `enabled = true`
- `cooldown_minutes = 1440`
- `daily_spin_limit = 1`
- `spin_cost_type = None`
- `spin_cost_amount = 0`

Para probabilidades iguales:

- `slot1_weight ... slot8_weight = 100`

Para un premio raro:

- premios comunes = 100
- premio raro = 5

Los pesos son relativos, no porcentajes directos.

## Seguridad heredada y reforzada

El navegador nunca envía con autoridad:

- AccountID;
- premio;
- cantidad;
- peso;
- costo;
- slot ganador.

OMEGA valida CSRF en el core y el giro usa una transacción SQL con `UPDLOCK,HOLDLOCK`.

La entrega real ocurre antes de la animación.
