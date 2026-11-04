# Ω OMEGA CMS

**OMEGA CMS** es un CMS modular orientado a servidores privados de **MU Online**, desarrollado principalmente para **MuEmu 0.97k Kayito** y su ecosistema de SQL Server.

El proyecto busca ofrecer una plataforma moderna para sitio público, cuentas, personajes, rankings, clanes, inventario, mercado, contenido, administración, seguridad, monetización y módulos adicionales, manteniendo una arquitectura extensible y compatible con instalaciones locales **XAMPP/Windows** y entornos web compatibles con PHP + SQL Server.

> **Core declarado actualmente:** `0.18.0`  
> **Driver principal:** MuEmu 0.97k Kayito  
> **Base de datos:** Microsoft SQL Server  
> **Rama estable esperada:** `main`

---

## Índice

- [Características principales](#características-principales)
- [Arquitectura](#arquitectura)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración .env](#configuración-env)
- [Seguridad](#seguridad)
- [AdminCP](#admincp)
- [Centro de Guías](#centro-de-guías)
- [Rankings MU Online](#rankings-mu-online)
- [Visual Design Studio](#visual-design-studio)
- [Google & Monetization Center PRO](#google--monetization-center-pro)
- [Módulos incluidos](#módulos-incluidos)
- [Migraciones y tablas CMS](#migraciones-y-tablas-cms)
- [Reparaciones y mejoras importantes](#reparaciones-y-mejoras-importantes)
- [Actualización y mantenimiento](#actualización-y-mantenimiento)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Créditos](#créditos)
- [Aviso legal](#aviso-legal)

---

## Características principales

OMEGA CMS incluye actualmente, entre otras funciones:

- Instalador web para SQL Server.
- Conexiones separadas para **base MU** y **base CMS**.
- Driver dedicado a **MuEmu 0.97k Kayito**.
- Registro e inicio de sesión usando cuentas reales de MU.
- Panel de usuario.
- Administración de cuentas y permisos.
- AdminCP Enterprise con navegación lateral.
- Noticias.
- Centro de Guías HTML.
- Rankings de personajes, clases, eventos y clanes.
- Blood Castle y Devil Square enriquecidos con datos del personaje.
- Perfiles públicos.
- Estado online.
- Descargas.
- Changelog.
- Gestión visual del sitio.
- Biblioteca multimedia.
- Sistema modular instalable.
- Marketplace.
- Inventario y baúl.
- Membresías VIP.
- Ruleta segura.
- Historial de duelos.
- Historial de drops PK.
- Staff público.
- Efectos visuales.
- Google Analytics / Ads / AdSense / GTM.
- `ads.txt` dinámico.
- Consentimiento de cookies.
- hCaptcha.
- CSRF.
- Rate limiting.
- Content Security Policy.
- Sesiones seguras.
- Utilidades de diagnóstico de base de datos.

---

# Arquitectura

OMEGA CMS utiliza un núcleo PHP propio, sin depender de frameworks externos pesados.

Las áreas principales son:

### `app/Core/`

Contiene servicios fundamentales como:

- Environment / `.env`
- Database
- Session
- Security
- CSRF
- RateLimiter
- Router / View
- Module Manager
- Runtime de módulos
- Versionado

### `app/GameServer/`

Contiene la integración con el servidor MU.

Actualmente el driver principal es:

```text
app/GameServer/Drivers/MuEmu097kKayito/
```

El driver conoce la estructura del GameServer y permite consultar personajes, cuentas, guilds, rankings y otros datos sin acoplar todo el CMS directamente a SQL.

### `app/Services/`

Servicios de negocio del CMS, entre ellos:

- AccountAuth
- AdminAccess
- AdminManager
- ModulePackageManager
- ModuleRuntime
- VisualAssetManager
- ChangelogManager
- CharacterNameRenderer
- RankingExclusionManager
- DatabaseFeatureCenter
- MyJewelBank
- SocialProfileManager
- DesignStudio
- GuildRankingProfessional
- GuideManager
- MonetizationCenter
- HCaptchaService

### `modules/`

Módulos independientes que extienden funciones públicas, administrativas o del panel de usuario.

### `templates/`

Plantillas públicas y administrativas.

### `public/`

Front controller, CSS, JavaScript, imágenes y uploads públicos.

### `database/migrations/`

Migraciones SQL del núcleo y subsistemas.

### `storage/`

Datos internos, caché, backups, staging y archivo de instalación.

---

# Requisitos

El instalador comprueba:

- **PHP 8.2 o superior**
- PDO
- OpenSSL
- JSON
- Sessions
- Directorio `storage/` escribible
- Permiso para crear/escribir `.env`
- Driver PDO para SQL Server:
  - **Windows/XAMPP:** `pdo_sqlsrv`
  - **Linux/cPanel:** `pdo_dblib` / FreeTDS

Para hCaptcha también se requiere:

- PHP **cURL**

## Windows / XAMPP recomendado

- Apache 2.4+
- PHP 8.2+
- Microsoft ODBC Driver 18 for SQL Server
- Microsoft Drivers for PHP for SQL Server
- Extensiones:
  - `pdo_sqlsrv`
  - `sqlsrv`
  - `curl`
  - `openssl`

---

# Instalación

## 1. Copiar el proyecto

En XAMPP, por ejemplo:

```text
C:\xampp\htdocs\OMEGA_CMS
```

o en una ruta equivalente de tu instalación.

## 2. Preparar SQL Server

OMEGA necesita acceso a:

1. La base de datos del servidor MU.
2. Una base de datos para el CMS.

El instalador puede crear la base CMS si el usuario SQL utilizado tiene permisos suficientes.

## 3. Abrir el instalador

Visita:

```text
http://localhost/OMEGA_CMS/
```

Mientras no exista:

```text
storage/installed.lock
```

OMEGA redirigirá al instalador.

## 4. Validación del esquema MU

El instalador comprueba tablas y columnas esenciales como:

- `AccountCharacter`
- `Character`
- `MEMB_INFO`
- `MEMB_STAT`
- `Guild`
- `GuildMember`
- `warehouse`
- `ResetInfo`
- `RankingBloodCastle`
- `RankingDevilSquare`

## 5. Crear administrador

La cuenta administrativa principal está vinculada a una cuenta MU real.

El instalador puede crear o asociar la cuenta correspondiente en:

```text
dbo.MEMB_INFO
```

El SuperAdmin queda registrado en la base CMS.

## 6. Finalización

Cuando la instalación termina se genera:

```text
.env
storage/installed.lock
```

No elimines `installed.lock` en producción salvo que realmente necesites volver al flujo de instalación.

---

# Configuración .env

OMEGA genera automáticamente las variables principales de conexión.

Ejemplo orientativo:

```env
APP_NAME="Omega MU"
APP_ENV=production
APP_DEBUG=false
APP_URL="https://example.com"
APP_TIMEZONE="America/Santiago"
APP_KEY="base64:..."

MU_DB_DRIVER=sqlsrv
MU_DB_HOST="127.0.0.1"
MU_DB_PORT=1433
MU_DB_DATABASE="MuOnline97"
MU_DB_USERNAME="usuario"
MU_DB_PASSWORD="..."
MU_DB_TRUST_CERT=true
MU_DB_ENCRYPT=false

CMS_DB_DRIVER=sqlsrv
CMS_DB_HOST="127.0.0.1"
CMS_DB_PORT=1433
CMS_DB_DATABASE="OmegaCMS"
CMS_DB_USERNAME="usuario"
CMS_DB_PASSWORD="..."
CMS_DB_TRUST_CERT=true
CMS_DB_ENCRYPT=false
```

> Nunca subas un `.env` real con credenciales al repositorio.

---

# Seguridad

OMEGA incluye distintas capas de protección.

## CSRF

Las operaciones sensibles usan token CSRF.

## Rate Limiting

El sistema limita operaciones sensibles y autenticaciones para reducir ataques automatizados.

## Sesiones

Se utiliza un sistema de sesión propio con regeneración y controles de acceso.

## Content Security Policy

OMEGA utiliza CSP para restringir scripts, frames, conexiones y contenido externo.

Los dominios de Google y hCaptcha se añaden únicamente cuando sus integraciones están habilitadas.

## hCaptcha Security System

hCaptcha protege actualmente:

- Login.
- Registro.

La recuperación está preparada para utilizar la misma capa cuando el sistema SMTP/recovery quede activo.

Configuración:

```env
HCAPTCHA_ENABLED=true
HCAPTCHA_SITEKEY=TU_SITEKEY
HCAPTCHA_SECRET=TU_SECRET
```

Opcionalmente:

```env
HCAPTCHA_LOGIN=true
HCAPTCHA_REGISTER=true
HCAPTCHA_RECOVERY=false
```

Si las variables específicas no existen, **Login y Registro se protegen automáticamente** al activar `HCAPTCHA_ENABLED`.

La Secret:

- Nunca se envía al navegador.
- Nunca se muestra desde AdminCP.
- Se usa únicamente para verificar el token mediante `api.hcaptcha.com/siteverify`.

El AdminCP dispone de un diagnóstico que informa:

- Estado del servicio.
- Presencia de claves.
- Disponibilidad de PHP cURL.
- Formularios protegidos.
- Estado general.

Documentación adicional:

```text
docs/HCAPTCHA_LOGIN.md
```

---

# AdminCP

El panel administrativo está pensado como centro de operaciones del CMS.

Incluye grupos principales:

### Operación

- Dashboard
- Cuentas
- Exclusión de rankings

### Contenido

- Noticias
- Guías
- Changelog
- Descargas

### Sistemas

- Módulos
- DB Feature Center
- Iconos y variables visuales

### Plataforma

- Visual Design Studio
- Google & Monetization
- Acceso administrativo
- Configuración

El sidebar utiliza un patrón de acordeón inspirado en el panel **Panel_Valheim_Pro**, adaptado al CSP de OMEGA mediante JavaScript externo.

Incluye:

- Grupos desplegables.
- Búsqueda de secciones.
- Atajo `/`.
- Modo compacto.
- Persistencia mediante localStorage.
- Estado activo.
- Diseño responsive.
- Logout seguro mediante POST + CSRF.

---

# Centro de Guías

Ruta pública:

```text
/guides
```

Cada guía dispone de:

- Título.
- Slug.
- Logo.
- Autor.
- Fecha.
- Categoría.
- Resumen.
- Contenido HTML.
- Estado borrador/publicada.
- Destacada.
- Contador de vistas.

El listado permite:

- Búsqueda.
- Filtrado por categoría.
- Guías destacadas.

Los logos pueden utilizar:

- Upload local.
- URL remota HTTPS.

El contenido HTML es sanitizado para evitar scripts y atributos peligrosos.

La tabla principal es:

```text
dbo.cms_guides
```

Migración:

```text
database/migrations/002_guides_center.sql
```

---

# Rankings MU Online

OMEGA puede trabajar con rankings de:

- Level.
- Reset.
- Grand Reset.
- Guilds.
- Blood Castle.
- Devil Square.
- Duel.
- Sky Event.
- Monster Points.
- Minigames.
- Golden Archer.
- Rankings por clase.

Clases soportadas actualmente por el driver incluyen:

- Dark Wizard.
- Soul Master.
- Dark Knight.
- Blade Knight.
- Fairy Elf.
- Muse Elf.
- Magic Gladiator.

## Blood Castle

Fuente:

```text
dbo.RankingBloodCastle
Name varchar(10)
Score int
```

## Devil Square

Fuente:

```text
dbo.RankingDevilSquare
Name varchar(10)
Score int
```

OMEGA mantiene estas tablas simples como fuente oficial y complementa datos mediante consultas a `Character` y `MEMB_STAT`.

El ranking enriquecido puede mostrar:

- Nombre.
- Clase.
- Level.
- Reset.
- Grand Reset.
- Mapa.
- Estado online.
- Score.

Los valores `Score = NULL` son tratados como cero.

También se añadieron estadísticas del evento:

- Participantes.
- Mejor score.
- Score promedio.

Las exclusiones configuradas desde AdminCP son respetadas.

---

# Visual Design Studio

El Visual Design Studio permite administrar la apariencia sin modificar manualmente CSS/PHP.

Funciones principales:

- Colores globales.
- Radius.
- Fondos.
- Overlay.
- Blur.
- Fondo fijo.
- Hero multimedia.
- Fondos específicos por sección.
- Imagen fija.
- Video.
- Colecciones aleatorias.
- Biblioteca multimedia.
- Presets.

Formatos multimedia permitidos:

### Imágenes

- JPG
- PNG
- WEBP
- GIF

Máximo: **12 MB**

### Videos

- MP4
- WEBM

Máximo: **60 MB**

## Gestión de fondos reparada

El sistema permite:

- Subir un asset.
- Aplicarlo directamente como fondo global.
- Reemplazar el fondo actual.
- Eliminar un recurso usado.
- Desasociarlo automáticamente del Hero/secciones.
- Quitar el recurso de colecciones aleatorias.
- Volver al fondo Omega por defecto al eliminar el fondo global.

Esto evita el problema histórico donde un recurso activo quedaba bloqueado y no podía eliminarse.

---

# Google & Monetization Center PRO

Centro de monetización nativo para OMEGA CMS.

No requiere WordPress ni Google Site Kit.

Incluye:

- Google Analytics 4.
- Google Ads Tag.
- Google Tag Manager.
- Google Site Verification / Search Console.
- AdSense Publisher ID.
- Auto Ads opcional.
- Bloques AdSense manuales.
- Banners propios.
- House Ads.
- HTML publicitario sanitizado.
- `ads.txt`.
- Consent Manager.
- Google Consent Mode v2.

## Placements

Actualmente existen hooks globales:

- `global_header`
- `below_navigation`
- `content_top`
- `content_bottom`
- `global_footer`

Los bloques pueden limitarse mediante reglas de rutas, por ejemplo:

```text
/guides*
/rankings
/news*
```

También pueden limitarse por:

- Invitados.
- Miembros.
- Desktop.
- Mobile.
- Fecha inicial.
- Fecha final.

## ads.txt

OMEGA puede publicar dinámicamente:

```text
/ads.txt
```

La configuración se administra desde el AdminCP.

## Consent Mode

Se manejan estados como:

- `analytics_storage`
- `ad_storage`
- `ad_user_data`
- `ad_personalization`

Documentación:

```text
docs/GOOGLE_MONETIZATION_CENTER.md
```

---

# Módulos incluidos

## Módulos Core

| Módulo | Versión | Autor | Estado inicial |
|---|---:|---|---|
| Account | 0.1.0 | Omega MU CMS | Activo |
| News | 0.1.0 | Omega MU CMS | Activo |
| Rankings | 0.1.0 | Omega MU CMS | Activo |
| Reset | 0.1.0 | Omega MU CMS | Activo |

## Duel History Professional

**Versión:** 1.0.1  
**Autor:** Omega MU CMS

Historial PvP utilizando `DuelHistory` y `DuelRanking`.

Incluye:

- Historial de enfrentamientos.
- Map/Arena.
- Duración.
- Motivo de finalización.
- Estadísticas acumuladas.
- Corrección para PDO_SQLSRV / ODBC Driver 18.

---

## Guild Directory Professional

**Versión:** 1.0.0  
**Autor:** Omega MU CMS

Directorio de clanes con:

- Logo.
- Notice.
- Guild Master.
- Miembros.
- Total de resets.
- Orden y filtros configurables.

---

## Guild Ranking Basic

**Versión:** 1.0.0  
**Autor:** Omega MU CMS

Ranking público básico de Guilds integrado en Rankings.

---

## Guild Ranking Professional

**Versión:** 3.0.0  
**Autor:** Omega MU CMS

Vista profesional de clanes con:

- Resets totales.
- Miembros.
- Guild Score.
- Notice.
- Preview de integrantes.
- Orden configurable.

---

## Inventory Viewer - Item Images Pack 1/2

**Versión:** 1.0.0  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del pack WebEngine

Incluye **474 GIF** de items.

---

## Inventory Viewer - Item Images Pack 2/2

**Versión:** 1.0.0  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del pack WebEngine

Incluye **473 GIF** adicionales.

---

## Inventario y Baúl Viewer — Kayito Sync

**Versión:** 2.0.0  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del WebEngine v1.0.7 FINAL POLISH

Características:

- Decoder sincronizado con Kayito.
- Warehouse.
- Inventario.
- Catálogo basado en `Item.txt`.
- Reconocimiento Type:Index.
- Diagnóstico de estructura.
- Integración visual con packs de items.

---

## Market Manager Professional

**Versión:** 1.0.1  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del WebEngine Market Manager v0.4.4

Incluye:

- Mercado entre jugadores.
- Warehouse de 1920 bytes.
- Publicaciones.
- Multi-moneda.
- Comisiones.
- Bóveda de entregas.
- Diagnósticos.
- Corrección de filas Warehouse NULL/no inicializadas.

---

## Name Effects Professional

**Versión:** 1.1.4  
**Crédito declarado:** LatinBattle.com / adaptación OmegaMU del módulo WebEngine

Incluye:

- 20 efectos GIF.
- Preview.
- Compra/configuración.
- Costos configurables.
- Soporte Zen.
- LevelUpPoint.
- Level.
- Reset.
- Grand Reset.
- WCoinC si existe.
- Acceso normal/VIP.
- Limpieza de efectos vencidos.
- Fixes DATEADD.
- Carga cache-safe para LiteSpeed/OPcache.

---

## PK Item Drop History Professional

**Versión:** 1.0.0  
**Autor:** Omega MU CMS

Historial de objetos perdidos por PK mediante `PKItemDropHistory`.

Puede mostrar:

- Item.
- Level.
- Excellent.
- Skill.
- Luck.
- Option.
- Mapa.
- Coordenadas.
- Datos de cuenta opcionales.

Por privacidad, la visualización de AccountID está desactivada por defecto.

---

## Rain Effect Manager Professional

**Versión:** 1.0.0  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del módulo WebEngine

Efectos visuales configurables:

- Llovizna.
- Tormenta.
- Salpicaduras.
- Relámpagos.
- Audio opcional.
- Optimización móvil.

---

## Secure Roulette

**Versión:** 1.0.2  
**Autor:** Omega MU CMS

Ruleta de premios con:

- Cooldown.
- Historial.
- WCoinC.
- WCoinP.
- GoblinPoint.
- Credits.
- Prevención de doble submit.
- Fix SQL Server/ODBC relacionado con parámetros DATEADD.

---

## Staff Profesional

**Versión:** 2.0.0  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del WebEngine Staff Profesional v2.0.2

Incluye:

- Staff vinculado a personajes reales.
- Rangos.
- Estado online.
- Estadísticas.
- Guild.
- Mapa.
- Contactos.
- Filtros.
- Administración.

---

## Title Bar Marquee Manager Professional

**Versión:** 1.0.1  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del módulo WebEngine

Animación del título de la pestaña:

- Caracteres.
- Palabras.
- Rebote.
- Sin jQuery.
- Compatible con CSP.
- Hotfix PHP 8.2.

---

## VIP Membership Pro

**Versión:** 1.0.1  
**Crédito declarado:** LatinBattle.com / adaptación OmegaCMS del módulo WebEngine v1.1.2

Incluye:

- Planes VIP.
- Descuentos.
- BBCode.
- Compra transaccional.
- Requisito de cuenta offline.
- Adaptador de créditos.
- Diagnóstico.
- Hotfix para integración con `ModuleRuntime`.

---

# Migraciones y tablas CMS

Migraciones principales:

```text
database/migrations/001_omega_cms_mssql.sql
database/migrations/002_guides_center.sql
database/migrations/003_google_monetization_center.sql
```

Entre las tablas del núcleo se encuentran:

- `cms_settings`
- `cms_admin_users`
- `cms_news`
- `cms_guides`
- `cms_audit_log`
- `cms_sessions`
- `cms_login_attempts`
- `cms_monetization_settings`
- `cms_ad_placements`

Los módulos pueden crear tablas propias mediante sus rutinas de instalación.

---

# Reparaciones y mejoras importantes

Esta sección resume cambios relevantes ya incorporados durante el desarrollo.

## Rankings Blood Castle / Devil Square

- Adaptación a:
  - `RankingBloodCastle(Name, Score)`
  - `RankingDevilSquare(Name, Score)`
- Enriquecimiento mediante Character/MEMB_STAT.
- Score NULL tratado como cero.
- Estado online.
- Datos de personaje.
- Estadísticas del evento.
- Respeto de exclusiones.

## Google & Monetization Center

- Corrección de `SQLSTATE[07002]` con PDO_SQLSRV.
- Se eliminó reutilización problemática de placeholders en `MERGE`.
- CSP condicional para Google.
- AdSense manual separado de Auto Ads.
- Consent Mode v2.
- Google Site Verification.

## AdminCP

- Rediseño Enterprise.
- Logout seguro.
- Sidebar responsive.
- Acordeones.
- Modo compacto.
- Buscador.
- Persistencia de estado.
- Reparación del menú bloqueado por CSP:
  - el JavaScript inline fue movido a `public/assets/js/admin-navigation.js`.
- Patrón de navegación adaptado desde `Panel_Valheim_Pro`.

## Visual Design Studio

- Reparación de assets que no podían eliminarse al estar asignados.
- Desasociación automática.
- Reemplazo de fondo global.
- Botón “Usar como fondo global”.
- Mensajes de upload mejorados.

## hCaptcha

- Integración server-side.
- Validación antes de autenticación/registro.
- Fail-closed.
- CSP compatible.
- Login protegido.
- Registro protegido.
- Diagnóstico AdminCP.
- Contextos reutilizables.

## Módulos

También existen hotfixes específicos documentados en sus manifests:

- Duel History: compatibilidad PDO_SQLSRV/ODBC 18.
- Market Manager: Warehouse NULL/no inicializado.
- Name Effects: DATEADD y caché LiteSpeed/OPcache.
- Secure Roulette: DATEADD/SQL Server.
- Title Marquee: warning PHP 8.2.
- VIP Membership: compatibilidad `ModuleRuntime`.

---

# Actualización y mantenimiento

## Antes de actualizar

Recomendado:

1. Respaldar la base CMS.
2. Respaldar `.env`.
3. Respaldar `public/uploads/`.
4. Respaldar módulos personalizados.
5. Revisar nuevas migraciones SQL.

## Después de actualizar

Comprueba:

- Login.
- Registro.
- AdminCP.
- Rankings.
- Módulos.
- Design Studio.
- hCaptcha.
- Monetización.
- Permisos de uploads.
- Driver SQL Server.

Si se modifica CSS/JS, OMEGA utiliza versionado de assets para reducir problemas de caché.

---

# Estructura del proyecto

```text
OMEGA_CMS/
├─ app/
│  ├─ Core/
│  ├─ GameServer/
│  ├─ Repositories/
│  └─ Services/
│
├─ assets/
├─ config/
├─ database/
│  └─ migrations/
│
├─ docs/
├─ install/
│  └─ lib/
│
├─ modules/
├─ public/
│  ├─ assets/
│  └─ uploads/
│
├─ rdp_guard/
├─ storage/
├─ templates/
├─ .htaccess
└─ index.php
```

---

# Desarrollo de módulos

Los módulos se ubican en:

```text
modules/<slug>/
```

Normalmente incluyen:

```text
module.json
Module.php
README.md
assets/
```

El manifest puede definir:

- Nombre.
- Slug.
- Versión.
- Autor.
- Versión mínima del core.
- Estado por defecto.
- Categoría.
- Menú de usuario.
- Acciones administrativas.
- Esquema de configuración.

OMEGA dispone de `ModuleManager`, `ModulePackageManager` y `ModuleRuntime` para descubrimiento, instalación y ejecución.

---

# Base de datos y privacidad

OMEGA diferencia entre:

- Datos públicos del personaje.
- Datos administrativos.
- Datos sensibles de la cuenta.

No se recomienda exponer públicamente:

- Contraseñas.
- Secret keys.
- IP completa.
- AccountID cuando no sea necesario.
- Credenciales SQL.
- Tokens privados.

Las páginas y módulos deben preferir datos de personaje para vistas públicas y reservar datos de cuenta para funciones autenticadas/administrativas.

---

# Créditos

## Dirección del proyecto

**Azzlaer / LatinBattle.com**

Concepto, dirección funcional, pruebas, adaptación al ecosistema MU Online, diseño del producto, definición de módulos e integración operativa del proyecto OMEGA CMS.

Repositorio:

**https://github.com/Azzlaer/OMEGA_CMS**

## OMEGA MU CMS

Los componentes cuyo manifest declara:

```text
Author: Omega MU CMS
```

son componentes desarrollados específicamente para este ecosistema.

Entre ellos:

- Core Account.
- News.
- Rankings.
- Reset.
- Duel History Professional.
- Guild Directory Professional.
- Guild Ranking Basic.
- Guild Ranking Professional.
- PK Item Drop History Professional.
- Secure Roulette.

## LatinBattle / WebEngine adaptations

Los siguientes módulos conservan explícitamente sus créditos de adaptación/origen declarados en sus manifests:

- Inventory Viewer Item Images Pack 1/2 — LatinBattle.com / adaptación OmegaCMS del pack WebEngine.
- Inventory Viewer Item Images Pack 2/2 — LatinBattle.com / adaptación OmegaCMS del pack WebEngine.
- Inventario y Baúl Viewer Kayito Sync — LatinBattle.com / adaptación OmegaCMS del WebEngine v1.0.7 FINAL POLISH.
- Market Manager Professional — LatinBattle.com / adaptación OmegaCMS del WebEngine Market Manager v0.4.4.
- Name Effects Professional — LatinBattle.com / adaptación OmegaMU del módulo WebEngine.
- Rain Effect Manager Professional — LatinBattle.com / adaptación OmegaCMS del módulo WebEngine.
- Staff Profesional — LatinBattle.com / adaptación OmegaCMS del WebEngine Staff Profesional v2.0.2.
- Title Bar Marquee Manager Professional — LatinBattle.com / adaptación OmegaCMS del módulo WebEngine.
- VIP Membership Pro — LatinBattle.com / adaptación OmegaCMS del módulo WebEngine v1.1.2.

Los créditos/licencias originales de cualquier recurso o código de terceros deben conservarse cuando corresponda.

## Asistencia de desarrollo

Parte del proceso iterativo de arquitectura, revisión, documentación, depuración e implementación de OMEGA CMS ha contado con asistencia de **ChatGPT de OpenAI**, bajo dirección y validación del responsable del proyecto.

## Tecnologías y servicios externos

OMEGA puede integrarse o funcionar junto a tecnologías de terceros como:

- PHP.
- Apache.
- Microsoft SQL Server.
- Microsoft ODBC Driver.
- Microsoft Drivers for PHP for SQL Server.
- FreeTDS / PDO_DBLIB.
- hCaptcha.
- Google Analytics.
- Google Ads.
- Google AdSense.
- Google Tag Manager.
- Google Search Console.

Cada tecnología o servicio mantiene sus propias marcas, licencias y condiciones.

---

# Aviso legal

OMEGA CMS es un proyecto independiente para administración web de servidores MU Online.

**MU Online**, nombres, imágenes, marcas y demás propiedad intelectual relacionada pertenecen a sus respectivos propietarios. Este proyecto no implica afiliación, patrocinio ni aprobación oficial por parte de Webzen u otros titulares de esas marcas.

Los módulos adaptados desde proyectos o recursos de terceros deben conservar sus créditos y licencias originales.

No publiques credenciales reales, claves privadas, contraseñas SQL, secrets de hCaptcha, claves API ni archivos `.env` en repositorios públicos.

---

# Estado del proyecto

OMEGA CMS continúa en desarrollo.

Las áreas actuales de evolución incluyen:

- Seguridad de cuentas.
- Nuevos rankings.
- Analítica.
- Economía.
- Perfiles avanzados.
- Integraciones Discord.
- Herramientas administrativas.
- Evolución del sistema modular.
- Compatibilidad con otros emuladores/versions de MU.

Las contribuciones y mejoras deben intentar conservar:

- Compatibilidad con el driver actual.
- Seguridad.
- Modularidad.
- Créditos originales.
- Migraciones no destructivas.
- Compatibilidad PHP 8.2+.
- Compatibilidad SQL Server.

---

**Ω OMEGA CMS — Enterprise Control for MU Online**
