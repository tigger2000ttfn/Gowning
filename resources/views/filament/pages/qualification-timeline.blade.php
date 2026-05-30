<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">

    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-chart-bar" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Qualification Timeline</h1>
                <p>Every person's path to qualified. Bar fill shows run progress, color shows status.</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID" class="gqs-fld sb-hf-search">
            <select wire:model.live="deptFilter" class="gqs-fld sb-hf-sel">
                <option value="">All Departments</option>
                @foreach($this->departmentOptions() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
            <select wire:model.live="statusFilter" class="gqs-fld sb-hf-sel">
                <option value="">All Statuses</option>
                <option value="in_progress">In Progress</option>
                <option value="qualified">Qualified</option>
                <option value="lapsed">Lapsed</option>
                <option value="pending">Pending</option>
            </select>
            <select wire:model.live="viewMode" class="gqs-fld sb-hf-sel" style="min-width:110px;">
                <option value="Day">Day</option>
                <option value="Week">Week</option>
                <option value="Month">Month</option>
            </select>
        </div>
    </div>

    @php $tasks = $this->tasks(); @endphp

    <div class="tl-legend">
        <span><i style="background:#A4123F;"></i>In Progress</span>
        <span><i style="background:#2E7D5B;"></i>Qualified</span>
        <span><i style="background:#C8102E;"></i>Lapsed</span>
        <span class="tl-count">{{ count($tasks) }} shown</span>
    </div>

    <div class="tl-fullbleed">
        <div id="gqs-gantt-data" data-tasks='@json($tasks)' data-mode="{{ $viewMode }}" style="display:none;"></div>
        @if(count($tasks))
            <div class="tl-scroll"><svg id="gqs-gantt"></svg></div>
        @else
            <div class="gqs-empty" style="padding:40px;">No qualifications match. Adjust the filters.</div>
        @endif
    </div>

    <div wire:ignore>
        <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
        <script>
            (function () {
                function render() {
                    const data = document.getElementById('gqs-gantt-data');
                    const el = document.getElementById('gqs-gantt');
                    if (!data || !el || !window.Gantt) return;
                    let tasks = [];
                    try { tasks = JSON.parse(data.getAttribute('data-tasks') || '[]'); } catch (e) { return; }
                    if (!tasks.length) return;
                    el.innerHTML = '';
                    try {
                        new Gantt('#gqs-gantt', tasks, {
                            view_mode: data.getAttribute('data-mode') || 'Month',
                            bar_height: 26, padding: 22, readonly: true, popup_trigger: 'mouseover',
                        });
                    } catch (e) { console.error('Gantt render failed', e); }
                }
                function boot() { render(); }
                if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
                document.addEventListener('livewire:initialized', () => {
                    if (window.Livewire) { Livewire.hook('morph.updated', () => setTimeout(render, 50)); }
                });
            })();
        </script>
    </div>

    <style>
        .tl-fullbleed{ margin:0 -32px; }
        .fi-main:has(.tl-fullbleed){ padding-left:0 !important; padding-right:0 !important; max-width:none !important; }
        .tl-scroll{ overflow-x:auto; padding:6px 32px 18px; }
        .tl-legend{ display:flex; align-items:center; gap:18px; padding:4px 32px 10px; font-size:12px; color:var(--gqs-text-dim,#6A6A72); }
        .tl-legend i{ display:inline-block; width:11px; height:11px; border-radius:3px; margin-right:5px; vertical-align:middle; }
        .tl-legend .tl-count{ margin-left:auto; font-weight:700; }

        #gqs-gantt .bar-label{ fill:#1A1A1F !important; font-size:12px !important; font-weight:600 !important; }
        #gqs-gantt .bar-label.big{ fill:#1A1A1F !important; }
        .dark #gqs-gantt .bar-label, .dark #gqs-gantt .bar-label.big{ fill:#F4F4F6 !important; }
        #gqs-gantt .lower-text, #gqs-gantt .upper-text{ font-size:11.5px !important; fill:#3A3A42 !important; font-weight:600; }
        .dark #gqs-gantt .lower-text, .dark #gqs-gantt .upper-text{ fill:#C8C8D0 !important; }
        #gqs-gantt .grid-header{ fill:#F0F0F3; }
        .dark #gqs-gantt .grid-header{ fill:#23232B; }

        #gqs-gantt .bar-wrapper.gantt-qualified .bar{fill:#A9D3BF !important;}
        #gqs-gantt .bar-wrapper.gantt-qualified .bar-progress{fill:#2E7D5B !important;}
        #gqs-gantt .bar-wrapper.gantt-active .bar{fill:#E6C2D2 !important;}
        #gqs-gantt .bar-wrapper.gantt-active .bar-progress{fill:#A4123F !important;}
        #gqs-gantt .bar-wrapper.gantt-lapsed .bar{fill:#F2C2C7 !important;}
        #gqs-gantt .bar-wrapper.gantt-lapsed .bar-progress{fill:#C8102E !important;}
    </style>
</x-filament-panels::page>
