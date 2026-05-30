<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Role;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageUsers));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageUsers));
    }
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;
    protected static ?string $pluralModelLabel = 'Users & Approvals';

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::where('approval_status', 'pending')->count();
        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->icon('heroicon-o-user-circle')->columns(2)->schema([
                TextInput::make('first_name')->label('First Name')->required(),
                TextInput::make('last_name')->label('Last Name')->required(),
                TextInput::make('email')->label('Email Address')->email()->required()->unique(ignoreRecord: true),
                Select::make('role')->label('Role')->options(
                    collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()
                )->required(),
                Select::make('approval_status')->label('Approval Status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
                ])->default('approved')->required(),
                Select::make('linked_personnel')->label('Linked Personnel Record')
                    ->options(fn () => \App\Models\Personnel::orderBy('employee_id')->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "{$r->employee_id} · {$r->full_name}"]))
                    ->searchable()
                    ->afterStateHydrated(fn ($component, $record) => $component->state($record?->personnel?->id))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (\App\Models\User $record, $state) {
                        \App\Models\Personnel::where('user_id', $record->id)->update(['user_id' => null]);
                        if ($state) { \App\Models\Personnel::where('id', $state)->update(['user_id' => $record->id]); }
                    })
                    ->helperText('Links This Login To An Employee Record.'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
            Section::make('Password')->icon('heroicon-o-key')->columns(2)->schema([
                TextInput::make('password')->label('Password')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave Blank When Editing To Keep The Current Password.'),
                Toggle::make('must_change_password')->label('Require Password Change On First Login')
                    ->default(true)->inline(false),
            ]),
            Section::make('Team Membership')->icon('heroicon-o-user-group')->columns(2)->schema([
                Select::make('team')->label('Team')
                    ->options(\App\Enums\Team::options())
                    ->placeholder('No Team'),
                Toggle::make('is_team_manager')->label('Team Manager'),
                Toggle::make('can_sample')->label('Qualified: Run Sampling'),
                Toggle::make('can_teach')->label('Qualified: Classroom Training'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->icon('heroicon-m-user')->searchable(['first_name','last_name'])->sortable()->weight('bold'),
                TextColumn::make('email')->label('Email Address')->icon('heroicon-m-envelope')->searchable()->copyable(),
                TextColumn::make('role')->label('Role')->badge()->formatStateUsing(fn ($r) => $r?->label()),
                TextColumn::make('approval_status')->badge()->label('Approval')
                    ->color(fn ($s) => match($s) {
                        'approved' => 'success', 'rejected' => 'danger', default => 'warning',
                    }),
                TextColumn::make('team')->label('Team')->badge()
                    ->formatStateUsing(fn ($s) => $s ? \App\Enums\Team::tryFrom($s)?->label() : '-')
                    ->color(fn ($s) => $s === 'qcm' ? 'info' : ($s === 'qa' ? 'warning' : 'gray'))->toggleable(),
                IconColumn::make('is_team_manager')->boolean()->label('Mgr')->toggleable(),
                IconColumn::make('can_sample')->boolean()->label('Sampling')->toggleable(),
                IconColumn::make('can_teach')->boolean()->label('Teach')->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('created_at')->dateTime()->since()->label('Requested')->toggleable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('team')->options(\App\Enums\Team::options()),
                SelectFilter::make('approval_status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
                ])->label('Approval Status'),
            ])
            ->recordActions([
                Action::make('approve')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (User $u) => $u->approval_status === 'pending')
                    ->action(function (User $u) {
                        $u->update(['approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => Auth::id()]);
                        Notification::make()->success()->title('User approved')->body($u->name . ' can now sign in.')->send();
                    }),
                Action::make('reject')->icon('heroicon-m-x-mark')->color('danger')
                    ->visible(fn (User $u) => $u->approval_status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (User $u) {
                        $u->update(['approval_status' => 'rejected']);
                        Notification::make()->title('User rejected')->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
