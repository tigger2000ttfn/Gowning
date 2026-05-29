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
            Section::make('Session')->columns(2)->schema([
                DatePicker::make('session_date')->required()->native(false),
                Select::make('status')->options([
                    'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled',
                ])->default('open')->required(),
                TimePicker::make('start_time')->seconds(false),
                TimePicker::make('end_time')->seconds(false),
                TextInput::make('location')->maxLength(255),
                TextInput::make('instructor')->maxLength(255),
                TextInput::make('capacity')->numeric()->default(20)->required()->minValue(1),
            ]),
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
                CreateAction::make()->label('Add Session')->modalHeading('Schedule a Session'),
            ])
            ->recordActions([
                Action::make('attendance')
                    ->label('Attendance')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('success')
                    ->modalHeading(fn (ClassSession $record) => 'Attendance — ' . $record->session_date->format('M j, Y'))
                    ->modalWidth('xl')
                    ->fillForm(function (ClassSession $record) {
                        return [
                            'rows' => $record->enrollments->map(fn ($e) => [
                                'enrollment_id' => $e->id,
                                'who' => trim(($e->employee_id ? $e->employee_id . ' — ' : '') . $e->name),
                                'present' => $e->status === 'attended',
                            ])->all(),
                        ];
                    })
                    ->schema([
                        Repeater::make('rows')
                            ->label('Enrolled')
                            ->schema([
                                Hidden::make('enrollment_id'),
                                \Filament\Forms\Components\TextInput::make('who')->label('Person')->disabled()->dehydrated(),
                                \Filament\Forms\Components\Toggle::make('present')->label('Present'),
                            ])
                            ->columns(2)
                            ->addable(false)->deletable(false)->reorderable(false),
                    ])
                    ->action(function (ClassSession $record, array $data) {
                        $marked = 0;
                        foreach ($data['rows'] ?? [] as $row) {
                            $enr = ClassEnrollment::find($row['enrollment_id']);
                            if (! $enr) continue;
                            if (! empty($row['present'])) {
                                $enr->update(['status' => 'attended']);
                                // generate the class-completion record (prerequisite for runs)
                                ClassCompletion::firstOrCreate(
                                    ['personnel_id' => $enr->personnel_id, 'class_name' => $record->trainingClass->name, 'completion_date' => $record->session_date->toDateString()],
                                    ['employee_id' => $enr->employee_id, 'source' => 'attendance'],
                                );
                                $marked++;
                            } else {
                                if ($enr->status === 'attended') $enr->update(['status' => 'signed_up']);
                            }
                        }
                        Notification::make()->success()->title('Attendance recorded')->body("{$marked} marked present; completions generated.")->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('session_date', 'desc');
    }
}
