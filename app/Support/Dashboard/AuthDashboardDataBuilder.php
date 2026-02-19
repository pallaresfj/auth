<?php

namespace App\Support\Dashboard;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLogin;
use App\Models\OAuthClient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthDashboardDataBuilder
{
    private const DEFAULT_WINDOW = '30d';

    /**
     * @var array<string, string>
     */
    private const WINDOW_LABELS = [
        'today' => 'Hoy',
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
    ];

    /**
     * @var array<int, string>
     */
    private const DISTRIBUTION_COLORS = [
        '#1d6362',
        '#6b9a34',
        '#99ce93',
        '#f8c508',
        '#f50404',
        '#0ea5e9',
        '#6366f1',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(string $window = self::DEFAULT_WINDOW): array
    {
        $window = $this->normalizeWindow($window);
        $cacheKey = "auth_dashboard:{$window}";

        return Cache::remember($cacheKey, now()->addSeconds(60), fn (): array => $this->buildPayload($window));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $window): array
    {
        $windowStart = $this->resolveWindowStart($window);
        $windowLabel = self::WINDOW_LABELS[$window];

        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $inactiveUsers = max(0, $totalUsers - $activeUsers);
        $activeUsersRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0.0;

        $oauthClientsActive = OAuthClient::query()->where('revoked', false)->count();
        $oauthClientsRevoked = OAuthClient::query()->where('revoked', true)->count();

        $tokensActiveNow = DB::table('oauth_access_tokens')
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $windowAuditQuery = AuditLogin::query()
            ->when($windowStart !== null, fn ($query) => $query->where('created_at', '>=', $windowStart));

        $eventsSuccessWindow = (clone $windowAuditQuery)->where('status', 'success')->count();
        $eventsFailedWindow = (clone $windowAuditQuery)->where('status', 'failed')->count();
        $eventsTotalWindow = (clone $windowAuditQuery)->count();
        $successRate = $eventsTotalWindow > 0 ? round(($eventsSuccessWindow / $eventsTotalWindow) * 100, 1) : 0.0;

        $clientNames = OAuthClient::query()->pluck('name', 'id')->all();

        $ecosystem = OAuthClient::query()
            ->where('revoked', false)
            ->orderBy('name')
            ->get(['id', 'name', 'redirect_uris'])
            ->map(fn (OAuthClient $client): array => $this->buildEcosystemCard($client))
            ->values()
            ->all();

        $recentActivity = AuditLogin::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'user_id', 'client_id', 'event', 'status', 'created_at'])
            ->map(fn (AuditLogin $event): array => $this->buildActivityRow($event, $clientNames))
            ->all();

        $distribution = $this->buildDistribution($windowStart, $clientNames);

        return [
            'window' => $window,
            'windowLabel' => $windowLabel,
            'windowOptions' => self::WINDOW_LABELS,
            'hero' => [
                'title' => 'Bienvenido de nuevo, Administrador',
                'description' => 'Gestiona la identidad institucional, monitorea la actividad OAuth y supervisa la seguridad desde tu centro de control.',
            ],
            'links' => [
                'oauthClients' => OAuthClientResource::getUrl('index'),
                'users' => UserResource::getUrl('index'),
            ],
            'kpis' => [
                [
                    'label' => 'Usuarios Totales',
                    'value' => number_format($totalUsers),
                    'hint' => "{$activeUsersRate}% activos",
                    'icon' => 'heroicon-o-users',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Usuarios Activos',
                    'value' => number_format($activeUsers),
                    'hint' => number_format($inactiveUsers).' inactivos',
                    'icon' => 'heroicon-o-user-circle',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Clientes OAuth',
                    'value' => number_format($oauthClientsActive),
                    'hint' => number_format($oauthClientsRevoked).' revocados',
                    'icon' => 'heroicon-o-key',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Tokens Vigentes',
                    'value' => number_format($tokensActiveNow),
                    'hint' => "Ventana: {$windowLabel}",
                    'icon' => 'heroicon-o-shield-check',
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Eventos Exitosos',
                    'value' => number_format($eventsSuccessWindow),
                    'hint' => "{$successRate}% tasa de éxito",
                    'icon' => 'heroicon-o-check-badge',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Eventos Fallidos',
                    'value' => number_format($eventsFailedWindow),
                    'hint' => $eventsFailedWindow > 0 ? 'Requiere revisión' : 'Sin fallos en la ventana',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'tone' => $eventsFailedWindow > 0 ? 'danger' : 'info',
                ],
            ],
            'ecosystem' => $ecosystem,
            'recentActivity' => $recentActivity,
            'distribution' => $distribution,
            'support' => [
                'title' => '¿Necesitas soporte?',
                'description' => 'Nuestro equipo técnico está disponible para ayudarte con clientes OAuth, accesos y eventos de autenticación.',
                'mailto' => 'mailto:soporte@iedagropivijay.edu.co?subject=Soporte%20Auth%20Dashboard',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $clientNames
     * @return array<string, mixed>
     */
    private function buildDistribution(?CarbonImmutable $windowStart, array $clientNames): array
    {
        $rows = AuditLogin::query()
            ->selectRaw('client_id, count(*) as total')
            ->whereNotNull('client_id')
            ->when($windowStart !== null, fn ($query) => $query->where('created_at', '>=', $windowStart))
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');

        if ($total === 0) {
            return [
                'empty' => true,
                'gradient' => 'conic-gradient(#d9e7e6 0deg 360deg)',
                'headline' => '0%',
                'subheadline' => 'Sin actividad',
                'items' => [],
                'total' => 0,
            ];
        }

        $angleCursor = 0.0;
        $segments = [];

        $items = $rows->values()->map(function ($row, int $index) use ($clientNames, $total, &$angleCursor, &$segments): array {
            $count = (int) $row->total;
            $percentage = round(($count / $total) * 100, 1);
            $color = self::DISTRIBUTION_COLORS[$index % count(self::DISTRIBUTION_COLORS)];
            $angle = ($count / $total) * 360;
            $start = round($angleCursor, 2);
            $end = round($angleCursor + $angle, 2);

            $segments[] = "{$color} {$start}deg {$end}deg";
            $angleCursor += $angle;

            return [
                'name' => $clientNames[$row->client_id] ?? Str::limit((string) $row->client_id, 18),
                'total' => $count,
                'percentage' => $percentage,
                'color' => $color,
            ];
        })->all();

        $first = $items[0];

        return [
            'empty' => false,
            'gradient' => 'conic-gradient('.implode(', ', $segments).')',
            'headline' => number_format((float) $first['percentage'], 1).'%',
            'subheadline' => Str::headline((string) $first['name']),
            'items' => $items,
            'total' => $total,
        ];
    }

    private function normalizeWindow(string $window): string
    {
        return array_key_exists($window, self::WINDOW_LABELS) ? $window : self::DEFAULT_WINDOW;
    }

    private function resolveWindowStart(string $window): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return match ($window) {
            'today' => $now->startOfDay(),
            '7d' => $now->subDays(7),
            '30d' => $now->subDays(30),
            default => null,
        };
    }

    private function buildEcosystemCard(OAuthClient $client): array
    {
        $redirectUrl = $this->resolvePrimaryRedirectUri($client->redirect_uris);
        $host = $redirectUrl ? parse_url($redirectUrl, PHP_URL_HOST) : null;

        return [
            'name' => $client->name,
            'description' => $this->resolveClientDescription($client->name),
            'host' => $host ?: 'Host no disponible',
            'icon' => $this->resolveClientIcon($client->name),
            'manageUrl' => OAuthClientResource::getUrl('index', [
                'tableSearch' => $client->name,
            ]),
            'externalUrl' => $redirectUrl,
            'status' => 'Activo',
        ];
    }

    /**
     * @param  array<string, string>  $clientNames
     * @return array<string, mixed>
     */
    private function buildActivityRow(AuditLogin $event, array $clientNames): array
    {
        $userName = trim((string) optional($event->user)->name);
        $userName = $userName !== '' ? $userName : 'Usuario no identificado';

        $clientName = $clientNames[$event->client_id] ?? null;

        return [
            'userName' => $userName,
            'userInitial' => Str::upper(Str::substr($userName, 0, 1)),
            'clientName' => $clientName ?: ($event->client_id ?: 'N/D'),
            'eventLabel' => $this->resolveEventLabel((string) $event->event),
            'statusLabel' => $event->status === 'success' ? 'Éxito' : 'Fallido',
            'statusTone' => $event->status === 'success' ? 'success' : 'danger',
            'occurredAt' => optional($event->created_at)?->format('d/m/Y H:i'),
            'occurredAtHuman' => optional($event->created_at)?->diffForHumans(),
        ];
    }

    /**
     * @param  array<int, mixed>|string|null  $redirectUris
     */
    private function resolvePrimaryRedirectUri(array|string|null $redirectUris): ?string
    {
        foreach ($this->normalizeStringArray($redirectUris) as $uri) {
            if (filter_var($uri, FILTER_VALIDATE_URL)) {
                return $uri;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>|string|null  $value
     * @return array<int, string>
     */
    private function normalizeStringArray(array|string|null $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $this->normalizeStringArray($decoded);
        }

        return collect(preg_split('/[\n,]+/', $value) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveEventLabel(string $event): string
    {
        return match ($event) {
            'login_google' => 'Inicio con Google',
            'authorize' => 'Autorización OAuth',
            'token_issued' => 'Token emitido',
            'logout' => 'Cierre de sesión',
            default => Str::headline($event),
        };
    }

    private function resolveClientIcon(string $name): string
    {
        return match (mb_strtolower($name)) {
            'planes', 'gestionplanes', 'gestionplanes-f5' => 'heroicon-o-book-open',
            'asistencia', 'teachingassistance' => 'heroicon-o-clipboard-document-check',
            'silo' => 'heroicon-o-archive-box',
            default => 'heroicon-o-building-library',
        };
    }

    private function resolveClientDescription(string $name): string
    {
        return match (mb_strtolower($name)) {
            'planes', 'gestionplanes', 'gestionplanes-f5' => 'Gestión académica, planes de área y seguimiento curricular.',
            'asistencia', 'teachingassistance' => 'Seguimiento de asistencia docente y gestión institucional.',
            'silo' => 'Gestión documental institucional y archivo académico.',
            default => 'Cliente OAuth activo dentro del ecosistema institucional.',
        };
    }
}
