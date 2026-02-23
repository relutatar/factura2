<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpiringContractsWidget;
use App\Filament\Widgets\InvoiceStatsWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\UnpaidInvoicesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Panou principal';
    protected static ?string $title           = 'Panou principal';
    protected static ?int    $navigationSort  = -2;

    /**
     * Returns the list of widget classes for this dashboard.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            InvoiceStatsWidget::class,
            UnpaidInvoicesWidget::class,
            ExpiringContractsWidget::class,
            LowStockWidget::class,
            RevenueChartWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 3;
    }
}
