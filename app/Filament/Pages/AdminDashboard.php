<?php

namespace App\Filament\Pages;

use App\Support\Dashboard\AuthDashboardDataBuilder;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;

class AdminDashboard extends Dashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.pages.admin-dashboard';

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $window = (string) request()->query('window', '30d');

        return app(AuthDashboardDataBuilder::class)->build($window);
    }
}
