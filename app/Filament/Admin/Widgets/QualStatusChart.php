<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Qualification;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class QualStatusChart extends ChartWidget
{
    protected ?string $heading = 'Qualification Status';
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }

    protected function getData(): array
    {
        $counts = Qualification::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $labels = ['qualified' => 'Qualified', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'lapsed' => 'Lapsed'];
        $colors = ['qualified' => '#2E7D5B', 'in_progress' => '#C79A2E', 'pending' => '#6B2C91', 'lapsed' => '#C8102E'];

        $data = []; $lbls = []; $clrs = [];
        foreach ($labels as $key => $label) {
            $lbls[] = $label;
            $data[] = $counts[$key] ?? 0;
            $clrs[] = $colors[$key];
        }

        return [
            'datasets' => [['data' => $data, 'backgroundColor' => $clrs, 'borderWidth' => 0]],
            'labels' => $lbls,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
