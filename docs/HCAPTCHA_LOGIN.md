# hCaptcha Login Protection

OMEGA CMS puede proteger el inicio de sesión con hCaptcha.

## 1. Crear las claves

Desde el panel de hCaptcha crea un Site y copia:

- **Sitekey**: clave pública que puede aparecer en el HTML.
- **Secret**: clave privada que debe permanecer únicamente en el servidor.

No subas la Secret a GitHub.

## 2. Configurar .env

Agrega estas variables al archivo privado `.env` de la instalación:

```env
HCAPTCHA_ENABLED=true
HCAPTCHA_SITEKEY=TU_SITEKEY_PUBLICA
HCAPTCHA_SECRET=TU_SECRET_PRIVADA
```

Para desactivar temporalmente la protección:

```env
HCAPTCHA_ENABLED=false
```

## 3. Flujo implementado

1. El formulario `/login` carga el widget oficial de hCaptcha.
2. hCaptcha agrega `h-captcha-response` al POST.
3. OMEGA valida CSRF.
4. OMEGA verifica el token server-side en `https://api.hcaptcha.com/siteverify`.
5. Se envían `secret`, `response`, `sitekey` y, cuando es válida, la IP del cliente.
6. Solo después de una verificación correcta se ejecuta la autenticación de la cuenta MU.

La validación es **fail-closed**: si hCaptcha está habilitado y el servicio no puede verificarse, el login es rechazado.

## 4. Requisitos PHP

Se requiere la extensión PHP cURL.

En XAMPP verifica que `extension=curl` esté habilitada en `php.ini` y reinicia Apache después de cualquier cambio.

## 5. CSP

OMEGA agrega los hosts oficiales recomendados por hCaptcha a Content Security Policy únicamente cuando `HCAPTCHA_ENABLED=true`:

- `https://hcaptcha.com`
- `https://*.hcaptcha.com`

Se habilitan en `script-src`, `frame-src`, `style-src` y `connect-src`.

## 6. Seguridad

- La Secret nunca se envía al navegador.
- No se confía en headers IP reenviados arbitrariamente; OMEGA usa `REMOTE_ADDR`.
- Los tokens son de un solo uso y deben validarse en el backend.
- hCaptcha complementa, no reemplaza, CSRF, rate limiting ni la contraseña.
