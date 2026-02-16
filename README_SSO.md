# Auth SSO Institucional  
## iedagropivijay.edu.co

## 1. Descripción General

La aplicación `auth.iedagropivijay.edu.co` será el **Proveedor de Identidad (Identity Provider - IdP)** oficial para todas las aplicaciones institucionales desplegadas como subdominios de:

- planes.iedagropivijay.edu.co
- asistencia.iedagropivijay.edu.co
- silo.iedagropivijay.edu.co
- futuras aplicaciones con el mismo stack

Permitirá **Inicio de Sesión Único (SSO)** utilizando el estándar **OAuth 2.0 + OpenID Connect (OIDC)**, centralizando la autenticación institucional sin afectar los sistemas de roles locales de cada aplicación.

---

# 2. Objetivo del Proyecto

Eliminar la necesidad de múltiples credenciales para docentes, permitiendo:

- Un único inicio de sesión institucional
- Autenticación obligatoria mediante Google Workspace
- Roles y permisos independientes por aplicación
- Escalabilidad para futuras apps

---

# 3. Principios Arquitectónicos

1. Separación clara entre:
   - **Autenticación (Identidad)** → gestionada por `auth`
   - **Autorización (Roles/Permisos)** → gestionada por cada app

2. Uso de estándares abiertos:
   - OAuth 2.0
   - OpenID Connect (OIDC)
   - Authorization Code Flow + PKCE

3. No modificar estructuras de roles existentes (Shield/Spatie u otros).

---

# 4. Stack Tecnológico

- Laravel 12
- Laravel Passport (OAuth2 Authorization Server)
- Extensión OIDC compatible con Passport
- Laravel Socialite (Google Provider)
- MySQL
- HTTPS obligatorio

---

# 5. Flujo de Autenticación (OIDC)

## Authorization Code + PKCE Flow

1. Usuario accede a `planes` o `asistencia`.
2. App redirige a:
GET /oauth/authorize


con parámetros:
- client_id
- redirect_uri
- response_type=code
- scope=openid email profile
- state
- code_challenge

3. `auth` verifica sesión:
   - Si no existe → login obligatorio con Google.
   - Si existe → continúa.

4. `auth` devuelve:
?code=xxxx


5. Cliente intercambia código:
POST /oauth/token


6. Respuesta incluye:

- access_token
- refresh_token
- id_token (OIDC)

7. Cliente valida `id_token`:
   - iss
   - aud
   - exp
   - nonce

8. Cliente crea o actualiza usuario local.

---

# 6. Caso Especial: silo (Google obligatorio)

Como `auth` utilizará Google obligatorio, `silo` podrá:

- Migrar a login institucional vía `auth`
- Mantener coherencia sin múltiples integraciones directas a Google

---

# 7. Modelo de Datos (Auth Central)

## users

| Campo | Tipo | Descripción |
|--------|------|-------------|
| id | bigint | PK |
| email | string | Único |
| name | string | Nombre |
| google_id | string | ID Google |
| is_active | boolean | Estado |
| last_login_at | timestamp | Último acceso |
| created_at | timestamp |  |
| updated_at | timestamp |  |

---

## audit_logins

| Campo | Tipo |
|--------|------|
| id | bigint |
| user_id | fk |
| client_id | string |
| ip | string |
| user_agent | text |
| status | string |
| created_at | timestamp |

---

## user_app_access (Fase 2)

Permite controlar acceso a apps específicas.

| Campo | Tipo |
|--------|------|
| user_id | fk |
| client_id | string |
| is_allowed | boolean |

---

# 8. Endpoints Requeridos

## OAuth2

- GET /oauth/authorize
- POST /oauth/token
- POST /oauth/token/refresh

## OpenID

- GET /.well-known/openid-configuration
- GET /oauth/jwks

## Recursos

- GET /me (protegido por access_token)

---

# 9. Seguridad

- HTTPS obligatorio
- Cookies Secure + HttpOnly
- PKCE requerido
- Access tokens corta duración (15–30 min)
- Refresh tokens rotables
- Validación estricta de issuer y audience

---

# 10. Gestión de Clientes

Cada aplicación será registrada como cliente OAuth:

| App | Subdominio |
|-----|------------|
| planes | planes.iedagropivijay.edu.co |
| asistencia | asistencia.iedagropivijay.edu.co |
| silo | silo.iedagropivijay.edu.co |

Cada cliente tendrá:

- client_id
- client_secret (si aplica)
- redirect_uris
- scopes permitidos

---

# 11. Integración con Filament (Apps Cliente)

En cada aplicación:

1. Instalar Socialite
2. Configurar proveedor OIDC apuntando a `auth`
3. Agregar botón “Entrar con Cuenta Institucional”
4. En callback:
   - Validar token
   - Crear o actualizar usuario local
   - Asignar rol por defecto si no existe
   - Iniciar sesión en guard Filament

No se modifica estructura de roles existentes.

---

# 12. Fases del Proyecto

## Fase 1
- Implementar `auth` con Google OAuth
- Configurar Passport + OIDC
- Registrar clientes planes y asistencia
- Validar flujo SSO completo

## Fase 2
- Control de acceso por aplicación
- Auditoría avanzada
- 2FA opcional

## Fase 3
- Panel administrativo para gestión de clientes
- Portal desarrollador interno

---

# 13. Criterios de Aceptación

✔ Login Google institucional exitoso  
✔ Emisión válida de id_token  
✔ Registro de múltiples clientes  
✔ Flujo completo Authorization Code + PKCE  
✔ Logs de autenticación generados  

---

# 14. Resultado Esperado

Una infraestructura centralizada que permita:

- Un solo login para docentes
- Seguridad institucional unificada
- Escalabilidad a nuevas apps sin rediseño
- Independencia de roles por aplicación

