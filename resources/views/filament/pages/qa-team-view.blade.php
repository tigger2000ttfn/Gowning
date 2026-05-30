<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'QA Team & Approval Ownership', 'subtitle' => 'Who owns which pending sign-offs, and review workload.', 'icon' => 'heroicon-o-clipboard-document-check'])

    @php
        $reviewers = $this->getReviewers();
        $unassigned = $this->getUnassigned();
        $manager = $this->manager();
        $total = $this->totalPending();
    @endphp

    <div class="gqs-stats">
        <div class="gqs-stat charcoal"><div class="n" style="font-size:18px;">{{ $manager?->name ?? 'Unassigned' }}</div><div class="l">QA Manager</div><span class="wm"><x-filament::icon icon="heroicon-o-user-circle"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $reviewers->count() }}</div><div class="l">Reviewers</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $unassigned->count() }}</div><div class="l">Unassigned Approvals</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $total }}</div><div class="l">Total Pending</div><span class="wm"><x-filament::icon icon="heroicon-o-inbox-stack"/></span></div>
    </div>

    <div class="gqs-tabs">
        @foreach(['overview' => 'Overview', 'table' => 'Workload Table', 'unassigned' => 'Unassigned'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="gqs-tab {{ $tab === $key ? 'on' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if($unassigned->isNotEmpty() && $tab !== 'unassigned')
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C79A2E,#9E7818);"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Unassigned Approvals ({{ $unassigned->count() }})</div>
            <div class="gqs-panel-body">
                @foreach($unassigned->take(5) as $q)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);font-size:13.5px;">
                        <span>{{ $q->personnel?->full_name }} <span style="color:var(--gqs-text-dim,#9A9AA4);">({{ $q->personnel?->employee_id }})</span></span>
                        <button wire:click="openAssign({{ $q->id }})" class="gqs-mini-btn">Assign Owner</button>
                    </div>
                @endforeach
                @if($unassigned->count() > 5)<div style="padding:8px 16px;font-size:12px;color:var(--gqs-text-dim,#9A9AA4);">+ {{ $unassigned->count() - 5 }} more, see Unassigned tab.</div>@endif
            </div>
        </div>
    @endif

    @if($tab === 'overview')
        @forelse($reviewers as $r)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-user"/> {{ $r->name }}@if($r->is_manager)<span class="gqs-pill gqs-pill-purple" style="margin-left:6px;">Manager</span>@endif</span>
                    <span style="font-size:12px;font-weight:600;opacity:.92;">{{ $r->load }} {{ \Illuminate\Support\Str::plural('approval', $r->load) }}</span>
                </div>
                <div class="gqs-panel-body">
                    @if($r->owned->isEmpty())
                        <div class="gqs-empty">No Approvals Assigned.</div>
                    @else
                        <table class="gqs-tbl">
                            <thead><tr><th>Person</th><th>Employee ID</th><th>Stage</th><th>Waiting Since</th></tr></thead>
                            <tbody>
                                @foreach($r->owned as $q)
                                    <tr>
                                        <td>{{ $q->personnel?->full_name }}</td>
                                        <td>{{ $q->personnel?->employee_id }}</td>
                                        <td><span class="gqs-pill gqs-pill-gold">{{ $q->workflow_stage?->label() }}</span></td>
                                        <td>{{ $q->stage_changed_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @empty
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No QA team members. Set a person's Team to "Quality Assurance" on their user record.</div></div>
        @endforelse
    @endif

    @if($tab === 'table')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Reviewer Workload</div>
            <div class="gqs-panel-body">
                <table class="gqs-tbl">
                    <thead><tr><th>Reviewer</th><th>Owned Approvals</th><th>Load</th></tr></thead>
                    <tbody>
                        @forelse($reviewers as $r)
                            <tr>
                                <td style="font-weight:600;">{{ $r->name }}@if($r->is_manager)<span class="gqs-pill gqs-pill-purple" style="margin-left:6px;">Mgr</span>@endif</td>
                                <td>{{ $r->load }}</td>
                                <td><span class="gqs-pill {{ $r->load > 8 ? 'gqs-pill-red' : 'gqs-pill-green' }}">{{ $r->load }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="gqs-empty">No team members.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($tab === 'unassigned')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-user-plus"/> Unassigned Approvals</div>
            <div class="gqs-panel-body">
                @forelse($unassigned as $q)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);font-size:13.5px;">
                        <span>{{ $q->personnel?->full_name }} <span style="color:var(--gqs-text-dim,#9A9AA4);">({{ $q->personnel?->employee_id }}) · {{ $q->workflow_stage?->label() }}</span></span>
                        <button wire:click="openAssign({{ $q->id }})" class="gqs-mini-btn">Assign Owner</button>
                    </div>
                @empty
                    <div class="gqs-empty">All Approvals Have Owners.</div>
                @endforelse
            </div>
        </div>
    @endif

    @if($showAssign)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAssign', false)">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:400px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Assign Approval Owner</div>
                <div style="padding:18px 20px;">
                    <label class="gqs-flbl">QA Reviewer</label>
                    <select wire:model="assignOwnerId" class="gqs-fld">
                        <option value="">Unassigned</option>
                        @foreach($this->reviewerOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button type="button" wire:click="$set('showAssign', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="button" wire:click="saveAssign" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
