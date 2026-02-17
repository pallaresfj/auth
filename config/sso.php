<?php

$csv = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => mb_strtolower(trim($item)),
    explode(',', $value),
)));

$map = static function (string $value): array {
    $pairs = array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode(',', $value),
    ));

    $result = [];

    foreach ($pairs as $pair) {
        [$key, $mappedValue] = array_pad(explode('|', $pair, 2), 2, '');

        $normalizedKey = mb_strtolower(trim((string) $key));
        $normalizedValue = trim((string) $mappedValue);

        if ($normalizedKey === '' || $normalizedValue === '') {
            continue;
        }

        $result[$normalizedKey] = $normalizedValue;
    }

    return $result;
};

return [
    'issuer' => env('ISSUER', env('APP_URL', 'http://localhost')),

    'institution_email_domains' => $csv(env('INSTITUTION_EMAIL_DOMAIN', 'iedagropivijay.edu.co')),

    'superadmin_emails' => $csv(env('SUPERADMIN_EMAILS', '')),

    'token_ttl_minutes' => (int) env('TOKEN_TTL_MINUTES', 30),

    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 14),

    'cors_allowed_origins' => $csv(env(
        'CORS_ALLOWED_ORIGINS',
        'https://gestionplanes.test,https://teachingassistance.test,http://localhost:8000'
    )),

    'allowed_redirect_hosts' => $csv(env(
        'SSO_ALLOWED_REDIRECT_HOSTS',
        'gestionplanes.test,teachingassistance.test,localhost,127.0.0.1'
    )),

    'insecure_redirect_hosts' => $csv(env(
        'SSO_INSECURE_REDIRECT_HOSTS',
        'localhost,127.0.0.1'
    )),

    'post_logout_redirect_hosts' => $csv(env(
        'SSO_POST_LOGOUT_REDIRECT_HOSTS',
        'localhost,127.0.0.1,gestionplanes.test,teachingassistance.test'
    )),

    'google_logout_from_browser' => (bool) env('GOOGLE_LOGOUT_FROM_BROWSER', true),

    'google_session_check_enabled' => (bool) env('GOOGLE_SESSION_CHECK_ENABLED', true),

    'google_session_check_interval_seconds' => (int) env('GOOGLE_SESSION_CHECK_INTERVAL_SECONDS', 60),

    'google_session_check_timeout_seconds' => (int) env('GOOGLE_SESSION_CHECK_TIMEOUT_SECONDS', 8),

    'frontchannel_logout_clients' => $map(env('SSO_FRONTCHANNEL_LOGOUT_CLIENTS', '')),

    'frontchannel_logout_secrets' => $map(env('SSO_FRONTCHANNEL_LOGOUT_SECRETS', '')),

    'frontchannel_logout_ttl_seconds' => (int) env('SSO_FRONTCHANNEL_LOGOUT_TTL_SECONDS', 120),
];
