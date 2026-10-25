# Guild Directory Professional v1.0.0

Módulo instalable para Omega MU CMS.

## Instalación

1. AdminCP → Módulos.
2. Instalar módulo.
3. Seleccionar:
   `Omega-Module-Guild-Directory-Professional-v1.0.0.zip`
4. Activar el módulo.
5. Aparecerá `🏰 Clanes` dentro de Mi Cuenta.

## Datos mostrados

Por cada clan:

- Logo / Guild Mark real
- Nombre del clan
- Guild Master
- Guild Notice
- Guild Score
- Cantidad de miembros
- Total acumulado de Reset
- Promedio de Reset por miembro
- Hasta 6 integrantes destacados

## Cálculo de Total Resets

El módulo utiliza:

```sql
SUM(Character.ResetCount)
```

para todos los personajes que pertenecen al clan a través de:

```text
GuildMember.Name -> Character.Name
GuildMember.G_Name -> Guild.G_Name
```

Por lo tanto, si un clan tiene:

- Personaje A = 10 resets
- Personaje B = 5 resets
- Personaje C = 20 resets

el clan mostrará:

```text
Total Resets = 35
```

## Guild Mark

El módulo incluye un renderer para el formato clásico MU:

- G_Mark
- 32 bytes
- 8x8 píxeles
- 4 bits por píxel
- 16 colores

Se representa directamente con HTML/CSS, sin crear archivos temporales.

## Filtros

- búsqueda por nombre de clan;
- Guild Master;
- Notice;
- ordenar por Total Resets;
- ordenar por miembros;
- ordenar por Guild Score;
- ordenar alfabéticamente.

## Estadísticas globales

- total de clanes;
- total de miembros;
- resets acumulados entre todos los clanes;
- resets del clan líder.

## Base de datos

READ ONLY:

- Guild
- GuildMember
- Character

No modifica ninguna tabla.
