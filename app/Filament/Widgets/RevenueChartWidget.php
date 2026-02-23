<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort        = 5;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading  = 'Venituri lunare (ultimele 12 luni)';
    protected static string $color     = 'success';

    protected function getData(): array
    {
        $companyId = session('active_company_id');
        $months    = collect(range(11, 0))->map(fn (int $i) => now()->subMonths($i));

        $revenues = $months->map(fn (Carbon $month) => Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', InvoiceStatus::Platita)
            ->whereYear('paid_at', $month->year)
            ->whereMonth('paid_at', $month->month)
            ->sum('total')
        );

        return [
            'datasets' => [
                [
                    'label'           => 'Venituri (RON)',
                    'data'            => $revenues->values()->all(),
                    'fill'            => false,
                    'borderColor'     => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $months->map(fn (Carbon $m) => $m->locale('ro')->isoFormat('MMM YYYY'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
