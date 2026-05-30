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
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::QaReview));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::QaReview));
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

    /** Non-conformance trending: counts by type and by organism (last 12 months). */
    public function ncTrend(): array
    {
        $since = now()->subMonths(12);
        $byType = \App\Models\NonConformance::where('observed_date', '>=', $since->toDateString())
            ->selectRaw('nc_type, count(*) as n')->groupBy('nc_type')->pluck('n', 'nc_type')->all();
        $byOrganism = \App\Models\NonConformance::where('observed_date', '>=', $since->toDateString())
            ->whereNotNull('organism')->where('organism', '!=', '')
            ->selectRaw('organism, count(*) as n')->groupBy('organism')->orderByDesc('n')->limit(10)->pluck('n', 'organism')->all();
        $bySite = \App\Models\NonConformance::where('observed_date', '>=', $since->toDateString())
            ->whereNotNull('site')->where('site', '!=', '')
            ->selectRaw('site, count(*) as n')->groupBy('site')->orderByDesc('n')->pluck('n', 'site')->all();
        return ['type' => $byType, 'organism' => $byOrganism, 'site' => $bySite,
                'open' => \App\Models\NonConformance::where('status', 'open')->count()];
    }

    /** Multi-sheet XLSX compliance workbook (no composer dependency, via SimpleXlsx). */
    public function exportXlsx(): StreamedResponse
    {
        $xlsx = new \App\Support\SimpleXlsx();

        // Overdue
        $overdue = [['Employee ID', 'Name', 'Department', 'Due Date']];
        foreach ($this->overdue as $q) {
            $overdue[] = [$q->personnel?->employee_id, $q->personnel?->full_name, $q->personnel?->department, $q->due_date?->toDateString()];
        }
        $xlsx->sheet('Overdue', $overdue);

        // Upcoming
        $upcoming = [['Employee ID', 'Name', 'Department', 'Due Date']];
        foreach ($this->upcoming as $q) {
            $upcoming[] = [$q->personnel?->employee_id, $q->personnel?->full_name, $q->personnel?->department, $q->due_date?->toDateString()];
        }
        $xlsx->sheet('Upcoming 60 Days', $upcoming);

        // Run results
        $runs = [['Employee ID', 'Name', 'Run Date', 'Result', 'Worklist', 'Veeva Doc']];
        foreach (QualificationRun::with('personnel')->latest('run_date')->limit(2000)->get() as $r) {
            $runs[] = [$r->personnel?->employee_id, $r->personnel?->full_name, $r->run_date?->toDateString(),
                $r->result?->value, $r->lims_worklist_id, $r->veeva_doc_number];
        }
        $xlsx->sheet('Run Results', $runs);

        // NC trending
        $nc = $this->ncTrend();
        $ncRows = [['Category', 'Key', 'Count']];
        foreach ($nc['type'] as $k => $v) { $ncRows[] = ['Type', \Illuminate\Support\Str::headline(str_replace('_',' ',$k)), $v]; }
        foreach ($nc['organism'] as $k => $v) { $ncRows[] = ['Organism', $k, $v]; }
        foreach ($nc['site'] as $k => $v) { $ncRows[] = ['Site', $k, $v]; }
        $xlsx->sheet('NC Trending', $ncRows);

        return $xlsx->download('gqs_compliance_' . now()->format('Ymd_His') . '.xlsx');
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
