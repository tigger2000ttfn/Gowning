<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Models\RunSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunSlotResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static ?string $model = RunSlot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Run Slot';
    protected static ?string $navigationLabel = 'Run Scheduler';
    protected static ?string $pluralModelLabel = 'Run Days';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Wizard::make([
                \Filament\Schemas\Components\Wizard\Step::make('Day & Capacity')
                    ->icon('heroicon-o-calendar-days')->description('When and how many')
                    ->columns(2)->schema([
                        TextInput::make('cleanroom')->required(),
                        Select::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])->default('open')->required(),
                        DatePicker::make('slot_date')->native(false)->displayFormat('d M Y')->label('Date')->required(),
                        TextInput::make('capacity')->numeric()->minValue(1)
                            ->default(fn () => (int) \App\Models\Setting::get('runs_per_day_capacity', 6))->required()
                            ->helperText('Defaults to the per-day capacity from Settings.'),
                        TimePicker::make('start_time')->native(false)->displayFormat('H:i'),
                        TimePicker::make('end_time')->native(false)->displayFormat('H:i'),
                    ]),
                \Filament\Schemas\Components\Wizard\Step::make('Assignment & Notes')
                    ->icon('heroicon-o-user')->description('Who runs it')
                    ->schema([
                        Select::make('assigned_analyst_id')->label('Assigned QC Micro Analyst')
                            ->options(fn () => \App\Models\User::where('is_active', true)->get()
                                ->filter(fn ($u) => $u->hasCapability(\App\Enums\Capability::RecordRuns) || $u->hasCapability(\App\Enums\Capability::ManageScheduling))
                                ->pluck('name', 'id')->all())
                            ->searchable()->placeholder('Unassigned'),
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
            ])->columnSpanFull()->skippable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_date')->icon('heroicon-m-calendar')->date()->sortable(),
                TextColumn::make('cleanroom')->icon('heroicon-m-home-modern')->searchable(),
                TextColumn::make('start_time')->time('H:i')->placeholder('—'),
                TextColumn::make('capacity'),
                TextColumn::make('analyst.name')->label('Analyst')->placeholder('Unassigned')->icon('heroicon-m-user'),
                TextColumn::make('reservations_count')->counts('reservations')->label('Requests'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->value === 'open' ? 'success' : 'gray'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')->options(['open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled']),
                \Filament\Tables\Filters\SelectFilter::make('cleanroom')
                    ->options(fn () => \App\Models\RunSlot::query()->distinct()->orderBy('cleanroom')->pluck('cleanroom', 'cleanroom')->all()),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('cancelDay')
                    ->label('Cancel Day')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => ($record->status->value ?? $record->status) !== 'cancelled')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel This Run Day')
                    ->modalDescription('Everyone booked will be auto-rescheduled to the next available day, with notifications sent.')
                    ->action(function ($record) {
                        $moved = app(\App\Services\AutoScheduler::class)->cancelSlot($record);
                        \Filament\Notifications\Notification::make()->success()
                            ->title('Run day cancelled')
                            ->body("{$moved} reservation(s) rescheduled and notified.")->send();
                    }),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('slot_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRunSlots::route('/'),
        ];
    }
}
