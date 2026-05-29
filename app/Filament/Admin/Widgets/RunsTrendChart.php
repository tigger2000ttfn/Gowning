<?php

namespace App\Filament\Admin\Widgets;

use App\Models\QualificationRun;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class RunsTrendChart extends ChartWidget
{
    protected ?string $heading = 'Run Results (Last 6 Months)';
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        $r = Auth::user()?->role;
        return (bool) ($r && $r->canManagePersonnel());
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn ($m) => $m->format('M'))->all();

        $pass = []; $fail = [];
        foreach ($months as $m) {
            $start = $m->copy(); $end = $m->copy()->endOfMonth();
            $pass[] = QualificationRun::where('result', 'pass')->whereBetween('run_date', [$start, $end])->count();
            $fail[] = QualificationRun::where('result', 'fail')->whereBetween('run_date', [$start, $end])->count();
        }

        return [
            'datasets' => [
                ['label' => 'Pass', 'data' => $pass, 'backgroundColor' => '#2E7D5B', 'borderColor' => '#2E7D5B'],
                ['label' => 'Fail', 'data' => $fail, 'backgroundColor' => '#C8102E', 'borderColor' => '#C8102E'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
