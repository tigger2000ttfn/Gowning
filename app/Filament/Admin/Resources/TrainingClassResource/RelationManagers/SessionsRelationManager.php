<?php

namespace App\Filament\Admin\Resources\TrainingClassResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select as FormSelect;
use App\Models\ClassEnrollment;
use App\Models\ClassCompletion;
use App\Models\ClassSession;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';
    protected static ?string $title = 'Scheduled Sessions';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Wizard::make([
                \Filament\Schemas\Components\Wizard\Step::make('Schedule')
                    ->icon('heroicon-o-calendar')->description('Date, time, capacity')
                    ->columns(2)->schema([
                        DatePicker::make('session_date')->native(false)->displayFormat('d-M-Y')->required()->native(false),
                        Select::make('status')->options([
                            'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled',
                        ])->default('open')->required(),
                        TimePicker::make('start_time')->native(false)->displayFormat('H:i')->seconds(false),
                        TimePicker::make('end_time')->native(false)->displayFormat('H:i')->seconds(false),
                        TextInput::make('capacity')->numeric()->default(20)->required()->minValue(1),
                        TextInput::make('location')->maxLength(255),
                    ]),
                \Filament\Schemas\Components\Wizard\Step::make('Instructor')
                    ->icon('heroicon-o-user')->description('Who teaches it')
                    ->columns(2)->schema([
                        Select::make('assigned_instructor_id')->label('Assigned Instructor')
                            ->options(fn () => \App\Models\User::where('is_active', true)->get()
                                ->filter(fn ($u) => $u->hasCapability(\App\Enums\Capability::ManageClasses) || $u->hasCapability(\App\Enums\Capability::ManageAttendance))
                                ->pluck('name', 'id')->all())
                            ->searchable()->placeholder('Unassigned'),
                        TextInput::make('instructor')->label('Instructor (Free Text)')->maxLength(255)
                            ->helperText('Optional, if not a system user.'),
                    ]),
            ])->columnSpanFull()->skippable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_date')->date()->sortable(),
                TextColumn::make('start_time')->time('g:i A')->placeholder('—'),
                TextColumn::make('location')->placeholder('—'),
                TextColumn::make('instructor')->placeholder('—'),
                TextColumn::make('capacity')->badge(),
                TextColumn::make('enrollments_count')->counts('enrollments')->label('Enrolled')->badge()->color('success'),
                TextColumn::make('status')->badge()->color(fn ($s) => match($s) {
                    'open' => 'success', 'cancelled' => 'danger', default => 'gray',
                }),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Session')->modalHeading('Schedule A Session'),
            ])
            ->recordActions([
                Action::make('attendance')
                    ->label('Attendance')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('success')
                    ->url(fn (ClassSession $record) => \App\Filament\Admin\Pages\ClassScheduler::getUrl(['attend' => $record->id]))
                    ->tooltip('Open the attendance sheet (draft until submitted with the trainer e-signature).'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('session_date', 'desc');
    }
}
