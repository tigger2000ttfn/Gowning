<?php

namespace App\Filament\Admin\Pages;

use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\ClassCompletion;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return $r && $r->canQaReview();
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return (bool) ($r && $r->canQaReview());
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 8;
    protected static ?string $title = 'Reports'; // lives in Manage menu

    protected string $view = 'filament.pages.reports';

    public function getOverdueProperty()
    {
        return Qualification::with('personnel')
            ->whereNotNull('due_date')->whereDate('due_date', '<', now())
            ->orderBy('due_date')->get();
    }

    public function getUpcomingProperty()
    {
        return Qualification::with('personnel')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays(60)])
            ->orderBy('due_date')->get();
    }

    public function getPassFailProperty()
    {
        return QualificationRun::selectRaw('result, count(*) as n')->groupBy('result')->pluck('n', 'result')->all();
    }

    public function getClassStatsProperty()
    {
        return ClassCompletion::selectRaw('class_name, count(*) as n')->groupBy('class_name')->orderByDesc('n')->get();
    }

    /** CSV export for LIMS handoff (recent run results). */
    public function exportRuns(): StreamedResponse
    {
        $rows = QualificationRun::with('personnel')->latest('run_date')->limit(1000)->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee ID', 'Name', 'Run Date', 'Result', 'Cycle', 'Recorded By']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->personnel?->employee_id,
                    $r->personnel?->full_name,
                    $r->run_date?->toDateString(),
                    $r->result?->value,
                    $r->cycle_type?->value ?? '',
                    $r->recorded_by,
                ]);
            }
            fclose($out);
        }, 'run_results_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
