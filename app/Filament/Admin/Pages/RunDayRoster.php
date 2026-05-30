<?php

namespace App\Filament\Admin\Pages;

use App\Models\RunSlot;
use App\Models\RunSample;
use App\Models\Reservation;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RunDayRoster extends Page
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Qual Run Day';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Qualification Run Day Roster';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function getSlotsProperty()
    {
        return RunSlot::with(['reservations' => function ($q) {
                $q->whereIn('status', ['approved', 'completed'])->with('personnel');
            }])
            ->whereDate('slot_date', $this->date ?: now()->toDateString())
            ->orderBy('start_time')
            ->get();
    }

    /** Sampling sites from Settings (comma-separated). */
    public function samplingSites(): array
    {
        $raw = Setting::get('sampling_sites', 'Fingertips, Chest, Forearms');
        return collect(explode(',', $raw))->map(fn ($s) => trim($s))->filter()->values()->all();
    }

    /** Mark a run as performed (the run attendance sheet). Records the run + advances the stage. */
    public function markPerformedAction(): Action
    {
        return Action::make('markPerformed')
            ->label('Mark Performed')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Mark Run Performed')
            ->modalDescription('Confirms this operator gowned through the cleanroom. Records the run and advances their qualification.')
            ->action(function (array $arguments) {
                $resId = $arguments['reservation_id'] ?? null;
                $res = $resId ? Reservation::with('personnel')->find($resId) : null;
                if (! $res || ! $res->personnel) {
                    Notification::make()->danger()->title('Reservation not found')->send();
                    return;
                }
                $res->update(['status' => 'completed']);
                // record the run through the engine (advances workflow_stage to RunPerformed)
                app(\App\Services\QualificationEngine::class)
                    ->recordRun($res->personnel, \App\Enums\RunResult::Pass, [
                        'run_date' => now()->toDateString(),
                        'recorded_by' => Auth::id(),
                    ]);
                Notification::make()->success()->title('Run performed')
                    ->body(($res->personnel->full_name ?? 'Operator') . ' recorded. Ready for sampling.')->send();
            });
    }

    /** Enter LIMS results (worklist ID + per-site pass/fail) for one reservation.
     *  Incubation happens in LIMS, so this is the quick results-entry step that moves
     *  the card straight to QA Review. */
    public function recordSamplesAction(): Action
    {
        return Action::make('enterResults')
            ->label('Enter Results')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('success')
            ->modalHeading('Enter LIMS Results')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments) {
                $res = Reservation::find($arguments['reservation_id'] ?? null);
                return ['lims_worklist_id' => $res?->lims_worklist_id];
            })
            ->schema(function () {
                $fields = [
                    TextInput::make('lims_worklist_id')->label('LIMS Worklist ID')
                        ->placeholder('Worklist / batch reference from LIMS')->columnSpanFull(),
                    Select::make('overall')->label('Overall Result')
                        ->options(['pass' => 'Pass', 'fail' => 'Fail'])->required()->live()
                        ->helperText('The headline pass/fail for this run.')->columnSpanFull(),
                ];
                // optional per-site detail (collapsed by default via a section)
                $siteFields = [];
                foreach ($this->samplingSites() as $i => $site) {
                    $key = 'site_' . $i;
                    $siteFields[] = Select::make($key . '_result')->label($site)
                        ->options(['pass' => 'Pass', 'fail' => 'Fail', 'na' => 'N/A'])->default('pass');
                }
                if ($siteFields) {
                    $fields[] = Section::make('Per-Site Detail (Optional)')
                        ->columns(count($siteFields))->collapsed()->schema($siteFields);
                }
                return $fields;
            })
            ->action(function (array $data, array $arguments) {
                $resId = $arguments['reservation_id'] ?? null;
                $res = $resId ? Reservation::with('personnel')->find($resId) : null;
                if (! $res) {
                    Notification::make()->danger()->title('Reservation not found')->send();
                    return;
                }
                $overall = $data['overall'] ?? 'pass';
                $worklist = $data['lims_worklist_id'] ?? null;
                $res->update(['lims_worklist_id' => $worklist]);

                // store per-site rows if provided
                foreach ($this->samplingSites() as $i => $site) {
                    $key = 'site_' . $i . '_result';
                    if (isset($data[$key])) {
                        RunSample::updateOrCreate(
                            ['reservation_id' => $res->id, 'site' => $site],
                            ['personnel_id' => $res->personnel_id, 'result' => $data[$key], 'recorded_by' => Auth::id()],
                        );
                    }
                }

                if ($res->personnel) {
                    $q = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
                    $run = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)
                        ->latest('run_date')->latest('id')->first();
                    if ($run) {
                        $run->lims_worklist_id = $worklist;
                        $run->results_entered_at = now();
                        $run->results_released_at = now();
                        $run->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
                        $run->save();
                    }
                    if ($q) {
                        // fail goes to QA determination; pass goes to QA review queue
                        $q->workflow_stage = $overall === 'fail'
                            ? \App\Enums\WorkflowStage::Failed
                            : \App\Enums\WorkflowStage::QaReview;
                        $q->stage_changed_at = now();
                        $q->save();
                    }
                }
                Notification::make()->success()->title('Results entered')
                    ->body(($res->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail' ? ', sent to QA determination.' : ', sent to QA review.'))->send();
            });
    }
}
