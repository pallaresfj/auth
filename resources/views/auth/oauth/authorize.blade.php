<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de autorización</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #111827; }
        .card { max-width: 42rem; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; }
        .btn { border: none; border-radius: 0.5rem; padding: 0.6rem 1rem; cursor: pointer; }
        .btn-approve { background: #15803d; color: #fff; }
        .btn-deny { background: #b91c1c; color: #fff; }
        .actions { display: flex; gap: 0.75rem; margin-top: 1.25rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Solicitud de autorización</h1>

    <p>
        <strong>{{ $client->name }}</strong> solicita acceso a tu cuenta.
    </p>

    @if (count($scopes) > 0)
        <p>Permisos solicitados:</p>
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    @php
        $nonceQuery = $request->query('nonce') ? ('?nonce=' . urlencode((string) $request->query('nonce'))) : '';
    @endphp

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
</div>
</body>
</html>
