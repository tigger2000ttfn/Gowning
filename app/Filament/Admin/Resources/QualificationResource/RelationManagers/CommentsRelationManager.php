<?php

namespace App\Filament\Admin\Resources\QualificationResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';
    protected static ?string $title = 'Running comments';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')->label('Comment')->required()->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')->label('Comment')->wrap(),
                TextColumn::make('author_name')->label('By')->default(fn ($r) => $r->user?->name),
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('Add comment')
                    ->modalHeading('Add a running comment')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['author_name'] = Auth::user()?->name;
                        return $data;
                    }),
            ])
            ->actions([]);   // append-only: no edit/delete in the GxP trail
    }
}
