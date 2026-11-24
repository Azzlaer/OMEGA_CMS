# Evento TvT PRO

Módulo de estadísticas Team vs Team para OMEGA CMS.

## Ubicación

TvT no aparece en el menú de perfil ni en Mi Cuenta.

Se integra directamente en:

**Rankings → Eventos → TvT**

URL:

`/rankings?type=tvt`

La pestaña solo aparece cuando el módulo `event-tvt-pro` está activado.

## Fuente de datos

El módulo trabaja en modo READ ONLY sobre:

- `dbo.TvTMatch`
- `dbo.TvTResult`
- `dbo.TvTAction`
- `dbo.Character` únicamente para cargar los personajes de la cuenta autenticada.

No crea ni modifica estas tablas.

## Funciones

- Resumen global de partidas, jugadores, kills, objetivos y Zen.
- Ranking acumulado por personaje.
- Win/Loss y Win Rate.
- K/D basado en las acciones resumen `KILLS` y `DEATHS`.
- Objetivos destruidos.
- Zen obtenido por `REWARD_ZEN`.
- Lista de partidas recientes.
- Detalle por `SessionKey`.
- Equipos BLUE/RED derivados de `ENTRY.Detail`.
- Cronología completa de acciones.
- Perfil individual de cada participante.
- Diagnóstico AdminCP de tablas/columnas.

## Mis personajes

Desde la configuración del módulo en AdminCP existe:

**Mostrar selector «Mis personajes»**

Cuando está activado y el visitante inició sesión, Rankings → TvT muestra un selector con los personajes cuya columna `Character.AccountID` pertenece a su cuenta.

Al seleccionar un personaje, OMEGA muestra su perfil TvT:

- partidas;
- victorias;
- derrotas;
- Win Rate;
- kills;
- deaths;
- K/D;
- objetivos;
- recompensas;
- historial reciente.

La opción puede desactivarse desde AdminCP sin afectar el ranking público TvT.

## Interpretación

`KILLS` y `DEATHS` se usan para estadísticas acumuladas.

`KILL` y `DEATH` se conservan para la cronología y no se suman nuevamente, evitando duplicar bajas.

El ganador se obtiene prioritariamente de `TvTResult.Result='WIN'`.

## Seguridad

- Solo SELECT sobre datos del GameServer.
- Parámetros preparados.
- SessionKey limitada a 64 caracteres.
- CharacterName limitada a 11 caracteres.
- El selector «Mis personajes» solo acepta nombres obtenidos desde la cuenta autenticada.
- Escapado HTML en toda salida dinámica.
