<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-academic-cap" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Gowning Class Board</h1>
                <p>Track class enrollments. Completing a class advances the person to the run pipeline.</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <button type="button" wire:click="$set('showAdd', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;height:36px;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add Enrollment
            </button>
        </div>
    </div>

    @if($showAdd)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAdd', false)">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:440px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Add Class Enrollment</div>
                <div style="padding:18px 20px;">
                    <label class="gqs-flbl">Person</label>
                    <select wire:model="addPersonnelId" class="gqs-fld" style="margin-bottom:14px;">
                        <option value="">Select a person...</option>
                        @foreach($this->bookablePersonnel() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    <label class="gqs-flbl">Class Session</label>
                    <select wire:model="addSessionId" class="gqs-fld">
                        <option value="">Select a session...</option>
                        @foreach($this->openSessions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button type="button" wire:click="$set('showAdd', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="button" wire:click="addEnrollment" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Add Enrollment</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div x-data="{
            init() { this.$nextTick(() => this.wire()); },
            wire() {
                document.querySelectorAll('[data-lane]').forEach(lane => {
                    if (lane._sortable) return;
                    lane._sortable = Sortable.create(lane, {
                        group: 'classes', animation: 150, ghostClass: 'kanban-ghost',
                        onStart: () => { this._dragging = true; },
                        onEnd: (evt) => {
                            this._dragging = false;
                            if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                            $wire.moveCard(parseInt(evt.item.getAttribute('data-id')), evt.to.getAttribute('data-lane'));
                        }
                    });
                });
                document.querySelectorAll('.kanban-card').forEach(card => {
                    if (card._clickWired) return;
                    card._clickWired = true;
                    card.addEventListener('click', () => {
                        if (this._dragging) return;
                        $wire.showDetail(parseInt(card.getAttribute('data-id')));
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
                                @if($card['class'])<div class="kanban-slot">{{ $card['class'] }}@if($card['date']) · {{ $card['date'] }}@endif</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    @if($detail)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="closeDetail">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:420px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <div style="font-weight:800;font-size:17px;">{{ $detail['name'] }}</div>
                        <div style="font-size:12px;opacity:.8;">{{ $detail['employee_id'] }}</div>
                    </div>
                    <button wire:click="closeDetail" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;opacity:.7;">&times;</button>
                </div>
                <div style="padding:18px 20px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px;font-size:13px;">
                        <div><div class="dm-l">Class</div><div class="dm-v">{{ $detail['class'] ? $detail['class'] : '—' }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $detail['status'] }}</div></div>
                        <div style="grid-column:1/-1;"><div class="dm-l">Session Date</div><div class="dm-v">{{ $detail['session_date'] ?? '—' }}</div></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button wire:click="closeDetail" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Close</button>
                        @if($detail['edit_url'])<a href="{{ $detail['edit_url'] }}" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;font-weight:700;text-decoration:none;">Edit Person</a>@endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    <style>
        .dm-l{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);}
        .dm-v{font-weight:600;color:var(--gqs-text,#1A1A1F);margin-top:1px;}
    </style>
    <style>
        .sb-fullbleed{width:100%;}
        .kanban-wrap{display:flex;gap:14px;overflow-x:auto;padding:0 32px 12px;align-items:stretch;min-height:calc(100vh - 260px);}
        .kanban-col{flex:0 0 250px;display:flex;flex-direction:column;}
        .kanban-lane{flex:1;}
        .kanban-col{background:#fff;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;padding:10px;min-height:120px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
        .dark .kanban-col{background:#1A1A20;border-color:#2A2A32;}
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
