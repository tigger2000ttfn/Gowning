<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'My Qualification', 'icon' => 'heroicon-o-identification'])

    @php
        $showBook = $this->bookClassAction->isVisible();
        $showRun = $this->requestRunAction->isVisible();
        $showResched = $this->rescheduleAction->isVisible();
    @endphp
    @if($showBook || $showRun || $showResched)
        <div style="margin:-4px 0 16px;display:flex;gap:10px;flex-wrap:wrap;">
            @if($showBook){{ $this->bookClassAction }}@endif
            @if($showRun){{ $this->requestRunAction }}@endif
            @if($showResched){{ $this->rescheduleAction }}@endif
        </div>
    @endif

    @php $needsClass = $person && ! $person->qualification?->class_on_file; @endphp
    @if($needsClass)
        <div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;background:#FFF6E5;border:1px solid #F0D08A;border-radius:10px;">
            <x-filament::icon icon="heroicon-o-academic-cap" style="width:20px;height:20px;color:#9A6B00;"/>
            <span style="font-size:13.5px;color:#6B4A00;">You Need To Complete The Gowning Class Before A Run Can Be Requested.
                <a href="{{ url('/') }}" style="color:#A4123F;font-weight:700;">Schedule Class →</a></span>
        </div>
    @endif
    @php $activeRes = $this->myActiveReservation(); @endphp
    @if($activeRes && $activeRes->runSlot)
        <div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;background:var(--gqs-surface-2,#F4F4F6);border-radius:10px;">
            <x-filament::icon icon="heroicon-o-calendar-days" style="width:20px;height:20px;color:#A4123F;"/>
            <span style="font-size:13.5px;color:var(--gqs-text,#1A1A1F);">Your next run: <strong>{{ $activeRes->runSlot->slot_date->gmpL() }}</strong>{{ $activeRes->runSlot->cleanroom ? ' · ' . $activeRes->runSlot->cleanroom : '' }}</span>
            <a href="{{ route('public.run.ics', $activeRes->runSlot) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#A4123F;color:#fff;border-radius:8px;font-weight:700;font-size:12.5px;text-decoration:none;">
                <x-filament::icon icon="heroicon-m-arrow-down-tray" style="width:15px;height:15px;"/> Add To Calendar
            </a>
        </div>
    @endif
    <x-filament-actions::modals />

    @if (! $person)
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">
            No personnel record is linked to your account yet. An administrator can link your
            account to your employee record so your qualification status appears here.
        </div></div>
    @else
        @php
            $q = $qualification;
            $st = $q?->status?->value;
            $statColor = $st === 'qualified' ? 'green' : ($st === 'in_progress' ? 'gold' : ($st === 'lapsed' ? 'red' : 'charcoal'));
            $hasClass = $classes->isNotEmpty();
        @endphp

        <div class="gqs-stats">
            <div class="gqs-stat {{ $statColor }}">
                <div class="n" style="font-size:22px;">{{ $q?->status?->label() ?? 'Not Started' }}</div>
                <div class="l">@if($q){{ $q->runs_completed }} / {{ $q->runs_required }} Runs · {{ $q->type?->label() }}@else No Qualification Yet @endif</div>
                <span class="wm"><x-filament::icon icon="heroicon-o-shield-check"/></span>
            </div>
            <div class="gqs-stat {{ $qualification?->isPastDue() ? 'red' : 'magenta' }}">
                <div class="n" style="font-size:22px;">{{ $qualification?->due_date?->gmp() ?? '-' }}</div>
                <div class="l">Due Date @if($qualification?->due_date)· {{ $qualification->isPastDue() ? 'Overdue' : 'Current' }}@endif</div>
                <span class="wm"><x-filament::icon icon="heroicon-o-calendar-days"/></span>
            </div>
            <div class="gqs-stat {{ $hasClass ? 'green' : 'gold' }}">
                <div class="n" style="font-size:22px;">{{ $hasClass ? 'Completed' : 'Not On File' }}</div>
                <div class="l">Gowning Class @if($hasClass)· {{ $classes->first()->completion_date?->gmp() }}@endif</div>
                <span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span>
            </div>
        </div>

        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clipboard-document-check"/> My Run History</div>
            <div class="gqs-panel-body">
                @if ($runs->isEmpty())<div class="gqs-empty">No Runs Recorded Yet.</div>@else
                    <table class="gqs-tbl">
                        <thead><tr><th>Date</th><th>Result</th><th>Cycle</th></tr></thead>
                        <tbody>@foreach ($runs as $run)
                            <tr><td>{{ $run->run_date?->gmp() }}</td>
                                <td><span class="gqs-pill {{ $run->result?->value === 'pass' ? 'gqs-pill-green' : 'gqs-pill-red' }}">{{ $run->result?->label() }}</span></td>
                                <td>{{ $run->cycle_type?->label() }}</td></tr>
                        @endforeach</tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- My Schedule: self-service management of upcoming classes and runs --}}
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#6B2C91,#4A1E66);"><x-filament::icon icon="heroicon-m-calendar-days"/> My Schedule</div>
            <div class="gqs-panel-body" style="padding:0;">
                @php $hasAny = $enrollments->isNotEmpty() || $bookings->isNotEmpty(); @endphp
                @if(! $hasAny)
                    <div class="gqs-empty" style="padding:20px;">Nothing scheduled right now. Use the buttons above to book a class or request a run.</div>
                @else
                    {{-- Run bookings --}}
                    @foreach($bookings as $b)
                        @php $slot = $b->runSlot; $needsAck = $b->acknowledged_at === null && in_array($b->status, ['approved'], true) && str_contains(strtolower((string) $b->notes), 'system'); @endphp
                        <div class="mysch-row">
                            <div class="mysch-ico" style="background:#A4123F1A;color:#A4123F;"><x-filament::icon icon="heroicon-m-beaker" style="width:18px;height:18px;"/></div>
                            <div class="mysch-main">
                                <div class="mysch-title">Qualification Run @if($slot?->is_special)<span class="gqs-pill gqs-pill-purple">Special</span>@endif</div>
                                <div class="mysch-sub">{{ $slot?->slot_date?->gmpL() ?? 'Date TBC' }}{{ $slot?->cleanroom ? ' · ' . $slot->cleanroom : '' }}{{ $slot?->start_time ? ' · ' . \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') : '' }}</div>
                                @if($needsAck)<div class="mysch-ack"><x-filament::icon icon="heroicon-m-information-circle" style="width:14px;height:14px;"/> This run was booked for you. Please acknowledge.</div>@endif
                            </div>
                            <div class="mysch-actions">
                                @if($slot)<a href="{{ route('public.run.ics', $slot) }}" class="mysch-btn mysch-btn-ghost"><x-filament::icon icon="heroicon-m-arrow-down-tray" style="width:14px;height:14px;"/> Calendar</a>@endif
                                @if($needsAck)<button wire:click="acknowledgeReservation({{ $b->id }})" class="mysch-btn mysch-btn-primary">Acknowledge</button>@endif
                                <button wire:click="cancelMyRun({{ $b->id }})" wire:confirm="Cancel this run booking?" class="mysch-btn mysch-btn-danger">Cancel</button>
                            </div>
                        </div>
                    @endforeach

                    {{-- Class enrollments --}}
                    @foreach($enrollments as $e)
                        @php $cancelled = $e->status === 'cancelled'; $sess = $e->classSession; @endphp
                        <div class="mysch-row {{ $cancelled ? 'mysch-cancelled' : '' }}">
                            <div class="mysch-ico" style="background:{{ $cancelled ? '#C8102E1A' : '#6B2C911A' }};color:{{ $cancelled ? '#C8102E' : '#6B2C91' }};"><x-filament::icon icon="heroicon-m-academic-cap" style="width:18px;height:18px;"/></div>
                            <div class="mysch-main">
                                <div class="mysch-title">{{ $sess?->trainingClass?->name ?? 'Gowning Class' }}
                                    <span class="gqs-pill {{ $cancelled ? 'gqs-pill-red' : 'gqs-pill-purple' }}">{{ \Illuminate\Support\Str::title(str_replace('_',' ',$e->status)) }}</span>
                                </div>
                                <div class="mysch-sub">{{ $sess?->session_date?->gmpL() ?? 'Date TBC' }}{{ $sess?->location ? ' · ' . $sess->location : '' }}{{ $sess?->start_time ? ' · ' . \Illuminate\Support\Carbon::parse($sess->start_time)->format('H:i') : '' }}</div>
                                @if($cancelled)<div class="mysch-ack" style="color:#C8102E;"><x-filament::icon icon="heroicon-m-exclamation-triangle" style="width:14px;height:14px;"/> This class was cancelled. Rebook to stay on track.</div>@endif
                            </div>
                            <div class="mysch-actions">
                                @if($cancelled)
                                    @if($this->canBookClass())<button wire:click="mountAction('bookClass')" class="mysch-btn mysch-btn-primary">Rebook</button>@endif
                                    <button wire:click="dismissEnrollment({{ $e->id }})" class="mysch-btn mysch-btn-ghost">Remove From View</button>
                                @else
                                    <button wire:click="cancelMyClass({{ $e->id }})" wire:confirm="Cancel this class booking?" class="mysch-btn mysch-btn-danger">Cancel</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    <style>
        .mysch-row{display:flex;align-items:flex-start;gap:13px;padding:13px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);}
        .mysch-row:last-child{border-bottom:none;}
        .mysch-cancelled{background:#FDF3F4;}
        .dark .mysch-cancelled{background:#2A1518;}
        .mysch-ico{flex:0 0 36px;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;}
        .mysch-main{flex:1;min-width:0;}
        .mysch-title{font-weight:700;font-size:14px;color:var(--gqs-text,#1A1A1F);display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .mysch-sub{font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;}
        .mysch-ack{font-size:11.5px;font-weight:600;color:#6B2C91;margin-top:5px;display:inline-flex;align-items:center;gap:4px;}
        .mysch-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center;}
        .mysch-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:6px 12px;border-radius:8px;border:1px solid transparent;cursor:pointer;text-decoration:none;}
        .mysch-btn-primary{background:#A4123F;color:#fff;}
        .mysch-btn-danger{background:transparent;color:#C8102E;border-color:#E7A8B4;}
        .mysch-btn-danger:hover{background:#FCEEF0;}
        .mysch-btn-ghost{background:transparent;color:var(--gqs-text-dim,#5A5A62);border-color:var(--gqs-border,#D6D6DC);}
        .mysch-btn-ghost:hover{background:var(--gqs-surface-2,#F4F4F6);}
    </style>
</x-filament-panels::page>
