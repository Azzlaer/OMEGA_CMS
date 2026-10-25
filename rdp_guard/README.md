# RDP Guard Web v3.2

Panel defensivo para recuperar y proteger RDP en Windows.

## Funciones

- Diagnostico asincrono:
  - privilegios del proceso PHP/PowerShell
  - politica de cuentas
  - usuarios locales
  - RDP habilitado/deshabilitado
  - NLA
  - puerto RDP
  - TermService
  - prueba local del listener
  - miembros de Remote Desktop Users
  - firewall RDP
  - eventos 4740

- Recuperacion:
  - habilita la cuenta local
  - intenta limpiar el bloqueo por ADSI
  - configura lockout threshold/duration/window
  - habilita RDP
  - habilita NLA opcional
  - configura puerto
  - inicia TermService
  - agrega/verifica usuario en Remote Desktop Users
  - crea regla "RDP Guard - Recovery"
  - prueba TCP local

- Visor de eventos:
  - 4625 logins fallidos
  - 4624 logins correctos
  - 4740 cuentas bloqueadas
  - resumen de IPs

- Firewall:
  - regla allowlist por IP/CIDR
  - opcion para deshabilitar reglas generales y Recovery

- Anti-Bruteforce:
  - tarea programada SYSTEM cada 5 minutos
  - analiza 4625 dentro de una ventana
  - bloquea temporalmente IPs que superen el umbral
  - whitelist
  - elimina bloqueos expirados
  - desinstalacion limpia

- Conexion:
  - genera un archivo .rdp listo para usar
  - no almacena contrasena

## Instalacion

1. Copia `RDP_Guard_Web_v3` al servidor web del VPS.
2. Edita `config.php`.
3. Cambia `APP_PASSWORD`.
4. Usa HTTPS.
5. Restringe el acceso al panel por IP/VPN si es posible.
6. PHP/Apache/IIS debe tener permiso para ejecutar PowerShell y modificar Windows.

## Recomendacion de uso

1. Abre VNC/SPICE/KVM del proveedor.
2. Ejecuta Diagnostico.
3. Ve a Recuperar RDP.
4. Recupera la cuenta.
5. Prueba RDP desde tu PC.
6. En cuanto funcione, ve a Firewall y restringe RDP a tu IP.
7. Instala Anti-Bruteforce con tu IP en whitelist.

## Nota importante

La regla `RDP Guard - Recovery` permite el puerto RDP desde cualquier IP para facilitar
la recuperacion. Es deliberadamente temporal. Despues de recuperar acceso, utiliza
Firewall -> restringir por IP y marca "Deshabilitar reglas generales...".

## Anti-Bruteforce

Archivos instalados:

`C:\ProgramData\RDPGuard\AntiBruteForce.ps1`
`C:\ProgramData\RDPGuard\whitelist.txt`
`C:\ProgramData\RDPGuard\blocked.json`

Tarea:

`RDP Guard Anti-Bruteforce`

## Seguridad del panel

No incluye terminal, web shell ni ejecucion de comandos arbitrarios.
Solo puede ejecutar los scripts PowerShell incluidos.


## Correccion v3.2
Reconstruye los scripts con el param principal como primera instruccion y elimina la duplicacion accidental de v3.1. Tambien mantiene startup logs y watchdog de jobs.
