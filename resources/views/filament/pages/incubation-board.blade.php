<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Incubation & Results', 'subtitle' => 'Plates auto-advance to Awaiting Results after the incubation period. Enter pass/fail when LIMS has them.', 'icon' => 'heroicon-o-beaker'])

    @php $incubating = $this->getIncubating(); $days = $this->incubationDays(); $ready = $incubating->where('awaiting', true); @endphp

    <div class="gqs-stats">
        <div class="gqs-stat gold"><div class="n">{{ $incubating->where('awaiting', false)->count() }}</div><div class="l">Incubating</div><span class="wm"><x-filament::icon icon="heroicon-o-beaker"/></span></div>
        <div class="gqs-stat green"><div class="n">{{ $ready->count() }}</div><div class="l">Ready To Read</div><span class="wm"><x-filament::icon icon="heroicon-o-check-badge"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $days }}</div><div class="l">Incubation Days</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#B8860B,#8A6309);"><x-filament::icon icon="heroicon-m-beaker"/> Incubation Timeline</div>
        <div class="gqs-panel-body">
            @forelse ($incubating as $row)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);{{ $row->awaiting ? 'background:rgba(46,125,91,.06);' : '' }}">
                    <div>
                        <div style="font-weight:700;color:var(--gqs-text,#1A1A1F);">{{ $row->name }}</div>
                        <div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
                            {{ $row->employee_id }}
                            @if($row->started) · started {{ $row->started->setTimezone('America/New_York')->format('M j') }}@endif
                            @if($row->ready) · read by {{ $row->ready->setTimezone('America/New_York')->format('M j') }}@endif
                            @if($row->worklist) · WL {{ $row->worklist }}@endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;white-space:nowrap;">
                        @if($row->awaiting)
                            <span class="gqs-pill gqs-pill-green">Ready To Read</span>
                            {{ ($this->enterResultsAction)(['id' => $row->id]) }}
                        @else
                            <span class="gqs-pill gqs-pill-gold">{{ (int) ceil($row->remaining) }} Days Left</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="gqs-empty">Nothing In Incubation.</div>
            @endforelse
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
