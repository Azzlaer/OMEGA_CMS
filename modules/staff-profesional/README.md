# Staff Profesional v2.0.0 — OmegaCMS

Adaptación nativa de WebEngine Staff Profesional v2.0.2 Robust Fix.

## Funciones conservadas

- Miembros de staff vinculados a personajes reales.
- Rango y color.
- Departamento.
- Descripción y biografía.
- Avatar individual y avatar por defecto.
- País, idiomas, zona horaria y horario.
- Estado público: disponible, ocupado, ausente, fuera de servicio.
- Discord, WhatsApp, teléfono, email y perfil web.
- Mostrar/ocultar cada medio de contacto.
- Mostrar/ocultar estadísticas.
- Mostrar clan.
- Mostrar estado Online.
- Miembros destacados.
- Orden configurable.
- Búsqueda.
- Filtro por rango.
- Filtro por departamento.
- Cargos vacantes.
- Fondo y cabecera configurables.

## Integración MuEmu 0.97k

Los datos se leen en tiempo real desde MuOnline97:

Character:
- Name
- AccountID
- Class
- cLevel
- ResetCount
- GrandResetCount
- PkLevel
- MapNumber
- MapPosX
- MapPosY

GuildMember:
- Name
- G_Name

Guild:
- G_Name
- G_Master
- G_Score

MEMB_STAT:
- memb___id
- ConnectStat

## Tablas propias Omega

- cms_staff_settings
- cms_staff_members

Se crean automáticamente.

## Página del módulo

OmegaCMS actualmente expone las páginas de módulos mediante `user.page`, por eso
la página aparece en:

Mi Cuenta → Staff Oficial

El hook ya retorna el formato exacto requerido por Omega:

[
  'title' => 'Nuestro Equipo',
  'html' => '...'
]

## Assets

- /module-assets/staff-profesional/assets/css/staff.css
- /module-assets/staff-profesional/assets/js/staff.js
- /module-assets/staff-profesional/assets/images/default-avatar.png
