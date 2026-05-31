<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-academic-cap" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Class Board</h1>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <button type="button" wire:click="$refresh"
                    style="display:inline-flex;align-items:center;gap:6px;padding:9px 13px;background:transparent;color:var(--gqs-text,#1A1A1F);border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;height:36px;">
                <x-filament::icon icon="heroicon-m-arrow-path" style="width:15px;height:15px;"/> Refresh
            </button>
            <button type="button" wire:click="$set('showAdd', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;height:40px;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add Enrollment
            </button>
            <select wire:model.live="groupBy" class="gqs-fld sb-hf-sel" title="Group cards into swimlanes">
                @foreach($this->groupByOptions() as $k => $label)<option value="{{ $k }}">{{ $k === '' ? 'No Grouping' : 'Group: ' . $label }}</option>@endforeach
            </select>
        </div>
    </div>

    @if($showAdd)
        <div class="gqs-modal-overlay" wire:click.self="$set('showAdd', false)">
            <div class="gqs-modal">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-academic-cap"/></span>Schedule Class</div>
                <div class="gqs-modal-body">
                    <div>
                        <label class="gqs-flbl">Person</label>
                        <select wire:model="addPersonnelId" class="gqs-fld">
                            <option value="">Select a person...</option>
                            @foreach($this->bookablePersonnel() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="gqs-flbl">Class Date / Session</label>
                        <select wire:model="addSessionId" class="gqs-fld">
                            <option value="">Select a session...</option>
                            @foreach($this->openSessions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="gqs-modal-foot">
                    <button type="button" wire:click="$set('showAdd', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="addEnrollment" class="gqs-btn gqs-btn-primary">Schedule</button>
                </div>
            </div>
        </div>
    @endif

    <div x-data="{
            init() {
                this.$nextTick(() => { this.wire(); this.fit(); });
                Livewire.hook('morph.updated', () => this.$nextTick(() => { this.wire(); this.fit(); }));
                window.addEventListener('resize', () => this.fit());
            },
            fit() {
                const el = this.$el.querySelector('.sb-gpane') || this.$el.querySelector('.kanban-wrap');
                if (el) el.style.height = Math.max(320, window.innerHeight - el.getBoundingClientRect().top - 10) + 'px';
            },
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
        }" x-init="init()">
        @if($this->groupBy === '')
        <div class="sb-fullbleed"><div class="kanban-wrap">
            {{-- Needs Class: people Class Pending, not yet signed up (informational, with quick enroll) --}}
            @php $needs = $this->getNeedsClass(); @endphp
            <div class="kanban-col cb-needs-col">
                <div class="kanban-head" style="background:#8A0E22;">
                    <span>Needs A Class</span><span class="kanban-count">{{ count($needs) }}</span>
                </div>
                <div class="kanban-lane">
                    @forelse($needs as $card)
                        <div class="kanban-card cb-needs-card" style="border-left-color:#8A0E22;">
                            <div class="kanban-name">{{ $card['name'] }}</div>
                            <div class="kanban-meta">{{ $card['employee_id'] }}@if($card['department']) · {{ $card['department'] }}@endif</div>
                            <button wire:click="$set('addPersonnelId', {{ $card['personnel_id'] }}); $set('showAdd', true)"
                                    class="cb-enroll-btn">Schedule Class</button>
                        </div>
                    @empty
                        <div class="gqs-empty" style="padding:14px;font-size:12px;">Everyone needing the class is signed up.</div>
                    @endforelse
                </div>
            </div>

            @foreach ($this->getColumns() as $status => $col)
                @include('filament.partials.cb-column', ['status' => $status, 'col' => $col])
            @endforeach

            {{-- Archive: far-right collapsed lane (completed enrollments) --}}
            @php $archive = $this->getArchive(); @endphp
            <div class="kanban-col cb-archive-col" x-data="{ open: false }" :class="open ? 'cb-archive-open' : ''">
                <div class="kanban-head cb-archive-head" style="background:{{ $archive['color'] }};" @click="open = !open">
                    <span x-show="open" x-cloak>{{ $archive['label'] }}</span>
                    <span x-show="!open" class="cb-archive-vlabel">{{ $archive['label'] }}</span>
                    <span class="kanban-count">{{ count($archive['cards']) }}</span>
                </div>
                <div class="kanban-lane" data-lane="completed" x-show="open" x-cloak>
                    @foreach ($archive['cards'] as $card)
                        <div class="kanban-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $archive['color'] }};">
                            <div class="kanban-name">{{ $card['name'] }}</div>
                            <div class="kanban-meta">{{ $card['employee_id'] }}</div>
                            @if($card['class'])<div class="kanban-slot">{{ $card['class'] }}@if($card['date']) · {{ $card['date'] }}@endif</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div></div>
        @else
        <div class="sb-fullbleed"><div class="sb-gpane"><div class="sb-gboard">
            @foreach($this->getSwimlanes() as $swim)
                <div class="sb-swim">
                    <div class="sb-glabel"><span>{{ $swim['label'] }}</span><span class="sb-gcount">{{ $swim['count'] ?? 0 }}</span></div>
                    <div class="sb-grow">
                        @foreach($swim['columns'] as $status => $col)
                            @include('filament.partials.cb-column', ['status' => $status, 'col' => $col])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div></div>
        @endif
    </div>

    <script src="{{ asset('vendor/sortable/Sortable.min.js') }}?v={{ @filemtime(public_path('vendor/sortable/Sortable.min.js')) }}"></script>

    @if($detail)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="closeDetail">
            <div style="background:var(--gqs-surface,#fff);border-radius:16px;width:540px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);border:2px solid #C79A2E;">
                <div style="background:linear-gradient(135deg,#C79A2E,#9E7714);color:#fff;padding:18px 22px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="display:flex;align-items:center;gap:13px;min-width:0;">
                        <span style="width:50px;height:50px;border-radius:13px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <x-filament::icon icon="heroicon-o-academic-cap" style="width:28px;height:28px;color:#fff;"/>
                        </span>
                        <div style="min-width:0;">
                            <div style="font-weight:800;font-size:18px;">{{ $detail['name'] }}</div>
                            <div style="font-size:12px;opacity:.9;">{{ $detail['employee_id'] }}@if($detail['job_title']) · {{ $detail['job_title'] }}@endif</div>
                            <span class="cb-pill" style="background:rgba(0,0,0,.22);margin-top:7px;display:inline-block;">{{ $detail['status'] }}</span>
                        </div>
                    </div>
                    <button wire:click="closeDetail" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;opacity:.8;">&times;</button>
                </div>
                <div style="padding:18px 22px;">
                    @unless($detail['can_edit'])
                        <div style="display:flex;align-items:center;gap:8px;background:#FBF6E9;border:1px solid #E7D6A6;color:#7A5E12;border-radius:9px;padding:8px 12px;font-size:12.5px;margin-bottom:16px;">
                            <x-filament::icon icon="heroicon-m-lock-closed" style="width:15px;height:15px;"/> Read-only. You do not have permission to edit this record.
                        </div>
                    @endunless

                    <div class="dm-sec">Person</div>
                    <div class="dm-grid">
                        <div><div class="dm-l">Department</div><div class="dm-v">{{ $detail['department'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Email</div><div class="dm-v" style="word-break:break-all;">{{ $detail['email'] ?: '—' }}</div></div>
                    </div>

                    <div class="dm-sec">Class Session</div>
                    <div class="dm-grid">
                        <div style="grid-column:1/-1;"><div class="dm-l">Class</div><div class="dm-v">{{ $detail['class'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Date</div><div class="dm-v">{{ $detail['session_date'] ?? '—' }}</div></div>
                        <div><div class="dm-l">Time</div><div class="dm-v">{{ $detail['session_time'] ?? '—' }}</div></div>
                        <div><div class="dm-l">Instructor</div><div class="dm-v">{{ $detail['instructor'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Location</div><div class="dm-v">{{ $detail['location'] ?: '—' }}</div></div>
                    </div>

                    <div class="dm-sec">Attendance Timeline</div>
                    <div class="dm-grid">
                        <div><div class="dm-l">Signed Up</div><div class="dm-v">{{ $detail['signed_up_at'] ?? '—' }}</div></div>
                        <div><div class="dm-l">Attended</div><div class="dm-v">{{ $detail['attended_at'] ?? '—' }}</div></div>
                        <div><div class="dm-l">Completed</div><div class="dm-v">{{ $detail['completed_at'] ?? '—' }}</div></div>
                    </div>

                    @if($detail['qual_status'])
                        <div class="dm-sec">Qualification</div>
                        <div class="dm-grid">
                            <div><div class="dm-l">Status</div><div class="dm-v">{{ $detail['qual_status'] }}</div></div>
                            <div><div class="dm-l">Stage</div><div class="dm-v">{{ $detail['qual_stage'] ?? '—' }}</div></div>
                            <div><div class="dm-l">Runs</div><div class="dm-v">{{ $detail['qual_runs'] ?? '—' }}</div></div>
                            <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $detail['qual_due'] ?? '—' }}</div></div>
                            <div><div class="dm-l">Class On File</div><div class="dm-v">{{ $detail['class_on_file'] ? 'Yes' : 'No' }}</div></div>
                        </div>
                    @endif

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:22px;">
                        <button wire:click="closeDetail" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Close</button>
                        @if($detail['can_edit'])
                            @if($detail['is_approved'])
                                <a href="{{ $detail['qa_url'] }}" style="padding:9px 18px;border-radius:8px;background:#6B2C91;color:#fff;font-weight:700;text-decoration:none;">Edit In QA Review</a>
                            @elseif($detail['edit_url'])
                                <a href="{{ $detail['edit_url'] }}" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;font-weight:700;text-decoration:none;">Edit Record</a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Rebook a No-Show into another session --}}
    @if($rebookEnrollmentId)
        <div class="gqs-modal-overlay" wire:click.self="closeRebook">
            <div class="gqs-modal" style="width:500px;max-width:94vw;">
                <div style="background:linear-gradient(135deg,#1F6FB2,#16517F);padding:16px 20px;display:flex;align-items:center;gap:12px;border-radius:14px 14px 0 0;">
                    <span style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <x-filament::icon icon="heroicon-o-arrow-path" style="width:26px;height:26px;color:#fff;"/>
                    </span>
                    <div style="font-weight:800;font-size:17px;color:#fff;">Rebook Trainee</div>
                </div>
                <div class="gqs-modal-body">
                    <p style="margin:0 0 12px;font-size:13px;color:var(--gqs-text,#1A1A1F);line-height:1.5;">Sign this person up for another class session. The No-Show entry is retired once rebooked.</p>
                    <label class="gqs-flbl">Rebook To</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:6px;">
                        <label style="display:flex;align-items:center;gap:9px;font-size:13.5px;cursor:pointer;">
                            <input type="radio" wire:model.live="rebookMode" value="next"> Next available session (same class)
                        </label>
                        <label style="display:flex;align-items:center;gap:9px;font-size:13.5px;cursor:pointer;">
                            <input type="radio" wire:model.live="rebookMode" value="specific"> A specific session
                        </label>
                    </div>
                    @if($rebookMode === 'specific')
                        <label class="gqs-flbl">Session</label>
                        <select wire:model="rebookSessionId" class="gqs-fld">
                            <option value="">Select a session...</option>
                            @foreach($this->rebookSessionOptions() as $sid => $lbl)<option value="{{ $sid }}">{{ $lbl }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeRebook" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="confirmRebook" class="gqs-btn gqs-btn-primary">Rebook</button>
                </div>
            </div>
        </div>
    @endif
    <style>
        .dm-sec{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#A4123F;margin:16px 0 8px;border-bottom:1px solid var(--gqs-border,#ECECEF);padding-bottom:4px;}
        .dm-sec:first-of-type{margin-top:0;}
        .dm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px;font-size:13px;}
        .dm-l{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);}
        .dm-v{font-weight:600;color:var(--gqs-text,#1A1A1F);margin-top:1px;}
        .dark .dm-v{color:#fff;}
        .cb-pill{font-size:10.5px;font-weight:700;padding:2px 10px;border-radius:20px;color:#fff;display:inline-block;margin-top:6px;}
        .cb-rebook-btn{margin-top:8px;display:block;width:100%;font-size:11.5px;font-weight:700;padding:5px 11px;border-radius:6px;border:none;background:#1F6FB2;color:#fff;cursor:pointer;}
        .cb-rebook-btn:hover{background:#16517F;}
    </style>
    <style>
        .sb-fullbleed{width:100%;}
        .kanban-wrap{display:flex;gap:14px;overflow-x:auto;overflow-y:hidden;padding:0 32px 12px;align-items:stretch;height:calc(100vh - 178px);min-height:360px;}
        .kanban-col{flex:0 0 320px;display:flex;flex-direction:column;max-height:100%;}
        .cb-needs-col{flex:0 0 250px;}
        .cb-needs-card{background:#FFF6F7;}
        .dark .cb-needs-card{background:#241419;}
        .cb-enroll-btn{margin-top:7px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:6px;border:none;background:#A4123F;color:#fff;cursor:pointer;}
        .cb-archive-col{flex:0 0 48px;transition:flex-basis .18s;}
        .cb-archive-col.cb-archive-open{flex:0 0 300px;}
        .cb-archive-head{cursor:pointer;}
        .cb-archive-vlabel{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;font-size:11.5px;letter-spacing:.04em;}
        .kanban-lane{flex:1;}
        .kanban-col{background:#fff;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;padding:10px;min-height:120px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
        .dark .kanban-col{background:#121216;border-color:#34343E;}
        .kanban-head{display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:14px;padding:8px 11px;border-radius:8px;color:#fff;margin-bottom:10px;}
        .kanban-count{background:rgba(255,255,255,.25);border-radius:20px;padding:1px 9px;font-size:12px;}
        .kanban-lane{display:flex;flex-direction:column;gap:8px;min-height:60px;overflow-y:auto;}
        .kanban-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DCDCE2);border-left:4px solid #A4123F;border-radius:9px;padding:10px 12px;cursor:grab;box-shadow:0 1px 3px rgba(0,0,0,.08);}
        .dark .kanban-card{background:#2C2C36;border-color:#44444F;box-shadow:0 1px 3px rgba(0,0,0,.4);}
        .kanban-name{font-weight:700;font-size:14px;color:var(--gqs-text,#1A1A1F);}
        .kanban-meta{font-size:12px;color:var(--gqs-text-dim,#6A6A72);}
        .kanban-slot{font-size:12px;color:#A4123F;font-weight:600;margin-top:4px;}
        .kanban-ghost{opacity:.4;}
        .dark .kanban-card{background:#1F1F25;} .dark .kanban-name{color:#fff;}
    </style>
</x-filament-panels::page>
