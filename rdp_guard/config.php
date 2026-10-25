<?php
declare(strict_types=1);

// ================================
// RDP Guard Web v3 - Configuracion
// ================================

// Cambia esto antes de usar la web.
const APP_PASSWORD = 'hP8fL3nN9zzF';

// Host/IP que aparecera por defecto al generar el archivo .rdp.
// Puedes dejarlo vacio y escribirlo desde el formulario.
const DEFAULT_RDP_HOST = '';

// Sesiones / datos
const SESSION_NAME = 'rdp_guard_web_v3';
const DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';
const JOB_DIR = DATA_DIR . DIRECTORY_SEPARATOR . 'jobs';
const STATE_DIR = DATA_DIR . DIRECTORY_SEPARATOR . 'state';
const SCRIPT_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';

// Permite que PHP inicie los scripts PowerShell.
// PHP/Apache/IIS necesita permisos suficientes.
const ALLOW_EXECUTION = true;

// Limites
const MAX_EVENT_COUNT = 2500;
const DEFAULT_EVENT_COUNT = 100;

// Valores recomendados
const DEFAULT_LOCKOUT_THRESHOLD = 10;
const DEFAULT_LOCKOUT_DURATION = 15;
const DEFAULT_LOCKOUT_WINDOW = 15;
const DEFAULT_BRUTE_THRESHOLD = 12;
const DEFAULT_BRUTE_WINDOW_MIN = 10;
const DEFAULT_BRUTE_BLOCK_MIN = 180;
