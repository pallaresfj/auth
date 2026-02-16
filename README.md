# Auth SSO (`auth.iedagropivijay.edu.co`)

Identity Provider institucional para `iedagropivijay.edu.co` basado en:

- Laravel 12
- Laravel Passport (OAuth2 Authorization Server)
- OpenID Connect sobre Passport (`jeremy379/laravel-openid-connect`)
- Laravel Socialite (Google obligatorio)
- Filament 5 (panel admin)
- MySQL

## Objetivo

`auth` centraliza **autenticación** (Google + OIDC/OAuth2) para:

- `planes.iedagropivijay.edu.co`
- `asistencia.iedagropivijay.edu.co`
- `silo.iedagropivijay.edu.co`

Los roles y permisos siguen siendo locales en cada app cliente.

## Requisitos

- PHP 8.2+
- Composer 2+
- MySQL
- Node.js (opcional para assets de frontend)

## Instalación local

1. Instalar dependencias:

```bash
composer install
```

2. Crear entorno:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configurar `.env`:

- `APP_URL` (local o productivo)
- `DB_*`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`
- `INSTITUTION_EMAIL_DOMAIN`
- `SUPERADMIN_EMAILS`
- `ISSUER`
- `TOKEN_TTL_MINUTES`
- `REFRESH_TOKEN_TTL_DAYS`
- `CORS_ALLOWED_ORIGINS`

4. Generar llaves de Passport:

```bash
php artisan passport:keys --force
```

5. Migrar y seed:

```bash
php artisan migrate --seed
```

6. Limpiar cachés:

```bash
php artisan optimize:clear
```

7. Levantar servidor:

```bash
php artisan serve
```

8. Opcional frontend:

```bash
npm install
npm run dev
```

## Variables de entorno principales

```dotenv
APP_URL=https://auth.iedagropivijay.edu.co
ISSUER=https://auth.iedagropivijay.edu.co
OIDC_FORCE_HTTPS=true

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://auth.iedagropivijay.edu.co/auth/google/callback

INSTITUTION_EMAIL_DOMAIN=iedagropivijay.edu.co
SUPERADMIN_EMAILS=admin@iedagropivijay.edu.co

TOKEN_TTL_MINUTES=30
REFRESH_TOKEN_TTL_DAYS=14

CORS_ALLOWED_ORIGINS=https://planes.iedagropivijay.edu.co,https://asistencia.iedagropivijay.edu.co,https://silo.iedagropivijay.edu.co
SSO_ALLOWED_REDIRECT_HOSTS=planes.iedagropivijay.edu.co,asistencia.iedagropivijay.edu.co,silo.iedagropivijay.edu.co
```

## Login Google (obligatorio)

Endpoints:

- `GET /login`
- `GET /auth/google/redirect`
- `GET /auth/google/callback`
- `POST /logout`

Reglas:

- No existe login con contraseña.
- Solo correos de dominios permitidos (`INSTITUTION_EMAIL_DOMAIN`).
- Usuarios inactivos (`is_active = false`) no pueden continuar.

## Endpoints OAuth2/OIDC

### OAuth2 (Passport)

- `GET /oauth/authorize`
- `POST /oauth/token`
- `POST /oauth/token/refresh`

### OIDC

- Discovery: `GET /.well-known/openid-configuration`
- JWKS: `GET /oauth/jwks`
- UserInfo: `GET /oauth/userinfo` (requiere `Bearer access_token` + scope `openid`)

## Ejemplos cURL

### 1) Authorization request (Authorization Code + PKCE + `openid`)

```bash
curl -G 'https://auth.iedagropivijay.edu.co/oauth/authorize' \
  --data-urlencode 'client_id=YOUR_CLIENT_ID' \
  --data-urlencode 'redirect_uri=https://planes.iedagropivijay.edu.co/auth/callback' \
  --data-urlencode 'response_type=code' \
  --data-urlencode 'scope=openid email profile' \
  --data-urlencode 'state=STATE123' \
  --data-urlencode 'code_challenge=BASE64URL_SHA256_VERIFIER' \
  --data-urlencode 'code_challenge_method=S256' \
  --data-urlencode 'nonce=NONCE123'
```

### 2) Exchange code por tokens (esperando `id_token`)

```bash
curl -X POST 'https://auth.iedagropivijay.edu.co/oauth/token' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=authorization_code' \
  -d 'client_id=YOUR_CLIENT_ID' \
  -d 'client_secret=YOUR_CLIENT_SECRET' \
  -d 'redirect_uri=https://planes.iedagropivijay.edu.co/auth/callback' \
  -d 'code=AUTH_CODE' \
  -d 'code_verifier=ORIGINAL_CODE_VERIFIER'
```

Si el scope incluyó `openid`, la respuesta contiene `id_token`.

### 3) UserInfo

```bash
curl 'https://auth.iedagropivijay.edu.co/oauth/userinfo' \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

## Panel administrativo Filament

Ruta: `https://auth.iedagropivijay.edu.co/admin`

- Usuarios:
  - Ver `email`, `is_active`, `last_login_at`, `google_id`
  - Activar/desactivar usuarios
- Clientes OAuth:
  - Crear/editar nombre
  - Definir redirect URIs exactas (sin wildcard, HTTPS y host permitido)
  - Definir scopes (`openid`, `email`, `profile`, ...)
  - Revocar/activar cliente
  - Regenerar `client_secret`

Acceso al panel:

- Solo correos listados en `SUPERADMIN_EMAILS`
- Usuario debe estar activo

## Seeders incluidos

- `SuperAdminsSeeder`: crea/actualiza superadmins desde `SUPERADMIN_EMAILS`
- `OAuthClientsSeeder`: crea clientes base:
  - `planes` → `https://planes.iedagropivijay.edu.co/auth/callback`
  - `asistencia` → `https://asistencia.iedagropivijay.edu.co/auth/callback`

> Ajusta esas redirect URIs en panel o seeder según tu callback final.

## Integración con apps Filament cliente

Cliente recomendado:

- Authorization Code + PKCE
- Scopes: `openid email profile`

En callback del cliente:

1. Intercambiar `code` por tokens en `/oauth/token`
2. Validar `id_token` (`iss`, `aud`, `exp`, `nonce`)
3. Consumir `/oauth/userinfo` si se requiere
4. Crear/actualizar usuario local y asignar rol local

## Auditoría

Tabla: `audit_logins`

Eventos auditados:

- `login_google`
- `authorize`
- `token_issued`
- `logout`

Estados:

- `success`
- `failed`

Incluye `user_id`, `client_id`, IP, user-agent y metadatos JSON.

## Pruebas

```bash
php artisan test
```

Incluye pruebas para:

- Discovery/JWKS
- UserInfo + scope `openid`
- Callback Google (éxito y dominio no permitido)
- Auditoría de emisión/fallo de token
