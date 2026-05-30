<x-filament-panels::page>
    @php $tab = $this->tab ?? 'calendar'; @endphp

    @include('filament.page-hero', ['title' => 'Schedule Calendar', 'icon' => 'heroicon-o-calendar', 'actions' => '
        <button type="button" wire:click="$set(\'tab\',\'calendar\')" class="gqs-tab ' . ($tab === 'calendar' ? 'active' : '') . '">Calendar</button>
        <button type="button" wire:click="$set(\'tab\',\'list\')" class="gqs-tab ' . ($tab === 'list' ? 'active' : '') . '">List</button>
    '])

    <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px;font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#A4123F;margin-right:4px;"></span>Run Days</span>
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#2E7D5B;margin-right:4px;"></span>Classes</span>
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#C79A2E;margin-right:4px;"></span>Due Dates</span>
    </div>

    @if($tab === 'list')
        @php $items = $this->listItems(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-body" style="padding:0;">
                @if(empty($items))
                    <div class="gqs-empty" style="padding:30px;">No upcoming run days or classes scheduled. Add them in Run Scheduler / Class Scheduler.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Date</th><th>Type</th><th>What</th><th>When</th></tr></thead>
                        <tbody>
                            @foreach($items as $it)
                                <tr>
                                    <td style="font-weight:700;white-space:nowrap;">{{ $it['date']->format('D, d M Y') }}</td>
                                    <td><span class="gqs-pill" style="background:{{ $it['color'] }};color:#fff;">{{ $it['type'] }}</span></td>
                                    <td>{{ $it['title'] }}</td>
                                    <td style="color:var(--gqs-text-dim,#6A6A72);">{{ $it['sub'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
    @php $weeks = $this->monthGrid(); @endphp
    <div class="gqs-panel">
        <div class="gqs-panel-head" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-calendar-days"/> {{ $this->monthLabel() }}</span>
            <span style="display:flex;align-items:center;gap:6px;">
                <button type="button" wire:click="prevMonth" class="rd-act" style="background:#fff;color:#A4123F;">‹ Prev</button>
                <button type="button" wire:click="thisMonth" class="rd-act" style="background:#fff;color:#A4123F;">Today</button>
                <button type="button" wire:click="nextMonth" class="rd-act" style="background:#fff;color:#A4123F;">Next ›</button>
            </span>
        </div>
        <div class="gqs-panel-body" style="padding:0;">
            <div class="cal-grid">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                    <div class="cal-dow">{{ $dow }}</div>
                @endforeach
                @foreach($weeks as $week)
                    @foreach($week as $cell)
                        <div class="cal-cell {{ $cell['inMonth'] ? '' : 'cal-out' }} {{ $cell['isToday'] ? 'cal-today' : '' }}">
                            <div class="cal-num">{{ $cell['day'] }}</div>
                            @foreach($cell['events'] as $ev)
                                <div class="cal-ev" style="border-left:3px solid {{ $ev['color'] }};" title="{{ $ev['title'] }}{{ $ev['time'] ? ' · '.$ev['time'] : '' }}">
                                    <span class="cal-ev-t">{{ $ev['title'] }}</span>@if($ev['time'])<span class="cal-ev-w">{{ $ev['time'] }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <style>
        .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--gqs-border,#E2E2E6);border-top:1px solid var(--gqs-border,#E2E2E6);}
        .cal-dow{background:#26262C;color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 6px;text-align:center;}
        .cal-cell{background:var(--gqs-surface,#fff);min-height:104px;padding:6px 6px 8px;display:flex;flex-direction:column;gap:3px;}
        .dark .cal-cell{background:#1A1A20;}
        .cal-out{background:#F6F6F8;}
        .dark .cal-out{background:#141418;}
        .cal-out .cal-num{opacity:.4;}
        .cal-today{box-shadow:inset 0 0 0 2px #A4123F;}
        .cal-num{font-size:12.5px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-bottom:2px;}
        .dark .cal-num{color:#ECECF0;}
        .cal-ev{background:var(--gqs-surface-2,#F4F4F7);border-radius:5px;padding:3px 6px;font-size:11px;line-height:1.25;display:flex;flex-direction:column;overflow:hidden;}
        .dark .cal-ev{background:#26262E;}
        .cal-ev-t{font-weight:600;color:var(--gqs-text,#1A1A1F);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .dark .cal-ev-t{color:#ECECF0;}
        .cal-ev-w{font-size:10px;color:var(--gqs-text-dim,#6A6A72);}
        @media (max-width:640px){ .cal-cell{min-height:74px;} .cal-ev-t{font-size:10px;} }
    </style>
</x-filament-panels::page>
