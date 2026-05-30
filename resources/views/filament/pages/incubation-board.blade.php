<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Incubation & Results Release', 'subtitle' => 'Plates in incubation and results ready to release to QA.', 'icon' => 'heroicon-o-beaker'])

    @php $incubating = $this->getIncubating(); $days = $this->incubationDays(); @endphp

    <div class="gqs-stats">
        <div class="gqs-stat gold">
            <div class="n">{{ $incubating->count() }}</div><div class="l">In Incubation</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-beaker"/></span>
        </div>
        <div class="gqs-stat green">
            <div class="n">{{ $incubating->where('done', true)->count() }}</div><div class="l">Ready To Release</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-check-badge"/></span>
        </div>
        <div class="gqs-stat charcoal">
            <div class="n">{{ $days }}</div><div class="l">Incubation Days (Setting)</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span>
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#B8860B,#8A6309);"><x-filament::icon icon="heroicon-m-beaker"/> Currently Incubating</div>
        <div class="gqs-panel-body">
            @forelse ($incubating as $row)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <div>
                        <div style="font-weight:700;color:var(--gqs-text,#1A1A1F);">{{ $row->name }}</div>
                        <div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
                            {{ $row->employee_id }}
                            @if($row->started) · started {{ $row->started->setTimezone('America/New_York')->format('M j, g:i A') }}@endif
                            @if($row->ready) · ready {{ $row->ready->setTimezone('America/New_York')->format('M j') }}@endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;white-space:nowrap;">
                        @if($row->done)
                            <span class="gqs-pill gqs-pill-green">Incubation Complete</span>
                        @elseif($row->remaining !== null)
                            <span class="gqs-pill gqs-pill-gold">{{ (int) ceil($row->remaining) }} Days Left</span>
                        @else
                            <span class="gqs-pill gqs-pill-gold">Timer Not Set</span>
                        @endif
                        <button wire:click="releaseResults({{ $row->id }})" class="sb-act sb-act-green" @if(!$row->done) onclick="return confirm('Incubation period is not complete. Release results anyway?')" @endif>Release Results</button>
                    </div>
                </div>
            @empty
                <div class="gqs-empty">Nothing In Incubation.</div>
            @endforelse
        </div>
    </div>

    <style>
        .sb-act{font-size:12px;font-weight:700;padding:6px 14px;border-radius:7px;border:none;cursor:pointer;color:#fff;}
        .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
    </style>
</x-filament-panels::page>
