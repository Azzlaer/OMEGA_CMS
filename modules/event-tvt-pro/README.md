# Evento TvT PRO

Módulo de estadísticas Team vs Team para OMEGA CMS.

## Fuente de datos

El módulo trabaja en modo READ ONLY sobre:

- `dbo.TvTMatch`
- `dbo.TvTResult`
- `dbo.TvTAction`

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

## Interpretación

`KILLS` y `DEATHS` se usan para estadísticas acumuladas porque son registros resumen del jugador.

`KILL` y `DEATH` se conservan para la cronología detallada y no se vuelven a sumar, evitando duplicar bajas.

El ganador se obtiene prioritariamente de `TvTResult.Result='WIN'`. Esto permite mostrar correctamente partidas donde el GameServer ya escribió resultados aunque `TvTMatch.Status` todavía permanezca `ACTIVE` o `EndedAt` sea NULL.

## Menú

El módulo aparece en Mi Cuenta como:

**Eventos · TvT**

## Seguridad

- Solo SELECT.
- Parámetros preparados.
- SessionKey limitada a 64 caracteres.
- CharacterName limitada a 11 caracteres.
- Escapado HTML en toda salida dinámica.
