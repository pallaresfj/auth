<?php

namespace App\Filament\Resources\OAuthClients\Pages;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use App\Models\OAuthClient;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageOAuthClients extends ManageRecords
{
    protected static string $resource = OAuthClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo cliente OAuth')
                ->createAnother(false)
                ->using(fn (array $data): OAuthClient => OAuthClientResource::createClient($data))
                ->after(function (CreateAction $action): void {
                    $record = $action->getRecord();

                    if (! $record instanceof OAuthClient) {
                        return;
                    }

                    $secret = (string) $record->plainSecret;

                    Notification::make()
                        ->title('Cliente OAuth creado')
                        ->body("Client ID: {$record->getKey()}\nClient Secret: {$secret}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
