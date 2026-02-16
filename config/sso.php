<?php

$csv = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => mb_strtolower(trim($item)),
    explode(',', $value),
)));

return [
    'issuer' => env('ISSUER', env('APP_URL', 'http://localhost')),

    'institution_email_domains' => $csv(env('INSTITUTION_EMAIL_DOMAIN', 'iedagropivijay.edu.co')),

    'superadmin_emails' => $csv(env('SUPERADMIN_EMAILS', '')),

    'token_ttl_minutes' => (int) env('TOKEN_TTL_MINUTES', 30),

    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 14),

    'cors_allowed_origins' => $csv(env(
        'CORS_ALLOWED_ORIGINS',
        'https://planes.iedagropivijay.edu.co,https://asistencia.iedagropivijay.edu.co,https://silo.iedagropivijay.edu.co,https://auth.iedagropivijay.edu.co'
    )),

    'allowed_redirect_hosts' => $csv(env(
        'SSO_ALLOWED_REDIRECT_HOSTS',
        'planes.iedagropivijay.edu.co,asistencia.iedagropivijay.edu.co,silo.iedagropivijay.edu.co'
    )),
];
