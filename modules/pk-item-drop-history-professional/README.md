# PK Item Drop History Professional v1.0.0

Módulo instalable para Omega MU CMS.

## Instalación

1. AdminCP → Módulos.
2. Instalar módulo.
3. Seleccionar `Omega-Module-PK-Item-Drop-History-Professional-v1.0.0.zip`.
4. Activar.
5. Aparecerá `☠️ PK Drop History` dentro de Mi Cuenta.

No es necesario reemplazar ni recompilar OmegaCMS.

## Base de datos

Tablas usadas en modo READ ONLY:

- `PKItemDropHistory`
- `Character`

El módulo inspecciona `INFORMATION_SCHEMA.COLUMNS` y adapta los nombres de columnas
cuando distintas builds usan variantes.

Campos compatibles/detectables:

- VictimName / Victim / CharacterName
- KillerName / Killer
- VictimAccount / VictimAccountID / AccountID
- KillerAccount / KillerAccountID
- ItemName
- ItemSection / ItemType / Section
- ItemIndex / ItemID / ItemCode
- ItemLevel
- ItemExcellent / ExcellentOption
- ItemLuck
- ItemSkill
- ItemOption / AdditionalOption
- MapNumber / Map
- MapX / PosX / X
- MapY / PosY / Y
- ItemHex / ItemData / ItemBytes
- DropDate / CreatedAt / EventDate / LogDate

## Mi Cuenta

Muestra:

- total histórico de drops PK de la cuenta;
- filtro por personaje;
- víctima;
- killer;
- item perdido;
- level;
- skill;
- luck;
- excellent;
- additional option;
- mapa;
- coordenadas;
- fecha;
- Item HEX opcional;
- AccountID opcional y desactivado por defecto.

## AdminCP

AdminCP → Módulos → PK Item Drop History Professional

Incluye:

- estado de `PKItemDropHistory`;
- total de registros;
- cantidad de columnas;
- diagnóstico campo por campo;
- indicador READ ONLY.

## Configuración

- cantidad de registros: 10–200;
- mostrar Item HEX;
- mostrar mapa/posición;
- mostrar AccountID;
- mostrar detalles del item.

## Seguridad

Este módulo NO:

- restaura items;
- elimina registros;
- mueve objetos;
- modifica inventarios;
- modifica Character;
- ejecuta INSERT/UPDATE/DELETE.

Solo muestra datos correspondientes a personajes de la cuenta autenticada.
