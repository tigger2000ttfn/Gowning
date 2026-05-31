<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-squares-2x2" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Status Board</h1>
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
            <select wire:model.live="groupBy" class="gqs-fld sb-hf-sel" title="Group cards into swimlanes">
                @foreach($this->groupByOptions() as $k => $label)<option value="{{ $k }}">{{ $k === '' ? 'No Grouping' : 'Group: ' . $label }}</option>@endforeach
            </select>
        </div>
    </div>

    <div wire:ignore.self
         x-data="sbBoard({ canReorder: @json((bool) auth()->user()?->hasCapability(\App\Enums\Capability::ManageScheduling)), canMove: @json((bool) (auth()->user()?->hasCapability(\App\Enums\Capability::ManageScheduling) || auth()->user()?->hasCapability(\App\Enums\Capability::QaApprove))) })"
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

        @if($this->groupBy === '')
        {{-- Ungrouped: single row, column drag-reorder enabled --}}
        <div class="sb-fullbleed"><div class="sb-wrap" x-ref="board">
            @foreach ($this->getStages() as $stage)
                @include('filament.partials.sb-column', ['stage' => $stage])
            @endforeach

            {{-- Archive: far-right collapsed lane (fully-done records; automation moves these to run history) --}}
            @php $archive = $this->getArchive(); @endphp
            <div class="sb-col sb-archive-col" x-data="{ open: false }" :class="open ? 'sb-archive-open' : ''">
                <div class="sb-head sb-archive-head" style="background:{{ $archive['color'] }};" @click="open = !open">
                    <span x-show="open" class="sb-head-label" x-cloak>{{ $archive['label'] }}</span>
                    <span x-show="!open" class="sb-archive-vlabel">{{ $archive['label'] }}</span>
                    <span class="sb-count">{{ count($archive['cards']) }}</span>
                </div>
                <div class="sb-lane" data-stage="archived" x-show="open" x-cloak>
                    @forelse($archive['cards'] as $card)
                        <div class="sb-card sb-card-archived" data-id="{{ $card['id'] }}" style="border-left-color:{{ $archive['color'] }};">
                            <div class="sb-card-body" wire:click="$dispatch('open-qual-modal', { id: {{ $card['id'] }} })" style="cursor:pointer;">
                                <div class="sb-name">{{ $card['name'] }}</div>
                                <div class="sb-meta">{{ $card['employee_id'] }}</div>
                                @if($card['due'])<div class="sb-due">Due {{ $card['due'] }}</div>@endif
                            </div>
                        </div>
                    @empty
                        <div class="gqs-empty" style="padding:14px;font-size:12px;">Empty.</div>
                    @endforelse
                </div>
            </div>
        </div></div>
        @else
        {{-- Grouped: horizontal swimlanes stacked in one scroll pane (horizontal scrollbar pinned at the bottom) --}}
        <div class="sb-fullbleed"><div class="sb-gpane"><div class="sb-gboard">
            @foreach($this->getSwimlanes() as $swim)
                <div class="sb-swim">
                    <div class="sb-glabel"><span>{{ $swim['label'] }}</span><span class="sb-gcount">{{ $swim['count'] ?? 0 }}</span></div>
                    <div class="sb-grow">
                        @foreach($swim['stages'] as $stage)
                            @include('filament.partials.sb-column', ['stage' => $stage])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div></div>
        @endif
    </div>

    <script src="{{ asset('vendor/sortable/Sortable.min.js') }}?v={{ @filemtime(public_path('vendor/sortable/Sortable.min.js')) }}"></script>
    <script>
        function sbBoard({ canReorder, canMove }) {
            return {
                selected: [],
                canReorder, canMove,
                _dragging: false,
                toggleSelect(id) {
                    const i = this.selected.indexOf(id);
                    if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
                },
                isSelected(id) { return this.selected.includes(id); },
                clearSel() { this.selected = []; },
                openCard(id) { if (this._dragging) return; this.$wire.dispatch('open-qual-modal', { id }); },
                fitHeight() {
                    const el = this.$root.querySelector('.sb-gpane') || this.$root.querySelector('.sb-wrap');
                    if (el) el.style.height = Math.max(320, window.innerHeight - el.getBoundingClientRect().top - 10) + 'px';
                },
                init() {
                    this.$nextTick(() => { this.wireSortables(); this.fitHeight(); });
                    setTimeout(() => this.fitHeight(), 200);
                    // re-wire + re-fit after Livewire DOM updates (search/filter/group/move re-render the lanes)
                    Livewire.hook('morphed', () => this.$nextTick(() => { this.wireSortables(); this.fitHeight(); }));
                    Livewire.hook('commit', ({ respond }) => respond(() => this.$nextTick(() => this.fitHeight())));
                    window.addEventListener('resize', () => this.fitHeight());
                    window.addEventListener('load', () => this.fitHeight());
                    document.addEventListener('livewire:navigated', () => this.$nextTick(() => this.fitHeight()));
                },
                wireSortables() {
                    // card drag between lanes (only for users who can move cards)
                    if (this.canMove) {
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
                    }
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


    {{-- Book a run for a Class-Complete person --}}
    @if($bookRunQid)
        <div class="gqs-modal-overlay" wire:click.self="closeBookRun">
            <div class="gqs-modal" style="width:500px;max-width:94vw;">
                <div style="background:linear-gradient(135deg,#2E7D5B,#225F46);padding:16px 20px;display:flex;align-items:center;gap:12px;border-radius:14px 14px 0 0;">
                    <span style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <x-filament::icon icon="heroicon-o-calendar-days" style="width:26px;height:26px;color:#fff;"/>
                    </span>
                    <div style="font-weight:800;font-size:17px;color:#fff;">Book A Run</div>
                </div>
                <div class="gqs-modal-body">
                    <p style="margin:0 0 12px;font-size:13px;color:var(--gqs-text,#1A1A1F);line-height:1.5;">Schedule this person for a qualification run. They move to Run Scheduled once booked.</p>
                    <label class="gqs-flbl">Book To</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:6px;">
                        <label style="display:flex;align-items:center;gap:9px;font-size:13.5px;cursor:pointer;">
                            <input type="radio" wire:model.live="bookRunMode" value="next"> Next available run day
                        </label>
                        <label style="display:flex;align-items:center;gap:9px;font-size:13.5px;cursor:pointer;">
                            <input type="radio" wire:model.live="bookRunMode" value="specific"> A specific run day
                        </label>
                    </div>
                    @if($bookRunMode === 'specific')
                        <label class="gqs-flbl">Run Day</label>
                        <select wire:model="bookRunSlotId" class="gqs-fld">
                            <option value="">Select a run day...</option>
                            @foreach($this->bookRunSlotOptions() as $sid => $lbl)<option value="{{ $sid }}">{{ $lbl }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeBookRun" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="confirmBookRun" class="gqs-btn gqs-btn-primary">Book Run</button>
                </div>
            </div>
        </div>
    @endif
    <style>
        .dm-l{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);}
        .dm-v{font-weight:600;color:var(--gqs-text,#1A1A1F);margin-top:1px;}
        .sb-book-run{margin-top:9px;display:block;width:100%;font-size:11.5px;font-weight:700;padding:7px 11px;border-radius:7px;border:none;background:#2E7D5B;color:#fff;cursor:pointer;}
        .sb-book-run:hover{background:#246148;}
        .sb-review-btn{margin-top:9px;display:flex;align-items:center;justify-content:center;gap:4px;width:100%;font-size:11.5px;font-weight:700;padding:7px 11px;border-radius:7px;border:none;background:#1F6FB2;color:#fff;cursor:pointer;text-decoration:none;}
        .sb-review-btn:hover{background:#185A92;}
    </style>
    <style>
        .sb-fullbleed{width:100%;}
        /* Grouped (swimlane) layout: one scroll pane, horizontal scrollbar pinned at its bottom (viewport bottom) */
        .sb-gpane{overflow:auto;height:calc(100vh - 178px);min-height:380px;padding:0 32px 0;}
        .sb-gboard{display:flex;flex-direction:column;gap:20px;width:max-content;min-width:100%;padding-bottom:16px;}
        .sb-swim{display:flex;flex-direction:column;gap:8px;}
        .sb-glabel{position:sticky;left:0;display:inline-flex;align-items:center;gap:9px;font-weight:800;font-size:13.5px;
            color:var(--gqs-text,#1A1A1F);padding:5px 12px;background:linear-gradient(90deg,rgba(164,18,63,.10),rgba(164,18,63,0));
            border-left:4px solid #A4123F;border-radius:5px;width:max-content;}
        .dark .sb-glabel{color:#ECECF0;background:linear-gradient(90deg,rgba(164,18,63,.28),rgba(164,18,63,0));}
        .sb-gcount{min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:#A4123F;color:#fff;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;}
        .sb-grow{display:flex;gap:12px;align-items:flex-start;}
        .sb-grow .sb-col{max-height:none;}
        .sb-grow .sb-lane{overflow:visible;}
        .sb-wrap{display:flex;gap:12px;overflow-x:auto;overflow-y:hidden;padding:0 32px 14px;align-items:stretch;height:calc(100vh - 178px);min-height:360px;}
        .sb-col{flex:0 0 230px;background:#fff;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;padding:10px;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(0,0,0,.05);max-height:100%;}
        .dark .sb-col{background:#121216;border-color:#34343E;}
        .sb-lane{flex:1;}
        .sb-head{display:flex;align-items:center;justify-content:space-between;color:#fff;font-weight:700;font-size:12.5px;padding:8px 11px;border-radius:8px;margin-bottom:9px;}
        .sb-count{background:rgba(255,255,255,.28);border-radius:20px;padding:1px 8px;font-size:11px;}
        .sb-lane{display:flex;flex-direction:column;gap:7px;min-height:60px;overflow-y:auto;flex:1;}
        .sb-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#E6E6EA);border-left:4px solid #A4123F;border-radius:9px;padding:9px 11px;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.06);}
        .sb-card:active{cursor:grabbing;}
        .sb-selected{outline:3px solid #A4123F;outline-offset:1px;box-shadow:0 0 0 4px rgba(164,18,63,.15) !important;}
        .sb-name{font-weight:700;font-size:13px;color:var(--gqs-text,#1A1A1F);}
        .sb-name-row{display:flex;align-items:baseline;justify-content:space-between;gap:6px;}
        .sb-emp{font-size:11px;font-weight:600;color:var(--gqs-text-dim,#7A7A82);margin-top:2px;letter-spacing:.02em;}
        .sb-foot{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;font-size:10.5px;color:var(--gqs-text-dim,#7A7A82);}
        .sb-foot-i{white-space:nowrap;}
        .sb-wl{font-weight:700;color:var(--gqs-text-dim,#5A5A62);background:var(--gqs-surface-2,#F1F1F4);border-radius:5px;padding:1px 6px;max-width:120px;overflow:hidden;text-overflow:ellipsis;}
        .dark .sb-wl{background:#2C2C34;color:#C9C9D2;}
        .sb-meta{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;}
        .sb-runs{display:flex;align-items:center;gap:4px;margin-top:5px;}
        .sb-pip{width:9px;height:9px;border-radius:50%;background:transparent;border:1.5px solid #C79A2E;display:inline-block;}
        .sb-pip.on{background:#2E7D5B;border-color:#2E7D5B;}
        .sb-runs-lbl{font-size:10.5px;color:var(--gqs-text-dim,#9A9AA4);margin-left:3px;font-weight:600;}
        .sb-due{font-size:11px;color:#A4123F;font-weight:600;margin-top:3px;}
        .sb-ghost{opacity:.4;}
        .dark .sb-card{background:#2C2C36;border-color:#44444F;box-shadow:0 1px 3px rgba(0,0,0,.4);}
        .dark .sb-name{color:#fff;}

        /* card layout: vertical so the action button sits at the bottom; checkbox overlays top-right */
        .sb-card{display:flex;flex-direction:column;gap:0;cursor:default;position:relative;}
        .sb-check{position:absolute;top:8px;right:8px;z-index:2;opacity:0;transition:opacity .12s;cursor:pointer;}
        .sb-card:hover .sb-check{opacity:1;}
        .sb-selected .sb-check{opacity:1;}
        .sb-check input{width:15px;height:15px;accent-color:#A4123F;cursor:pointer;}
        .sb-card-body{min-width:0;cursor:pointer;padding-right:18px;}
        .sb-pill{display:inline-block;margin-top:5px;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;background:#E6E6EA;color:#3A3A42;}
        .sb-pill-qualified{background:#2E7D5B;color:#fff;}
        .sb-pill-in_progress{background:#C79A2E;color:#fff;}
        .sb-pill-pending{background:#6B6B73;color:#fff;}
        .sb-pill-lapsed{background:#C8102E;color:#fff;}
        .sb-tag{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;background:#EFEFF2;color:#444;}
        .dark .sb-tag{background:#2C2C34;color:#C9C9D2;}
        .sb-tag-red{background:#FCEEF0;color:#C8102E;}
        .dark .sb-tag-red{background:#3A1B22;color:#FF8A9B;}
        .sb-line{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:5px;}
        .sb-line-l{font-weight:700;text-transform:uppercase;letter-spacing:.03em;font-size:9.5px;opacity:.8;margin-right:4px;}
        /* lane header is the grab handle for reordering columns */
        .sb-head-grab{cursor:grab;}
        .sb-head-grab:active{cursor:grabbing;}
        .sb-col-ghost{opacity:.5;}

        /* Archive: far-right collapsed lane */
        .sb-archive-col{flex:0 0 48px;transition:flex-basis .18s;}
        .sb-archive-col.sb-archive-open{flex:0 0 230px;}
        .sb-archive-head{cursor:pointer;}
        .sb-archive-vlabel{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;font-size:11.5px;letter-spacing:.04em;}
        .sb-card-archived{cursor:pointer;}
    </style>
</x-filament-panels::page>
