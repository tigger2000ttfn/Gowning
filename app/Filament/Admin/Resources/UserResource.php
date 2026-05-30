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
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
                Select::make('role')->options(
                    collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()
                )->required(),
                Select::make('approval_status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
                ])->required(),
                Select::make('linked_personnel')->label('Linked Personnel Record')
                    ->options(fn () => \App\Models\Personnel::orderBy('employee_id')->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "{$r->employee_id} — {$r->full_name}"]))
                    ->searchable()
                    ->afterStateHydrated(fn ($component, $record) => $component->state($record?->personnel?->id))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (\App\Models\User $record, $state) {
                        \App\Models\Personnel::where('user_id', $record->id)->update(['user_id' => null]);
                        if ($state) { \App\Models\Personnel::where('id', $state)->update(['user_id' => $record->id]); }
                    })
                    ->helperText('Link this login to an employee record so their qualification shows in My Qualification.'),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('Team Membership')->icon('heroicon-o-user-group')->columns(2)->schema([
                Select::make('team')->label('Team')
                    ->options(\App\Enums\Team::options())
                    ->placeholder('No team')
                    ->helperText('Which working team this staff member belongs to.'),
                Toggle::make('is_team_manager')->label('Team Manager')
                    ->helperText('Managers can assign work and see their team view.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->icon('heroicon-m-user')->searchable()->sortable()->weight('bold'),
                TextColumn::make('email')->icon('heroicon-m-envelope')->searchable()->copyable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn ($r) => $r?->label()),
                TextColumn::make('approval_status')->badge()->label('Approval')
                    ->color(fn ($s) => match($s) {
                        'approved' => 'success', 'rejected' => 'danger', default => 'warning',
                    }),
                TextColumn::make('team')->label('Team')->badge()
                    ->formatStateUsing(fn ($s) => $s ? \App\Enums\Team::tryFrom($s)?->label() : '-')
                    ->color(fn ($s) => $s === 'qcm' ? 'info' : ($s === 'qa' ? 'warning' : 'gray'))->toggleable(),
                IconColumn::make('is_team_manager')->boolean()->label('Mgr')->toggleable(),
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
