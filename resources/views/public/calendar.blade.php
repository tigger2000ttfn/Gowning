@extends('public.layout')
@section('title', 'Schedule Calendar')
@section('content')
    <section class="pagehead">
        <div class="pagehead-inner">
            <h1><img src="{{ asset('images/title-flask.svg') }}" alt="" class="title-icon"> Schedule Calendar</h1>
            <p>All gowning classes and qualification run slots in one place. Switch between month, week, and year. Click an event to sign up.</p>
        </div>
    </section>

    <section class="section">
        <div class="cal-legend">
            <span><i style="background:#A4123F"></i> Gowning Classes</span>
            <span><i style="background:#C79A2E"></i> Qualification Run Slots</span>
        </div>
        <div id="calendar"></div>
    </section>

    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <style>
        .cal-legend{display:flex;gap:22px;margin-bottom:16px;font-size:14px;font-weight:600;color:var(--ink);}
        .cal-legend i{display:inline-block;width:13px;height:13px;border-radius:3px;margin-right:6px;vertical-align:middle;}
        #calendar{background:#fff;border:1px solid #D8D8DE;border-radius:14px;padding:18px;box-shadow:0 4px 14px rgba(0,0,0,.06);}
        .fc .fc-button-primary{background:var(--magenta);border-color:var(--magenta);text-transform:capitalize;}
        .fc .fc-button-primary:hover{background:#850F33;border-color:#850F33;}
        .fc .fc-button-primary:not(:disabled).fc-button-active{background:#850F33;border-color:#850F33;}
        .fc .fc-toolbar-title{font-size:20px;font-weight:700;color:var(--ink);}
        .fc-event{cursor:pointer;font-weight:600;border:none;}
        .fc-daygrid-event{padding:2px 5px;}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('calendar');
            var cal = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'multiMonthYear,dayGridMonth,timeGridWeek'
                },
                buttonText: { year: 'Year', month: 'Month', week: 'Week', today: 'Today' },
                events: '{{ route('public.calendar.events') }}',
                eventDidMount: function (info) {
                    var p = info.event.extendedProps;
                    if (p && p.type) {
                        info.el.title = info.event.title + ' — ' + p.type +
                            (p.location ? ' @ ' + p.location : '') +
                            (p.seats != null ? ' (' + p.seats + ' open)' : '');
                    }
                }
            });
            cal.render();
        });
    </script>
@endsection
