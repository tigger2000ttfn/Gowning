<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Status Board', 'subtitle' => 'Drag each person through the GMP pipeline, class to QA sign-off.', 'icon' => 'heroicon-o-squares-2x2'])

    <div x-data="{
            init() { this.$nextTick(() => this.wire()); },
            wire() {
                document.querySelectorAll('[data-stage]').forEach(lane => {
                    if (lane._sortable) return;
                    lane._sortable = Sortable.create(lane, {
                        group: 'stages', animation: 150, ghostClass: 'sb-ghost',
                        onEnd: (evt) => {
                            const id = evt.item.getAttribute('data-id');
                            const to = evt.to.getAttribute('data-stage');
                            $wire.moveCard(parseInt(id), to);
                        }
                    });
                });
            }
        }" x-init="init()" wire:key="sb-{{ now()->timestamp }}">

        <div class="sb-fullbleed"><div class="sb-wrap">
            @foreach ($this->getStages() as $stage)
                <div class="sb-col">
                    <div class="sb-head" style="background:{{ $stage['color'] }};">
                        <span>{{ $stage['label'] }}</span>
                        <span class="sb-count">{{ count($stage['cards']) }}</span>
                    </div>
                    <div class="sb-lane" data-stage="{{ $stage['key'] }}">
                        @foreach ($stage['cards'] as $card)
                            <div class="sb-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $stage['color'] }};">
                                <div class="sb-name">{{ $card['name'] }}</div>
                                <div class="sb-meta">{{ $card['employee_id'] }} · {{ $card['meta'] }}</div>
                                @if($card['due'])<div class="sb-due">Due {{ $card['due'] }}</div>@endif
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
        .sb-wrap{display:flex;gap:12px;overflow-x:auto;padding:0 32px 14px;align-items:stretch;min-height:calc(100vh - 260px);}
        .sb-col{flex:0 0 220px;background:rgba(120,120,130,.06);border-radius:12px;padding:9px;display:flex;flex-direction:column;}
        .sb-lane{flex:1;}
        .sb-head{display:flex;align-items:center;justify-content:space-between;color:#fff;font-weight:700;font-size:12.5px;padding:8px 11px;border-radius:8px;margin-bottom:9px;}
        .sb-count{background:rgba(255,255,255,.28);border-radius:20px;padding:1px 8px;font-size:11px;}
        .sb-lane{display:flex;flex-direction:column;gap:7px;min-height:60px;}
        .sb-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DCDCE2);border-left:4px solid #A4123F;border-radius:9px;padding:9px 11px;cursor:grab;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        .sb-card:active{cursor:grabbing;}
        .sb-name{font-weight:700;font-size:13px;color:var(--gqs-text,#1A1A1F);}
        .sb-meta{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;}
        .sb-due{font-size:11px;color:#A4123F;font-weight:600;margin-top:3px;}
        .sb-ghost{opacity:.4;}
        .dark .sb-card{background:#1F1F25;}
        .dark .sb-name{color:#fff;}
    </style>
</x-filament-panels::page>
