<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SILO - Solicitud de autorizacion</title>
    <style>
        :root {
            --primary: #1d6362;
            --primary-dark: #154847;
            --primary-soft: #e7f3f2;
            --bg: #f6f6f8;
            --card: #ffffff;
            --muted: #64748b;
            --text: #0f172a;
            --border: #dbe2ea;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .card {
            width: min(100%, 520px);
            border-radius: 16px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .header {
            padding: 2.25rem 2rem 1.5rem;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
        }

        .logo-badge {
            width: 74px;
            height: 74px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            margin: 0 auto 1rem;
            display: grid;
            place-items: center;
            font-size: 2rem;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.45rem, 2.7vw, 2rem);
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .subtitle {
            margin: 0.5rem 0 0;
            color: var(--muted);
            font-size: 0.98rem;
        }

        .content {
            padding: 2rem;
            display: grid;
            gap: 1.25rem;
        }

        .section-title {
            margin: 0;
            font-size: 0.92rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #1e293b;
        }

        .scope-list {
            display: grid;
            gap: 1rem;
        }

        .scope-item {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            align-items: start;
        }

        .scope-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 0.95rem;
            margin-top: 0.1rem;
        }

        .scope-name {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .scope-desc {
            margin: 0.2rem 0 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.38;
        }

        .notice {
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.95rem 1rem;
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 0.25rem;
            padding-inline: 0.15rem;
        }

        .actions form {
            margin: 0;
            width: 100%;
        }

        .btn {
            width: 100%;
            height: 48px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-approve {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 22px rgba(29, 99, 98, 0.24);
        }

        .btn-approve:hover {
            background: var(--primary-dark);
        }

        .btn-deny {
            background: #fff;
            border-color: #dbe2ea;
            color: #1e293b;
        }

        .btn-deny:hover {
            background: #f8fafc;
        }

        .footer {
            border-top: 1px solid #edf2f7;
            background: #f8fafc;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.9rem 2rem;
            font-size: 0.74rem;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .footer a {
            color: inherit;
            text-decoration: none;
        }

        .footer a:hover {
            color: #64748b;
        }

    </style>
</head>
<body>
@php
    $nonceQuery = $request->query('nonce') ? ('?nonce=' . urlencode((string) $request->query('nonce'))) : '';

    $scopeMeta = static function (string $identifier, string $description): array {
        return match ($identifier) {
            'openid' => [
                'icon' => '✓',
                'title' => 'Autenticacion OpenID Connect',
                'description' => 'Permite autenticacion segura con el proveedor de identidad institucional.',
            ],
            'email' => [
                'icon' => '@',
                'title' => 'Acceso a correo electronico',
                'description' => 'Permite leer tu direccion de correo principal.',
            ],
            'profile' => [
                'icon' => '◎',
                'title' => 'Acceso a perfil basico',
                'description' => 'Incluye nombre visible y preferencias basicas de perfil.',
            ],
            default => [
                'icon' => '•',
                'title' => $identifier,
                'description' => $description !== '' ? $description : 'Permiso solicitado por la aplicacion cliente.',
            ],
        };
    };

    $requestId = 'REQ-' . strtoupper(substr(sha1((string) $request->state), 0, 8));
@endphp

<div class="card">
    <header class="header">
        <div class="logo-badge">✱</div>
        <h1>{{ strtoupper((string) $client->name) }}</h1>
        <p class="subtitle">{{ (string) $client->name }} solicita acceso a tu cuenta</p>
    </header>

    <section class="content">
        <p class="section-title">Permisos solicitados</p>

        @if (count($scopes) > 0)
            <div class="scope-list">
                @foreach ($scopes as $scope)
                    @php
                        $scopeId = (string) data_get($scope, 'id', data_get($scope, 'identifier', data_get($scope, 'name', 'scope')));
                        $scopeDescription = trim((string) data_get($scope, 'description', ''));
                        $meta = $scopeMeta($scopeId, $scopeDescription);
                    @endphp
                    <article class="scope-item">
                        <div class="scope-icon">{{ $meta['icon'] }}</div>
                        <div>
                            <p class="scope-name">{{ $meta['title'] }}</p>
                            <p class="scope-desc">{{ $meta['description'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="notice">La aplicacion no solicito permisos adicionales.</div>
        @endif

        <div class="notice">
            Al autorizar, permites que <strong>{{ (string) $client->name }}</strong> use esta informacion segun sus politicas.
            Puedes revocar este acceso posteriormente.
        </div>

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') . $nonceQuery }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="btn btn-approve" type="submit">Autorizar</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="btn btn-deny" type="submit">Cancelar</button>
            </form>
        </div>
    </section>

    <footer class="footer">
        <span>Id solicitud: {{ $requestId }}</span>
        <span>
            <a href="#">Privacidad</a>
            &nbsp;•&nbsp;
            <a href="#">Ayuda</a>
        </span>
    </footer>
</div>
</body>
</html>
