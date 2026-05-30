<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Timeline', 'subtitle' => 'Each person\'s path to qualified, on a Gantt timeline. Bar fill shows run progress.', 'icon' => 'heroicon-o-chart-bar'])

    {{-- Frappe Gantt (CDN, same pattern as FullCalendar/Sortable already used in this app) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
        <div style="flex:1;min-width:180px;max-width:300px;">
            <label class="gqs-flbl">Search</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name or employee ID" class="gqs-fld">
        </div>
        <div style="min-width:170px;">
            <label class="gqs-flbl">Department</label>
            <select wire:model.live="deptFilter" class="gqs-fld">
                <option value="">All Departments</option>
                @foreach($this->departmentOptions() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
        </div>
        <div style="min-width:150px;">
            <label class="gqs-flbl">Zoom</label>
            <select wire:model.live="viewMode" class="gqs-fld">
                <option value="Day">Day</option>
                <option value="Week">Week</option>
                <option value="Month">Month</option>
            </select>
        </div>
        <div style="display:flex;gap:14px;align-items:center;font-size:12px;color:var(--gqs-text-dim,#6A6A72);padding-bottom:8px;">
            <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#A4123F;margin-right:4px;"></span>In progress</span>
            <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#2E7D5B;margin-right:4px;"></span>Qualified</span>
            <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#C8102E;margin-right:4px;"></span>Lapsed</span>
        </div>
    </div>

    @php $tasks = $this->tasks(); @endphp

    <div class="gqs-panel">
        <div class="gqs-panel-body" style="padding:8px;overflow-x:auto;">
            <div id="gqs-gantt-data" data-tasks='@json($tasks)' data-mode="{{ $viewMode }}" style="display:none;"></div>
            @if(count($tasks))
                <svg id="gqs-gantt"></svg>
            @else
                <div class="gqs-empty" style="padding:30px;">No qualifications match. Adjust the filters.</div>
            @endif
        </div>
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
                            bar_height: 20, padding: 16, readonly: true, popup_trigger: 'mouseover',
                        });
                    } catch (e) { console.error('Gantt render failed', e); }
                }
                function boot() { render(); }
                if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
                // re-render after each Livewire DOM morph (filter/zoom changes)
                document.addEventListener('livewire:initialized', () => {
                    if (window.Livewire) {
                        Livewire.hook('morph.updated', () => setTimeout(render, 50));
                    }
                });
            })();
        </script>
    </div>

    <style>
        #gqs-gantt .bar-wrapper.gantt-qualified .bar{fill:#2E7D5B !important;}
        #gqs-gantt .bar-wrapper.gantt-qualified .bar-progress{fill:#206048 !important;}
        #gqs-gantt .bar-wrapper.gantt-active .bar{fill:#C98AA6 !important;}
        #gqs-gantt .bar-wrapper.gantt-active .bar-progress{fill:#A4123F !important;}
        #gqs-gantt .bar-wrapper.gantt-lapsed .bar{fill:#E59AA4 !important;}
        #gqs-gantt .bar-wrapper.gantt-lapsed .bar-progress{fill:#C8102E !important;}
        #gqs-gantt .grid-header{fill:#F4F4F6;}
        #gqs-gantt .bar-label{fill:#1A1A1F;font-size:11px;}
        #gqs-gantt .bar-label.big{fill:#1A1A1F;}
    </style>
</x-filament-panels::page>
