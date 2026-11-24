# MarrySystem PRO

Sistema de Casamientos para OMEGA CMS.

## Ubicación

**Rankings → Sistemas → Casamientos**

URL:

`/rankings?type=marrysystem`

## Tablas del GameServer

Modo READ ONLY sobre:

- `dbo.MarriageCouples`
- `dbo.MarriageRequests`
- `dbo.MarriageHistory`
- `dbo.MarriageAudit`
- `dbo.Character` para resolver actividad personal de la cuenta autenticada.

## Funciones

- parejas activas;
- total de matrimonios;
- divorcios;
- solicitudes pendientes;
- solicitudes aprobadas;
- historial reciente;
- estado personal de la cuenta;
- diagnóstico AdminCP;
- enlaces a perfiles de personajes;
- no expone AccountID públicamente.

## Banner

Mismos modos visuales que los eventos PRO:

- Diseño MarrySystem
- Banner personalizado
- Sin cabecera

Ajuste:

- cover
- contain
- fill
- scale-down

También permite:

- posición;
- altura 160–720 px;
- preview;
- reemplazo;
- eliminación.

Ruta:

`/public/uploads/marrysystem-pro/`

## Persistencia CMS

`dbo.cms_marrysystem_settings`

La configuración del CMS nunca modifica las tablas del MarrySystem.
