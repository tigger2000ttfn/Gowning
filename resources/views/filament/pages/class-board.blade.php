<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Gowning Class Board', 'subtitle' => 'Track class enrollments. Completing a class advances the person to the run pipeline.', 'icon' => 'heroicon-o-academic-cap'])

    <div x-data="{
            init() { this.$nextTick(() => this.wire()); },
            wire() {
                document.querySelectorAll('[data-lane]').forEach(lane => {
                    if (lane._sortable) return;
                    lane._sortable = Sortable.create(lane, {
                        group: 'classes', animation: 150, ghostClass: 'kanban-ghost',
                        onEnd: (evt) => { $wire.moveCard(parseInt(evt.item.getAttribute('data-id')), evt.to.getAttribute('data-lane')); }
                    });
                });
            }
        }" x-init="init()" wire:key="cb-{{ now()->timestamp }}">
        <div class="sb-fullbleed"><div class="kanban-wrap">
            @foreach ($this->getColumns() as $status => $col)
                <div class="kanban-col">
                    <div class="kanban-head" style="background:{{ $col['color'] }};">
                        <span>{{ $col['label'] }}</span><span class="kanban-count">{{ count($col['cards']) }}</span>
                    </div>
                    <div class="kanban-lane" data-lane="{{ $status }}">
                        @foreach ($col['cards'] as $card)
                            <div class="kanban-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $col['color'] }};">
                                <div class="kanban-name">{{ $card['name'] }}</div>
                                <div class="kanban-meta">{{ $card['employee_id'] }}</div>
                                @if($card['class'])<div class="kanban-slot">{{ \Illuminate\Support\Str::title($card['class']) }}@if($card['date']) · {{ $card['date'] }}@endif</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
        .sb-fullbleed{width:100%;}
        .kanban-wrap{display:flex;gap:14px;overflow-x:auto;padding:0 32px 12px;align-items:flex-start;}
        .kanban-col{flex:0 0 250px;}
        .kanban-col{background:rgba(120,120,130,.06);border-radius:12px;padding:10px;min-height:120px;}
        .kanban-head{display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:14px;padding:8px 11px;border-radius:8px;color:#fff;margin-bottom:10px;}
        .kanban-count{background:rgba(255,255,255,.25);border-radius:20px;padding:1px 9px;font-size:12px;}
        .kanban-lane{display:flex;flex-direction:column;gap:8px;min-height:60px;}
        .kanban-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DCDCE2);border-left:4px solid #A4123F;border-radius:9px;padding:10px 12px;cursor:grab;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        .kanban-name{font-weight:700;font-size:14px;color:var(--gqs-text,#1A1A1F);}
        .kanban-meta{font-size:12px;color:var(--gqs-text-dim,#6A6A72);}
        .kanban-slot{font-size:12px;color:#A4123F;font-weight:600;margin-top:4px;}
        .kanban-ghost{opacity:.4;}
        .dark .kanban-card{background:#1F1F25;} .dark .kanban-name{color:#fff;}
    </style>
</x-filament-panels::page>
