<x-filament-panels::page>
    <div
        x-data="{
            init() {
                this.$nextTick(() => this.wire());
            },
            wire() {
                document.querySelectorAll('[data-lane]').forEach(lane => {
                    if (lane._sortable) return;
                    lane._sortable = Sortable.create(lane, {
                        group: 'reservations',
                        animation: 150,
                        ghostClass: 'kanban-ghost',
                        onEnd: (evt) => {
                            const id = evt.item.getAttribute('data-id');
                            const toStatus = evt.to.getAttribute('data-lane');
                            $wire.moveCard(parseInt(id), toStatus);
                        }
                    });
                });
            }
        }"
        x-init="init()"
        wire:key="board-{{ now()->timestamp }}"
    >
        <div class="kanban-wrap">
            @foreach ($this->getColumns() as $status => $col)
                <div class="kanban-col">
                    <div class="kanban-head kanban-{{ $status }}">
                        <span>{{ $col['label'] }}</span>
                        <span class="kanban-count">{{ count($col['cards']) }}</span>
                    </div>
                    <div class="kanban-lane" data-lane="{{ $status }}">
                        @foreach ($col['cards'] as $card)
                            <div class="kanban-card" data-id="{{ $card['id'] }}">
                                <div class="kanban-name">{{ $card['name'] }}</div>
                                <div class="kanban-meta">{{ $card['employee_id'] }}</div>
                                @if($card['slot'])
                                    <div class="kanban-slot">{{ $card['slot'] }} · {{ $card['date'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
        .kanban-wrap{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;align-items:start;}
        @media(max-width:900px){.kanban-wrap{grid-template-columns:repeat(2,1fr);}}
        .kanban-col{background:rgba(120,120,130,.06);border-radius:12px;padding:10px;min-height:120px;}
        .kanban-head{display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:14px;padding:8px 10px;border-radius:8px;color:#fff;margin-bottom:10px;}
        .kanban-requested{background:#B8860B;}
        .kanban-approved{background:#2E7D5B;}
        .kanban-completed{background:#1F6FB2;}
        .kanban-no_show{background:#C8102E;}
        .kanban-count{background:rgba(255,255,255,.25);border-radius:20px;padding:1px 9px;font-size:12px;}
        .kanban-lane{display:flex;flex-direction:column;gap:8px;min-height:60px;}
        .kanban-card{background:#fff;border:1px solid #DCDCE2;border-left:4px solid #A4123F;border-radius:9px;padding:10px 12px;cursor:grab;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        .kanban-card:active{cursor:grabbing;}
        .kanban-name{font-weight:700;font-size:14px;color:#1A1A1F;}
        .kanban-meta{font-size:12px;color:#6A6A72;}
        .kanban-slot{font-size:12px;color:#A4123F;font-weight:600;margin-top:4px;}
        .kanban-ghost{opacity:.4;}
        .dark .kanban-card{background:#1F1F25;color:#E5E5EA;}
        .dark .kanban-name{color:#fff;}
    </style>
</x-filament-panels::page>
