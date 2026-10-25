# Guild Ranking Professional v3.0.0

Vista profesional opcional para Omega MU CMS.

## Requisito recomendado

Instalar también:

`Guild Ranking Basic`

Entonces en:

```text
/rankings?type=guilds
```

aparecerán:

```text
🏰 Básico
✨ Profesional
```

La opción Profesional solo aparece cuando este módulo está instalado y activo.

## Administración

Desde:

```text
AdminCP → Módulos → Guild Ranking Professional → Configurar
```

puedes definir:

- cantidad máxima de clanes;
- orden por defecto;
- mostrar/ocultar Guild Score;
- mostrar/ocultar Notice;
- mostrar/ocultar preview de miembros.

## Mi Cuenta

No registra `user_menu`, por lo que nunca aparece en Mi Cuenta.

## Público

No requiere autenticación.

Ruta:

```text
/rankings?type=guilds&view=professional
```

## READ ONLY

Consulta:

- Guild
- GuildMember
- Character
