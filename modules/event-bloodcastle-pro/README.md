# Evento Blood Castle PRO

Extensión visual y administrativa de Blood Castle para OMEGA CMS.

## Ubicación

**Rankings → Eventos → Blood Castle**

## Fuente de datos

Se conserva el ranking oficial existente:

- `dbo.RankingBloodCastle`
- `Driver::eventRanking('bloodcastle')`
- `Driver::eventRankingSummary('bloodcastle')`

El módulo solo controla la presentación.

## Cabecera

Modos exclusivos:

- Diseño Blood Castle
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

`/public/uploads/event-bloodcastle-pro/`

## Persistencia

`dbo.cms_bloodcastle_module_settings`
