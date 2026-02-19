<x-filament-panels::page>
    <div class="auth-dashboard-page">
        <section class="auth-dashboard-hero">
            <div class="auth-dashboard-hero__content">
                <p class="auth-dashboard-hero__eyebrow">Centro de control OAuth</p>
                <h1 class="auth-dashboard-hero__title">{{ $hero['title'] }}</h1>
                <p class="auth-dashboard-hero__description">{{ $hero['description'] }}</p>

                <div class="auth-dashboard-window-switch" role="group" aria-label="Rango temporal">
                    @foreach ($windowOptions as $optionKey => $optionLabel)
                        <a
                            href="{{ request()->fullUrlWithQuery(['window' => $optionKey]) }}"
                            @class([
                                'auth-dashboard-window-switch__chip',
                                'is-active' => $window === $optionKey,
                            ])
                        >
                            {{ $optionLabel }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="auth-dashboard-hero__actions">
                <a href="{{ $links['oauthClients'] }}" class="auth-dashboard-primary-cta">
                    <x-filament::icon icon="heroicon-o-plus-circle" class="auth-dashboard-primary-cta__icon" />
                    Crear cliente OAuth
                </a>
            </div>
        </section>

        <section class="auth-dashboard-section">
            <div class="auth-dashboard-section__header">
                <h2 class="auth-dashboard-section__title">Ecosistema Institucional</h2>
                <p class="auth-dashboard-section__meta">{{ count($ecosystem) }} cliente(s) activos</p>
            </div>

            @if (count($ecosystem) === 0)
                <article class="auth-dashboard-empty-card">
                    <p class="auth-dashboard-empty-card__title">No hay clientes OAuth activos</p>
                    <p class="auth-dashboard-empty-card__text">Crea un cliente para habilitar integraciones entre aplicaciones.</p>
                </article>
            @else
                <div class="auth-dashboard-ecosystem-grid">
                    @foreach ($ecosystem as $client)
                        <article class="auth-dashboard-ecosystem-card">
                            <div class="auth-dashboard-ecosystem-card__top">
                                <span class="auth-dashboard-ecosystem-card__icon-wrap">
                                    <x-filament::icon :icon="$client['icon']" class="auth-dashboard-ecosystem-card__icon" />
                                </span>
                                <span class="auth-dashboard-ecosystem-card__badge">{{ $client['status'] }}</span>
                            </div>

                            <h3 class="auth-dashboard-ecosystem-card__title">{{ $client['name'] }}</h3>
                            <p class="auth-dashboard-ecosystem-card__description">{{ $client['description'] }}</p>
                            <p class="auth-dashboard-ecosystem-card__host">{{ $client['host'] }}</p>

                            <div class="auth-dashboard-ecosystem-card__actions">
                                <a href="{{ $client['manageUrl'] }}" class="auth-dashboard-link">Gestionar</a>

                                @if ($client['externalUrl'])
                                    <a href="{{ $client['externalUrl'] }}" class="auth-dashboard-link" target="_blank" rel="noopener noreferrer">
                                        Abrir app
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="auth-dashboard-section">
            <div class="auth-dashboard-section__header">
                <h2 class="auth-dashboard-section__title">Estadísticas e Indicadores</h2>
                <p class="auth-dashboard-section__meta">{{ $windowLabel }}</p>
            </div>

            <div class="auth-dashboard-kpi-grid">
                @foreach ($kpis as $kpi)
                    <article class="auth-dashboard-kpi-card auth-dashboard-kpi-card--{{ $kpi['tone'] }}">
                        <div class="auth-dashboard-kpi-card__header">
                            <span class="auth-dashboard-kpi-card__icon-wrap">
                                <x-filament::icon :icon="$kpi['icon']" class="auth-dashboard-kpi-card__icon" />
                            </span>
                            <p class="auth-dashboard-kpi-card__label">{{ $kpi['label'] }}</p>
                        </div>

                        <p class="auth-dashboard-kpi-card__value">{{ $kpi['value'] }}</p>
                        <p class="auth-dashboard-kpi-card__hint">{{ $kpi['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="auth-dashboard-main-grid">
            <article class="auth-dashboard-card">
                <div class="auth-dashboard-card__header">
                    <h3 class="auth-dashboard-card__title">Última actividad de autenticación</h3>
                    <a href="{{ $links['users'] }}" class="auth-dashboard-link">Ver usuarios</a>
                </div>

                @if (count($recentActivity) === 0)
                    <article class="auth-dashboard-empty-card auth-dashboard-empty-card--compact">
                        <p class="auth-dashboard-empty-card__title">Sin eventos recientes</p>
                        <p class="auth-dashboard-empty-card__text">Cuando exista actividad de login u OAuth aparecerá aquí.</p>
                    </article>
                @else
                    <div class="auth-dashboard-table-wrap">
                        <table class="auth-dashboard-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Cliente</th>
                                    <th>Evento</th>
                                    <th>Fecha y hora</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentActivity as $row)
                                    <tr>
                                        <td>
                                            <div class="auth-dashboard-user-cell">
                                                <span class="auth-dashboard-user-cell__avatar">{{ $row['userInitial'] }}</span>
                                                <span class="auth-dashboard-user-cell__name">{{ $row['userName'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $row['clientName'] }}</td>
                                        <td>{{ $row['eventLabel'] }}</td>
                                        <td>
                                            <span class="auth-dashboard-table__date">{{ $row['occurredAt'] }}</span>
                                            <span class="auth-dashboard-table__date-meta">{{ $row['occurredAtHuman'] }}</span>
                                        </td>
                                        <td>
                                            <span class="auth-dashboard-status auth-dashboard-status--{{ $row['statusTone'] }}">
                                                {{ $row['statusLabel'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>

            <aside class="auth-dashboard-side-stack">
                <article class="auth-dashboard-card">
                    <h3 class="auth-dashboard-card__title">Distribución de actividad</h3>

                    <div class="auth-dashboard-donut" style="--auth-dashboard-donut-gradient: {{ $distribution['gradient'] }};">
                        <div class="auth-dashboard-donut__center">
                            <p class="auth-dashboard-donut__headline">{{ $distribution['headline'] }}</p>
                            <p class="auth-dashboard-donut__subheadline">{{ $distribution['subheadline'] }}</p>
                        </div>
                    </div>

                    <p class="auth-dashboard-donut__total">
                        {{ number_format($distribution['total']) }} evento(s) en la ventana seleccionada
                    </p>

                    @if (count($distribution['items']) > 0)
                        <ul class="auth-dashboard-distribution-list">
                            @foreach ($distribution['items'] as $item)
                                <li class="auth-dashboard-distribution-list__item">
                                    <span class="auth-dashboard-distribution-list__name">
                                        <span
                                            class="auth-dashboard-distribution-list__dot"
                                            style="background-color: {{ $item['color'] }}"
                                            aria-hidden="true"
                                        ></span>
                                        {{ $item['name'] }}
                                    </span>
                                    <span class="auth-dashboard-distribution-list__value">
                                        {{ number_format($item['total']) }} ({{ number_format((float) $item['percentage'], 1) }}%)
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>

                <article class="auth-dashboard-support-card">
                    <h3 class="auth-dashboard-support-card__title">{{ $support['title'] }}</h3>
                    <p class="auth-dashboard-support-card__description">{{ $support['description'] }}</p>
                    <a href="{{ $support['mailto'] }}" class="auth-dashboard-support-card__button">
                        Contactar soporte técnico
                    </a>
                </article>
            </aside>
        </section>
    </div>
</x-filament-panels::page>
