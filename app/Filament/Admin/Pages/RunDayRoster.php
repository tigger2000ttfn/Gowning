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

    /** Record microbiological samples (per site pass/fail) for one reservation. */
    public function recordSamplesAction(): Action
    {
        return Action::make('recordSamples')
            ->label('Record Samples')
            ->icon('heroicon-m-beaker')
            ->modalHeading('Record Microbiological Samples')
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => ['reservation_id' => $arguments['reservation_id'] ?? null])
            ->schema(function () {
                $fields = [];
                foreach ($this->samplingSites() as $i => $site) {
                    $key = 'site_' . $i;
                    $fields[] = Section::make($site)->columns(3)->schema([
                        Select::make($key . '_result')->label('Result')
                            ->options(['pass' => 'Pass', 'fail' => 'Fail', 'pending' => 'Pending'])
                            ->default('pending')->required(),
                        TextInput::make($key . '_plate')->label('Plate / LIMS ID'),
                        TextInput::make($key . '_cfu')->label('CFU Count')->numeric()->minValue(0),
                    ]);
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
                foreach ($this->samplingSites() as $i => $site) {
                    $key = 'site_' . $i;
                    RunSample::updateOrCreate(
                        ['reservation_id' => $res->id, 'site' => $site],
                        [
                            'personnel_id' => $res->personnel_id,
                            'result' => $data[$key . '_result'] ?? 'pending',
                            'plate_id' => $data[$key . '_plate'] ?? null,
                            'cfu_count' => $data[$key . '_cfu'] ?? null,
                            'recorded_by' => Auth::id(),
                        ]
                    );
                }
                // advance the person's workflow stage to Samples Taken
                if ($res->personnel) {
                    $q = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
                    if ($q && in_array($q->workflow_stage?->value, ['run_scheduled', 'run_performed'], true)) {
                        $q->workflow_stage = \App\Enums\WorkflowStage::SamplesTaken;
                        $q->stage_changed_at = now();
                        $q->save();
                    }
                }
                Notification::make()->success()->title('Samples recorded')
                    ->body(($res->personnel?->full_name ?? 'Operator') . ', ' . count($this->samplingSites()) . ' sites logged.')->send();
            });
    }
}
