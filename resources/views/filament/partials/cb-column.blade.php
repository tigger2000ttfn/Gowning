{{-- One Class Board status column. Expects $status (key) and $col = ['label','color','cards']. --}}
<div class="kanban-col">
    <div class="kanban-head" style="background:{{ $col['color'] }};">
        <span>{{ $col['label'] }}</span><span class="kanban-count">{{ count($col['cards']) }}</span>
    </div>
    <div class="kanban-lane" data-lane="{{ $status }}">
        @foreach ($col['cards'] as $card)
            @if(!empty($card['is_completion_record']))
                {{-- Manual/inferred class completion: the record IS the QA-approved entry; open the shared
                     qualification modal by the person's current qualification, and it is not draggable. --}}
                @php $ccQid = \App\Models\Qualification::where('personnel_id', $card['personnel_id'])->whereNull('superseded_at')->latest('id')->value('id'); @endphp
                <div class="kanban-card" data-no-drag style="border-left-color:{{ $col['color'] }};{{ $ccQid ? 'cursor:pointer;' : '' }}"
                     @if($ccQid) wire:click="$dispatch('open-qual-modal', { id: {{ (int) $ccQid }} })" @endif>
                    <div class="kanban-name">{{ $card['name'] }}</div>
                    <div class="kanban-meta">{{ $card['employee_id'] }}@if(!empty($card['department'])) · {{ $card['department'] }}@endif</div>
                    @if($card['class'])<div class="kanban-slot">{{ $card['class'] }}@if($card['date']) · {{ $card['date'] }}@endif</div>@endif
                    <span class="cb-pill" style="background:{{ $card['status_color'] }};">{{ $card['status_label'] }}</span>
                    <span class="cb-pill" style="background:#6A6A72;margin-left:4px;" title="Source: {{ ucfirst((string) $card['source']) }}">{{ ucfirst((string) $card['source']) }}</span>
                </div>
            @else
            <div class="kanban-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $col['color'] }};">
                <div class="kanban-name">{{ $card['name'] }}</div>
                <div class="kanban-meta">{{ $card['employee_id'] }}@if(!empty($card['department'])) · {{ $card['department'] }}@endif</div>
                @if($card['class'])<div class="kanban-slot">{{ $card['class'] }}@if($card['date']) · {{ $card['date'] }}@endif</div>@endif
                <span class="cb-pill" style="background:{{ $card['status_color'] }};">{{ $card['status_label'] }}</span>
                @if($status === 'no_show' && !empty($card['personnel_id']))
                    <button type="button" wire:click="openRebook({{ $card['id'] }})" onclick="event.stopPropagation()" class="cb-rebook-btn">Rebook</button>
                @endif
            </div>
            @endif
        @endforeach
    </div>
</div>
