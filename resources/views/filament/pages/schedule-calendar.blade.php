<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Schedule Calendar', 'subtitle' => 'Run days, class sessions, and qualification due dates in one view.', 'icon' => 'heroicon-o-calendar'])

    {{-- FullCalendar via CDN (same library/pattern already used by the public calendar) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">

    <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px;font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#A4123F;margin-right:4px;"></span>Run Days</span>
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#2E7D5B;margin-right:4px;"></span>Classes</span>
        <span><span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:#C79A2E;margin-right:4px;"></span>Due Dates</span>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-body" style="padding:16px;">
            <div id="gqs-cal-data" data-events='@json($this->events())' style="display:none;"></div>
            <div id="gqs-calendar"></div>
        </div>
    </div>

    <div wire:ignore>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script>
            (function () {
                let cal = null;
                function render() {
                    const el = document.getElementById('gqs-calendar');
                    const data = document.getElementById('gqs-cal-data');
                    if (!el || !data || !window.FullCalendar) return;
                    let events = [];
                    try { events = JSON.parse(data.getAttribute('data-events') || '[]'); } catch (e) {}
                    if (cal) { cal.destroy(); cal = null; }
                    cal = new FullCalendar.Calendar(el, {
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
                        events: events,
                    });
                    cal.render();
                }
                function boot() { render(); }
                if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
                document.addEventListener('livewire:initialized', () => {
                    if (window.Livewire) Livewire.hook('morph.updated', () => setTimeout(render, 50));
                });
            })();
        </script>
    </div>

    <style>
        #gqs-calendar .fc{font-size:13px;}
        #gqs-calendar .fc-toolbar-title{font-size:18px;font-weight:800;color:var(--gqs-text,#1A1A1F);}
        #gqs-calendar .fc-button-primary{background:#A4123F;border-color:#A4123F;}
        #gqs-calendar .fc-button-primary:hover{background:#850F33;border-color:#850F33;}
        #gqs-calendar .fc-button-primary:not(:disabled).fc-button-active{background:#850F33;border-color:#850F33;}
        .dark #gqs-calendar .fc-toolbar-title{color:#ECECF0;}
    </style>
</x-filament-panels::page>
