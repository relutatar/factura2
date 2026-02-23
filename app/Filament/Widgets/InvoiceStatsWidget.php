<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $companyId = session('active_company_id');

        $totalRevenue = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Platita)
            ->sum('total');

        $pendingCount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->count();

        $pendingAmount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->sum('total');

        $overdueCount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->where('due_date', '<', now())
            ->count();

        $thisMonthCount = Invoice::where('company_id', $companyId)
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->count();

        return [
            Stat::make('Venituri încasate', number_format($totalRevenue, 2, ',', '.') . ' RON')
                ->description('Din facturi plătite')
                ->descriptionIcon('heroicon-s-banknotes')
                ->color('success'),

            Stat::make('Facturi neîncasate', $pendingCount)
                ->description(number_format($pendingAmount, 2, ',', '.') . ' RON în așteptare')
                ->descriptionIcon('heroicon-s-clock')
                ->color('warning'),

            Stat::make('Facturi restante', $overdueCount)
                ->description('Scadență depășită')
                ->descriptionIcon('heroicon-s-exclamation-circle')
                ->color($overdueCount > 0 ? 'danger' : 'gray'),

            Stat::make('Total emise (lună)', $thisMonthCount)
                ->description('Facturi emise luna aceasta')
                ->descriptionIcon('heroicon-s-document-text')
                ->color('info'),
        ];
    }
}
