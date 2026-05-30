<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Status Board', 'subtitle' => 'Drag each person through the GMP pipeline, class to QA sign-off.', 'icon' => 'heroicon-o-squares-2x2'])

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;padding:0 32px;">
        <div style="flex:1;min-width:180px;max-width:300px;">
            <label class="gqs-flbl">Search</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name or employee ID" class="gqs-fld">
        </div>
        <div style="min-width:170px;">
            <label class="gqs-flbl">Department</label>
            <select wire:model.live="deptFilter" class="gqs-fld">
                <option value="">All Departments</option>
                @foreach($this->departmentOptions() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
        </div>
        <div style="min-width:150px;">
            <label class="gqs-flbl">Cycle Type</label>
            <select wire:model.live="typeFilter" class="gqs-fld">
                <option value="">All Types</option>
                <option value="initial">Initial</option>
                <option value="annual">Annual</option>
            </select>
        </div>
    </div>

    <div x-data="{
            selectMode: false,
            selected: [],
            toggleSelect(id) {
                const i = this.selected.indexOf(id);
                if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
            },
            isSelected(id) { return this.selected.includes(id); },
            clearSel() { this.selected = []; },
            init() { this.$nextTick(() => this.wire()); },
            wire() {
                document.querySelectorAll('[data-stage]').forEach(lane => {
                    if (lane._sortable) return;
                    lane._sortable = Sortable.create(lane, {
                        group: 'stages', animation: 150, ghostClass: 'sb-ghost',
                        disabled: false,
                        onStart: () => { this._dragging = true; },
                        onEnd: (evt) => {
                            this._dragging = false;
                            const id = evt.item.getAttribute('data-id');
                            const to = evt.to.getAttribute('data-stage');
                            if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return; // no move = treat as click elsewhere
                            $wire.moveCard(parseInt(id), to);
                        }
                    });
                });
                // click: in select mode toggles selection, otherwise opens the detail modal
                document.querySelectorAll('.sb-card').forEach(card => {
                    if (card._clickWired) return;
                    card._clickWired = true;
                    card.addEventListener('click', (e) => {
                        if (this._dragging) return;
                        const id = parseInt(card.getAttribute('data-id'));
                        if (this.selectMode) { this.toggleSelect(id); }
                        else { $wire.showDetail(id); }
                    });
                });
            }
        }" x-init="init()" wire:key="sb-{{ now()->timestamp }}">

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:0 32px;">
            <button type="button" @click="selectMode = !selectMode; if(!selectMode) clearSel()"
                    :style="selectMode ? 'background:#A4123F;color:#fff;' : 'background:transparent;color:var(--gqs-text,#1A1A1F);'"
                    style="display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border:1px solid #A4123F;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-check-circle" style="width:16px;height:16px;"/>
                <span x-text="selectMode ? 'Selecting' : 'Select Multiple'"></span>
            </button>
            <span x-show="selectMode" x-cloak style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
                <span x-text="selected.length"></span> selected. Tap cards to select.
            </span>
        </div>

        <div class="sb-fullbleed"><div class="sb-wrap">
            @foreach ($this->getStages() as $stage)
                <div class="sb-col">
                    <div class="sb-head" style="background:{{ $stage['color'] }};">
                        <span>{{ $stage['label'] }}</span>
                        <span class="sb-count">{{ count($stage['cards']) }}</span>
                    </div>
                    <div class="sb-lane" data-stage="{{ $stage['key'] }}">
                        @foreach ($stage['cards'] as $card)
                            <div class="sb-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $stage['color'] }};"
                                 :class="{ 'sb-selected': isSelected({{ $card['id'] }}) }">
                                <div class="sb-name">{{ $card['name'] }}</div>
                                <div class="sb-meta">{{ $card['employee_id'] }}</div>
                                @if(($card['runs_req'] ?? 0) > 0)
                                    <div class="sb-runs" title="{{ $card['runs_done'] }} of {{ $card['runs_req'] }} runs">
                                        @for($r = 0; $r < $card['runs_req']; $r++)
                                            <span class="sb-pip {{ $r < $card['runs_done'] ? 'on' : '' }}"></span>
                                        @endfor
                                        <span class="sb-runs-lbl">{{ $card['runs_done'] }}/{{ $card['runs_req'] }} runs</span>
                                    </div>
                                @endif
                                @if($card['due'])<div class="sb-due">Due {{ $card['due'] }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div>

        {{-- Floating bulk action bar --}}
        <div x-show="selectMode && selected.length > 0" x-cloak x-transition
             style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:40;display:flex;align-items:center;gap:12px;background:#1C1C21;color:#fff;padding:12px 18px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.35);">
            <span style="font-weight:700;font-size:13px;"><span x-text="selected.length"></span> selected</span>
            <button type="button"
                    @click="$wire.bulkBookRunDay(selected).then(() => { clearSel(); selectMode = false; })"
                    style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#A4123F;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-calendar-days" style="width:15px;height:15px;"/> Assign To Next Run Day
            </button>
            <button type="button" @click="clearSel()"
                    style="padding:8px 12px;background:transparent;color:#ECECF0;border:1px solid #44444E;border-radius:8px;font-weight:600;font-size:12.5px;cursor:pointer;">Clear</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    {{-- Click-to-view detail modal --}}
    @if($detail)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="closeDetail">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:460px;max-width:94vw;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <div style="font-weight:800;font-size:17px;">{{ $detail['name'] }}</div>
                        <div style="font-size:12px;opacity:.8;">{{ $detail['employee_id'] }}@if($detail['department']) · {{ $detail['department'] }}@endif</div>
                    </div>
                    <button wire:click="closeDetail" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;opacity:.7;">&times;</button>
                </div>
                <div style="padding:18px 20px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px;font-size:13px;">
                        <div><div class="dm-l">Stage</div><div class="dm-v">{{ $detail['stage'] }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $detail['status'] }}</div></div>
                        <div><div class="dm-l">Type</div><div class="dm-v">{{ $detail['type'] }}</div></div>
                        <div><div class="dm-l">Runs</div><div class="dm-v">{{ $detail['runs'] }}</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $detail['due'] ?? '—' }}</div></div>
                        <div><div class="dm-l">Class On File</div><div class="dm-v">{{ $detail['class_on_file'] ? 'Yes' : 'No' }}</div></div>
                        <div><div class="dm-l">QA Owner</div><div class="dm-v">{{ $detail['qa_owner'] ?? 'Unassigned' }}</div></div>
                    </div>

                    @if(count($detail['recent_runs']))
                        <div class="dm-l" style="margin-top:18px;">Recent Runs</div>
                        <table class="gqs-tbl" style="margin-top:6px;">
                            <thead><tr><th>Date</th><th>Result</th><th>Worklist</th></tr></thead>
                            <tbody>@foreach($detail['recent_runs'] as $r)
                                <tr><td>{{ $r['date'] }}</td><td>{{ $r['result'] }}</td><td>{{ $r['worklist'] ?? '—' }}</td></tr>
                            @endforeach</tbody>
                        </table>
                    @endif

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button wire:click="closeDetail" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Close</button>
                        <a href="{{ $detail['edit_url'] }}" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;font-weight:700;text-decoration:none;">Edit</a>
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
        .sb-wrap{display:flex;gap:12px;overflow-x:auto;padding:0 32px 14px;align-items:stretch;min-height:calc(100vh - 260px);}
        .sb-col{flex:0 0 230px;background:#fff;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;padding:10px;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(0,0,0,.05);}
        .dark .sb-col{background:#1A1A20;border-color:#2A2A32;}
        .sb-lane{flex:1;}
        .sb-head{display:flex;align-items:center;justify-content:space-between;color:#fff;font-weight:700;font-size:12.5px;padding:8px 11px;border-radius:8px;margin-bottom:9px;}
        .sb-count{background:rgba(255,255,255,.28);border-radius:20px;padding:1px 8px;font-size:11px;}
        .sb-lane{display:flex;flex-direction:column;gap:7px;min-height:60px;}
        .sb-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#E6E6EA);border-left:4px solid #A4123F;border-radius:9px;padding:9px 11px;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.06);}
        .dark .sb-card{background:#23232B;border-color:#33333D;}
        .sb-card:active{cursor:grabbing;}
        .sb-selected{outline:3px solid #A4123F;outline-offset:1px;box-shadow:0 0 0 4px rgba(164,18,63,.15) !important;}
        .sb-name{font-weight:700;font-size:13px;color:var(--gqs-text,#1A1A1F);}
        .sb-meta{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;}
        .sb-runs{display:flex;align-items:center;gap:4px;margin-top:5px;}
        .sb-pip{width:9px;height:9px;border-radius:50%;background:transparent;border:1.5px solid #C79A2E;display:inline-block;}
        .sb-pip.on{background:#2E7D5B;border-color:#2E7D5B;}
        .sb-runs-lbl{font-size:10.5px;color:var(--gqs-text-dim,#9A9AA4);margin-left:3px;font-weight:600;}
        .sb-due{font-size:11px;color:#A4123F;font-weight:600;margin-top:3px;}
        .sb-ghost{opacity:.4;}
        .dark .sb-card{background:#1F1F25;}
        .dark .sb-name{color:#fff;}
    </style>
</x-filament-panels::page>
