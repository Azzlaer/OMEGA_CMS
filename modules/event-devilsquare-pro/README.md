# Evento Devil Square PRO

Extensión visual y administrativa de Devil Square para OMEGA CMS.

## Ubicación

**Rankings → Eventos → Devil Square**

## Fuente de datos

Se conserva el ranking oficial existente:

- `dbo.RankingDevilSquare`
- `Driver::eventRanking('devilsquare')`
- `Driver::eventRankingSummary('devilsquare')`

El módulo solo controla la presentación.

## Cabecera

Modos exclusivos:

- Diseño Devil Square
- Banner personalizado
- Sin cabecera

## Ajuste de imagen

- Rellenar / cover
- Encajar / contain
- Estirar / fill
- Tamaño natural / scale-down
- posición configurable
- altura configurable entre 160 y 720 px

## Banner

JPG, PNG o WEBP, máximo 8 MB.

Ruta:

`/public/uploads/event-devilsquare-pro/`

## Persistencia

`dbo.cms_devilsquare_module_settings`
