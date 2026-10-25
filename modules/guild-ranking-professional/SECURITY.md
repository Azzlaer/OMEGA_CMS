# Security

Guild Ranking Professional Override v2.0.0 es un módulo exclusivamente
de lectura.

## Tablas consultadas

- Guild
- GuildMember
- Character

## No realiza

- INSERT
- UPDATE
- DELETE

sobre datos del GameServer.

## Fallback

Si faltan tablas/columnas o el módulo no puede preparar la vista profesional,
el hook no reemplaza el contenido y Omega conserva el ranking clásico.

Esto evita dejar `/rankings?type=guilds` inutilizable.
