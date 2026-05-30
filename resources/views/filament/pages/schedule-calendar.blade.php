<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Schedule Calendar', 'subtitle' => 'Run days, class sessions, and qualification due dates in one view.', 'icon' => 'heroicon-o-calendar'])

    {{-- Toolbar --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <button wire:click="prevMonth" class="cal-nav"><x-filament::icon icon="heroicon-m-chevron-left" style="width:16px;height:16px;"/></button>
            <span style="font-weight:800;font-size:17px;min-width:160px;text-align:center;color:var(--gqs-text,#1A1A1F);">{{ $this->monthLabel() }}</span>
            <button wire:click="nextMonth" class="cal-nav"><x-filament::icon icon="heroicon-m-chevron-right" style="width:16px;height:16px;"/></button>
            <button wire:click="today" class="cal-today">Today</button>
        </div>
        <div style="display:flex;align-items:center;gap:14px;font-size:12.5px;">
            <label class="cal-leg"><input type="checkbox" wire:model.live="showRuns"> <span class="dot" style="background:#A4123F;"></span> Run Days</label>
            <label class="cal-leg"><input type="checkbox" wire:model.live="showClasses"> <span class="dot" style="background:#2E7D5B;"></span> Classes</label>
            <label class="cal-leg"><input type="checkbox" wire:model.live="showDue"> <span class="dot" style="background:#C79A2E;"></span> Due Dates</label>
        </div>
    </div>

    <div class="cal-grid">
        <div class="cal-dow">Sun</div><div class="cal-dow">Mon</div><div class="cal-dow">Tue</div><div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div>
        @foreach($this->getGrid() as $week)
            @foreach($week as $cell)
                <div class="cal-cell {{ $cell['in_month'] ? '' : 'cal-out' }} {{ $cell['is_today'] ? 'cal-today-cell' : '' }}">
                    <div class="cal-daynum">{{ $cell['day'] }}</div>
                    @foreach(array_slice($cell['events'], 0, 4) as $ev)
                        <div class="cal-ev" style="border-left:3px solid {{ $ev['color'] }};" title="{{ $ev['label'] }}">{{ $ev['label'] }}</div>
                    @endforeach
                    @if(count($cell['events']) > 4)
                        <div class="cal-more">+{{ count($cell['events']) - 4 }} more</div>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>

    <style>
        .cal-nav,.cal-today{border:1px solid var(--gqs-border,#C4C4CC);background:var(--gqs-surface,#fff);border-radius:8px;cursor:pointer;color:var(--gqs-text,#1A1A1F);}
        .cal-nav{padding:7px 9px;display:inline-flex;} .cal-today{padding:7px 14px;font-weight:600;font-size:12.5px;}
        .cal-nav:hover,.cal-today:hover{background:var(--gqs-surface-2,#F1F1F4);}
        .cal-leg{display:flex;align-items:center;gap:5px;cursor:pointer;color:var(--gqs-text-dim,#6A6A72);}
        .cal-leg .dot{width:10px;height:10px;border-radius:50%;display:inline-block;}
        .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--gqs-border,#E2E2E6);border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;overflow:hidden;}
        .cal-dow{background:#1C1C21;color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:9px;text-align:center;}
        .cal-cell{background:var(--gqs-surface,#fff);min-height:104px;padding:6px 7px;display:flex;flex-direction:column;gap:3px;}
        .cal-out{background:var(--gqs-surface-2,#FAFAFB);} .dark .cal-out{background:#16161B;}
        .cal-out .cal-daynum{color:var(--gqs-text-dim,#B0B0B8);}
        .cal-today-cell{box-shadow:inset 0 0 0 2px #A4123F;}
        .cal-daynum{font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);}
        .cal-ev{font-size:10.5px;line-height:1.25;padding:2px 5px;background:var(--gqs-surface-2,#F4F4F6);border-radius:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gqs-text,#1A1A1F);}
        .dark .cal-ev{background:#23232B;}
        .cal-more{font-size:10px;color:var(--gqs-text-dim,#9A9AA4);font-weight:600;padding-left:5px;}
        .dark .cal-cell{background:#1A1A20;}
    </style>
</x-filament-panels::page>
