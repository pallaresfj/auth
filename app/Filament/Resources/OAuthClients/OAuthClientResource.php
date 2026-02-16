<?php

namespace App\Filament\Resources\OAuthClients;

use App\Filament\Resources\OAuthClients\Pages\ManageOAuthClients;
use App\Models\OAuthClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthClientResource extends Resource
{
    protected static ?string $model = OAuthClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Clientes OAuth';

    protected static ?string $modelLabel = 'Cliente OAuth';

    protected static ?string $pluralModelLabel = 'Clientes OAuth';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TagsInput::make('redirect_uris')
                    ->label('Redirect URIs')
                    ->placeholder('https://app.iedagropivijay.edu.co/callback')
                    ->required()
                    ->afterStateHydrated(function (TagsInput $component, mixed $state): void {
                        $component->state(static::normalizeStringArrayState($state));
                    })
                    ->helperText('Cada URI debe ser exacta, HTTPS y sin wildcard.'),
                TagsInput::make('scopes')
                    ->label('Scopes permitidos')
                    ->required()
                    ->default(['openid', 'email', 'profile'])
                    ->afterStateHydrated(function (TagsInput $component, mixed $state): void {
                        $component->state(static::normalizeStringArrayState($state));
                    })
                    ->suggestions(array_keys(config('openid.passport.tokens_can', []))),
                Toggle::make('revoked')
                    ->label('Revocado')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Client ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('redirect_uris')
                    ->label('Redirect URIs')
                    ->formatStateUsing(static fn ($state): string => implode("\n", static::normalizeStringArrayState($state)))
                    ->wrap(),
                TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(','),
                IconColumn::make('revoked')
                    ->label('Revocado')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->using(fn (OAuthClient $record, array $data): OAuthClient => static::updateClient($record, $data)),
                Action::make('regenerate_secret')
                    ->label('Regenerar secret')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->action(function (OAuthClient $record): void {
                        $secret = static::regenerateSecret($record);

                        Notification::make()
                            ->title('Nuevo client secret generado')
                            ->body("Guárdalo ahora. Secret: {$secret}")
                            ->success()
                            ->send();
                    }),
                Action::make('toggle_revoked')
                    ->label(fn (OAuthClient $record): string => $record->revoked ? 'Activar' : 'Revocar')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color(fn (OAuthClient $record): string => $record->revoked ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(fn (OAuthClient $record): bool => $record->update(['revoked' => ! $record->revoked])),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOAuthClients::route('/'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createClient(array $data): OAuthClient
    {
        $redirectUris = static::sanitizeRedirectUris($data['redirect_uris'] ?? []);
        $scopes = static::sanitizeScopes($data['scopes'] ?? []);

        /** @var OAuthClient $client */
        $client = OAuthClient::query()->create([
            'name' => $data['name'],
            'secret' => Str::random(40),
            'provider' => null,
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => $scopes,
            'revoked' => (bool) ($data['revoked'] ?? false),
        ]);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateClient(OAuthClient $record, array $data): OAuthClient
    {
        $record->forceFill([
            'name' => $data['name'],
            'redirect_uris' => static::sanitizeRedirectUris($data['redirect_uris'] ?? []),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => static::sanitizeScopes($data['scopes'] ?? []),
            'revoked' => (bool) ($data['revoked'] ?? false),
        ])->save();

        return $record;
    }

    public static function regenerateSecret(OAuthClient $record): string
    {
        $record->secret = Str::random(40);
        $record->save();

        return (string) $record->plainSecret;
    }

    /**
     * @param  array<int, mixed>  $uris
     * @return array<int, string>
     */
    public static function sanitizeRedirectUris(array $uris): array
    {
        $hosts = config('sso.allowed_redirect_hosts', []);

        $normalized = collect($uris)
            ->map(static fn ($uri): string => trim((string) $uri))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'redirect_uris' => 'Debes registrar al menos una redirect URI.',
            ]);
        }

        foreach ($normalized as $uri) {
            if (str_contains($uri, '*')) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "No se permiten wildcards en redirect URI: {$uri}",
                ]);
            }

            $parts = parse_url($uri);

            if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "La redirect URI no es válida: {$uri}",
                ]);
            }

            if (mb_strtolower((string) $parts['scheme']) !== 'https') {
                throw ValidationException::withMessages([
                    'redirect_uris' => "La redirect URI debe usar HTTPS: {$uri}",
                ]);
            }

            if (! in_array(mb_strtolower((string) $parts['host']), $hosts, true)) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "Host no permitido en redirect URI: {$uri}",
                ]);
            }
        }

        return $normalized->unique()->values()->all();
    }

    /**
     * @param  array<int, mixed>  $scopes
     * @return array<int, string>
     */
    public static function sanitizeScopes(array $scopes): array
    {
        $allowedScopes = array_keys(config('openid.passport.tokens_can', []));

        $normalized = collect($scopes)
            ->map(static fn ($scope): string => trim((string) $scope))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            $normalized = collect(['openid', 'email', 'profile']);
        }

        $invalidScopes = $normalized->reject(static fn (string $scope): bool => in_array($scope, $allowedScopes, true));

        if ($invalidScopes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scopes' => 'Scopes inválidos: '.$invalidScopes->implode(', '),
            ]);
        }

        return $normalized->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeStringArrayState(mixed $state): array
    {
        if (is_array($state)) {
            return collect($state)
                ->map(static fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        if (! is_string($state)) {
            return [];
        }

        $state = trim($state);

        if ($state === '') {
            return [];
        }

        $decoded = json_decode($state, true);

        if (is_array($decoded)) {
            return collect($decoded)
                ->map(static fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return collect(explode(',', $state))
            ->map(static fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
