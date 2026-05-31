<x-filament-panels::page>
    @php
        $q = $record; $q->loadMissing('personnel', 'children');
        $stageVal = $q->workflow_stage?->value;
        $reviewUrl = match ($stageVal) {
            'awaiting_results', 'results_released' => \App\Filament\Admin\Pages\IncubationBoard::getUrl(['tab' => 'evaluation', 'evaluate' => $q->id]),
            'qa_review', 'qa_signoff', 'failed' => \App\Filament\Admin\Pages\QaQueue::getUrl(),
            'class_pending', 'class_complete' => \App\Filament\Admin\Pages\ClassScheduler::getUrl(),
            'run_scheduled', 'run_performed', 'incubating' => \App\Filament\Admin\Pages\RunDayRoster::getUrl(['person' => $q->personnel_id]),
            default => null,
        };
        $reviewLabel = match ($stageVal) {
            'awaiting_results', 'results_released' => 'Lab Review',
            'qa_review', 'qa_signoff', 'failed' => 'QA Review',
            'class_pending', 'class_complete' => 'Class Scheduler',
            'run_scheduled', 'run_performed', 'incubating' => 'Run Scheduler',
            default => null,
        };
        $canManage = auth()->user()?->hasCapability(\App\Enums\Capability::ManagePersonnel);
    @endphp

    {{-- Header: back nav + breadcrumb on the left, actions on the right --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <a href="{{ \App\Filament\Admin\Resources\QualificationResource::getUrl('index') }}" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <x-filament::icon icon="heroicon-m-arrow-left" style="width:16px;height:16px;"/> Back To Active Runs
            </a>
            <span style="color:var(--gqs-text-dim,#9A9AA4);font-size:13px;">Active Runs / {{ $q->personnel?->full_name ?? 'Record' }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            @if($reviewUrl)
                <a href="{{ $reviewUrl }}" class="gqs-btn" style="background:#1F6FB2;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" style="width:15px;height:15px;"/> Open In {{ $reviewLabel }}
                </a>
            @endif
            @if($canManage && $q->personnel)
                <a href="{{ \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $q->personnel->id]) }}" class="gqs-btn gqs-btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <x-filament::icon icon="heroicon-m-pencil-square" style="width:15px;height:15px;"/> Edit Personnel
                </a>
            @endif
        </div>
    </div>

    {{-- Reuse the detail content (it reads $getRecord; provide it) --}}
    @include('filament.qualification-detail', ['getRecord' => fn () => $q])
</x-filament-panels::page>
