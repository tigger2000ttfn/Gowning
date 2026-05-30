<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-squares-2x2" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Qualification Status Board</h1>
                <p>Drag each person through the GMP pipeline, class to QA sign-off.</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID" class="gqs-fld sb-hf-search">
            <select wire:model.live="deptFilter" class="gqs-fld sb-hf-sel">
                <option value="">All Departments</option>
                @foreach($this->departmentOptions() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
            <select wire:model.live="typeFilter" class="gqs-fld sb-hf-sel">
                <option value="">All Types</option>
                <option value="initial">Initial</option>
                <option value="annual">Annual</option>
            </select>
        </div>
    </div>

    <div wire:ignore.self
         x-data="sbBoard({ canReorder: @json((bool) auth()->user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) })"
         x-init="init()">

        {{-- Toolbar: selection happens via per-card checkboxes; bar only shows when something is picked --}}
        <div x-show="selected.length > 0" x-cloak x-transition
             style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 12px;padding:10px 16px;background:#1C1C21;color:#fff;border-radius:10px;">
            <span style="font-weight:700;font-size:13px;"><span x-text="selected.length"></span> selected</span>
            <button type="button" @click="$wire.bulkBookRunDay(selected).then(() => clearSel())"
                    style="display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#A4123F;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-calendar-days" style="width:15px;height:15px;"/> Assign To Next Run Day
            </button>
            <button type="button" @click="clearSel()"
                    style="padding:7px 11px;background:transparent;color:#ECECF0;border:1px solid #44444E;border-radius:8px;font-weight:600;font-size:12.5px;cursor:pointer;">Clear</button>
            <span style="font-size:11.5px;opacity:.7;">Hover a card and tick its box to select. Drag cards to move stage.</span>
        </div>

        <div class="sb-fullbleed"><div class="sb-wrap" x-ref="board">
            @foreach ($this->getStages() as $stage)
                <div class="sb-col" data-lane="{{ $stage['key'] }}">
                    <div class="sb-head" style="background:{{ $stage['color'] }};" :class="canReorder ? 'sb-head-grab' : ''">
                        <span class="sb-head-label">{{ $stage['label'] }}</span>
                        <span class="sb-count">{{ count($stage['cards']) }}</span>
                    </div>
                    <div class="sb-lane" data-stage="{{ $stage['key'] }}">
                        @foreach ($stage['cards'] as $card)
                            <div class="sb-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $stage['color'] }};"
                                 :class="{ 'sb-selected': isSelected({{ $card['id'] }}) }">
                                <label class="sb-check" @click.stop>
                                    <input type="checkbox" :checked="isSelected({{ $card['id'] }})" @change="toggleSelect({{ $card['id'] }})">
                                </label>
                                <div class="sb-card-body" @click="openCard({{ $card['id'] }})">
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
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div>

        {{-- Archive swimlane (QA signed-off / complete), collapsed by default --}}
        @php $archive = $this->getArchive(); @endphp
        <div class="sb-archive" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="sb-archive-head">
                <x-filament::icon icon="heroicon-m-archive-box" style="width:16px;height:16px;"/>
                <span>{{ $archive['label'] }}</span>
                <span class="sb-count" style="background:{{ $archive['color'] }};">{{ count($archive['cards']) }}</span>
                <x-filament::icon icon="heroicon-m-chevron-down" style="width:15px;height:15px;margin-left:auto;" x-bind:style="open && 'transform:rotate(180deg)'"/>
            </button>
            <div x-show="open" x-transition x-cloak class="sb-archive-body">
                @forelse($archive['cards'] as $card)
                    <div class="sb-card sb-card-archived" data-id="{{ $card['id'] }}" style="border-left-color:{{ $archive['color'] }};">
                        <div class="sb-card-body" wire:click="showDetail({{ $card['id'] }})" style="cursor:pointer;">
                            <div class="sb-name">{{ $card['name'] }}</div>
                            <div class="sb-meta">{{ $card['employee_id'] }}</div>
                            @if($card['due'])<div class="sb-due">Due {{ $card['due'] }}</div>@endif
                        </div>
                    </div>
                @empty
                    <div class="gqs-empty" style="padding:16px;">No qualified records yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        function sbBoard({ canReorder }) {
            return {
                selected: [],
                canReorder,
                _dragging: false,
                toggleSelect(id) {
                    const i = this.selected.indexOf(id);
                    if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
                },
                isSelected(id) { return this.selected.includes(id); },
                clearSel() { this.selected = []; },
                openCard(id) { if (this._dragging) return; this.$wire.showDetail(id); },
                init() {
                    this.$nextTick(() => this.wireSortables());
                    // re-wire after Livewire DOM updates (search/filter/move re-render the lanes)
                    Livewire.hook('morph.updated', () => this.$nextTick(() => this.wireSortables()));
                },
                wireSortables() {
                    // card drag between lanes
                    this.$root.querySelectorAll('.sb-lane').forEach(lane => {
                        if (lane._sortable) return;
                        lane._sortable = Sortable.create(lane, {
                            group: 'stages', animation: 150, ghostClass: 'sb-ghost',
                            handle: '.sb-card-body',
                            onStart: () => { this._dragging = true; },
                            onEnd: (evt) => {
                                setTimeout(() => { this._dragging = false; }, 50);
                                const id = evt.item.getAttribute('data-id');
                                const to = evt.to.getAttribute('data-stage');
                                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                                this.$wire.moveCard(parseInt(id), to);
                            }
                        });
                    });
                    // lane (column) reordering by dragging headers
                    const board = this.$refs.board;
                    if (this.canReorder && board && !board._laneSortable) {
                        board._laneSortable = Sortable.create(board, {
                            group: 'lanes', animation: 150, draggable: '.sb-col',
                            handle: '.sb-head-grab', ghostClass: 'sb-col-ghost',
                            onEnd: () => {
                                const order = [...board.querySelectorAll('.sb-col')].map(c => c.getAttribute('data-lane'));
                                this.$wire.setLaneOrder(order);
                            }
                        });
                    }
                }
            };
        }
    </script>

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

        /* card layout with checkbox + clickable body */
        .sb-card{display:flex;align-items:flex-start;gap:8px;cursor:default;}
        .sb-check{flex:0 0 auto;opacity:0;transition:opacity .12s;padding-top:1px;cursor:pointer;}
        .sb-card:hover .sb-check{opacity:1;}
        .sb-selected .sb-check{opacity:1;}
        .sb-check input{width:15px;height:15px;accent-color:#A4123F;cursor:pointer;}
        .sb-card-body{flex:1;min-width:0;cursor:pointer;}
        /* lane header is the grab handle for reordering columns */
        .sb-head-grab{cursor:grab;}
        .sb-head-grab:active{cursor:grabbing;}
        .sb-col-ghost{opacity:.5;}

        /* Archive swimlane */
        .sb-archive{margin:14px 32px 0;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;background:#fff;}
        .dark .sb-archive{background:#1A1A20;border-color:#2A2A32;}
        .sb-archive-head{display:flex;align-items:center;gap:9px;width:100%;padding:12px 16px;background:transparent;border:none;cursor:pointer;font-weight:700;font-size:13.5px;color:var(--gqs-text,#1A1A1F);text-align:left;}
        .dark .sb-archive-head{color:#fff;}
        .sb-archive-body{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px;padding:0 16px 16px;}
        .sb-card-archived{cursor:pointer;}
    </style>
</x-filament-panels::page>
