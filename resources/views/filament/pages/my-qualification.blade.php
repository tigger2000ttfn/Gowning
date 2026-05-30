<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'My Qualification', 'subtitle' => 'Your status and run history.', 'icon' => 'heroicon-o-identification'])

    @if($this->rescheduleAction->isVisible())
        <div style="margin-bottom:16px;">{{ $this->rescheduleAction }}</div>
    @endif
    @php $activeRes = $this->myActiveReservation(); @endphp
    @if($activeRes && $activeRes->runSlot)
        <div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;background:var(--gqs-surface-2,#F4F4F6);border-radius:10px;">
            <x-filament::icon icon="heroicon-o-calendar-days" style="width:20px;height:20px;color:#A4123F;"/>
            <span style="font-size:13.5px;color:var(--gqs-text,#1A1A1F);">Your next run: <strong>{{ $activeRes->runSlot->slot_date->format('l, M j, Y') }}</strong>{{ $activeRes->runSlot->cleanroom ? ' · ' . $activeRes->runSlot->cleanroom : '' }}</span>
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
                <div class="n" style="font-size:22px;">{{ $qualification?->due_date?->format('M j, Y') ?? '-' }}</div>
                <div class="l">Due Date @if($qualification?->due_date)· {{ $qualification->isPastDue() ? 'Overdue' : 'Current' }}@endif</div>
                <span class="wm"><x-filament::icon icon="heroicon-o-calendar-days"/></span>
            </div>
            <div class="gqs-stat {{ $hasClass ? 'green' : 'gold' }}">
                <div class="n" style="font-size:22px;">{{ $hasClass ? 'Completed' : 'Not On File' }}</div>
                <div class="l">Gowning Class @if($hasClass)· {{ $classes->first()->completion_date?->format('M j, Y') }}@endif</div>
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
                            <tr><td>{{ $run->run_date?->format('M j, Y') }}</td>
                                <td><span class="gqs-pill {{ $run->result?->value === 'pass' ? 'gqs-pill-green' : 'gqs-pill-red' }}">{{ $run->result?->label() }}</span></td>
                                <td>{{ $run->cycle_type?->label() }}</td></tr>
                        @endforeach</tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#6B2C91,#4A1E66);"><x-filament::icon icon="heroicon-m-calendar"/> My Upcoming Classes</div>
            <div class="gqs-panel-body">
                @if ($enrollments->isEmpty())
                    <div class="gqs-empty">You're Not Signed Up For Any Upcoming Classes.
                        <a href="{{ url('/') }}" style="color:#A4123F;font-weight:700;">Browse Classes →</a></div>
                @else
                    @foreach ($enrollments as $e)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                            <span><strong>{{ $e->classSession?->trainingClass?->name }}</strong>
                                <span style="color:var(--gqs-text-dim,#6A6A72);"> · {{ $e->classSession?->session_date?->format('M j, Y') }}</span></span>
                            <span class="gqs-pill gqs-pill-purple">{{ str_replace('_',' ',$e->status) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
