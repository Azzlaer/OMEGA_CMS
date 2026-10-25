# Duel History Professional v1.0.1

Módulo instalable para Omega MU CMS.

## Instalación

1. AdminCP → Módulos.
2. Instalar módulo.
3. Seleccionar `Omega-Module-Duel-History-Professional-v1.0.1.zip`.
4. Activar el módulo.
5. El usuario verá `⚔️ Duel History` dentro de Mi Cuenta.

No debes reemplazar archivos del CMS.

## Tablas utilizadas

Solo lectura:

- `Character`
- `DuelHistory`
- `DuelRanking`

La estructura detectada en la base MuOnline97 incluye para DuelHistory:

- WinnerAccount
- WinnerName
- LoserAccount
- LoserName
- WinnerClass
- LoserClass
- WinnerLevel
- LoserLevel
- WinnerReset
- LoserReset
- ArenaID
- MapNumber
- DurationSeconds
- WinnerKills
- LoserKills
- KillsToWin
- FinishReason
- DuelDate

El módulo detecta las columnas en runtime y tolera la ausencia de campos opcionales.

## Mi Cuenta

Muestra:

- total de duelos;
- victorias;
- derrotas;
- Win Rate;
- K/D;
- filtro por personaje de la propia cuenta;
- tarjetas DuelRanking;
- rival;
- marcador;
- niveles;
- mapa y arena;
- duración;
- motivo de finalización;
- fecha;
- duración promedio.

No muestra las cuentas de los rivales.

## AdminCP

AdminCP → Módulos → Duel History Professional

Incluye diagnóstico:

- DuelHistory disponible/no disponible;
- DuelRanking disponible/no disponible;
- cantidad de registros;
- comprobación de columnas esperadas;
- estado READ ONLY.

## Configuración

Desde el detalle del módulo:

- cantidad máxima de duelos (10–200);
- mostrar mapa/arena;
- mostrar duración;
- mostrar motivo de finalización;
- mostrar estadísticas de DuelRanking.

## Seguridad

El módulo es READ ONLY.

No realiza:

- INSERT
- UPDATE
- DELETE

sobre las tablas del GameServer.

Los datos mostrados pertenecen únicamente a la cuenta autenticada.


## v1.0.1 — ODBC / PDO_SQLSRV Fix

Se corrigió `SQLSTATE[07002]: COUNT field incorrect or syntax error`.

La causa era la reutilización del mismo parámetro nombrado más de una vez
dentro de una sentencia SQL, algo que PDO_SQLSRV/ODBC Driver 18 puede rechazar.

Antes:

```sql
WinnerAccount = :account OR LoserAccount = :account
```

Ahora:

```sql
WinnerAccount = :account_winner
OR LoserAccount = :account_loser
```

La misma corrección se aplicó a:
- filtro por personaje;
- filtro por AccountID;
- fallback por lista de personajes.
