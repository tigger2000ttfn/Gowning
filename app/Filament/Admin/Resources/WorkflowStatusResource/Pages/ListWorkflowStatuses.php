<?php

namespace App\Filament\Admin\Resources\WorkflowStatusResource\Pages;

use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\WorkflowStatusResource;
use App\Models\WorkflowStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListWorkflowStatuses extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Statuses';
    public ?string $gqsSubtitle = 'Rename, recolor, reorder, and add workflow statuses. Built-in statuses can be renamed and recolored; custom ones can be added per workflow.';
    public ?string $gqsIcon = 'heroicon-o-swatch';
    protected static string $resource = WorkflowStatusResource::class;
    public function getHeading(): string { return ''; }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Status')
                ->mutateDataUsing(function (array $data): array {
                    if (empty($data['key'])) {
                        $base = Str::slug($data['label'] ?? 'status', '_') ?: 'status';
                        $key = $base; $i = 2;
                        while (WorkflowStatus::where('domain', $data['domain'])->where('key', $key)->exists()) {
                            $key = $base . '_' . $i; $i++;
                        }
                        $data['key'] = $key;
                    }
                    $data['is_system'] = false;
                    return $data;
                }),
        ];
    }
}
