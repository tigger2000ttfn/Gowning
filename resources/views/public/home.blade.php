@extends('public.layout')
@section('title', 'MATC Gowning Qualification')
@section('content')
    <section class="hero">
        <div class="hero-inner">
            <img src="{{ asset('images/flying-star.svg') }}" alt="" class="hero-star">
            <h1>Cleanroom Gowning <span class="accent">Qualification</span></h1>
            <p>Schedule gowning classes, sign up for qualification run slots, and track your
               status through the full qualification cycle &mdash; all in one place.</p>
            <div class="cta">
                <a href="#classes" class="primary">Gowning classes</a>
                <a href="#runs" class="ghost">Run slots</a>
            </div>
        </div>
    </section>

    {{-- Two-column quick stats strip --}}
    <div class="strip">
        <div class="strip-item"><span class="num">{{ $sessions->count() }}</span><span class="lbl">Open class sessions</span></div>
        <div class="strip-item"><span class="num">{{ $runSlots->count() }}</span><span class="lbl">Open run slots</span></div>
        <div class="strip-item"><span class="num">3</span><span class="lbl">Runs for initial qual</span></div>
        <div class="strip-item"><span class="num">12</span><span class="lbl">Month requal cycle</span></div>
    </div>

    {{-- GOWNING CLASSES --}}
    <section class="section" id="classes">
        <div class="sec-head">
            <h2><span class="dot dot-magenta"></span> Gowning Classes</h2>
            <p class="sub">Completing a gowning class is the prerequisite for initial qualification runs.</p>
        </div>
        @if ($sessions->isEmpty())
            <div class="empty">No open class sessions right now.</div>
        @else
            <div class="grid">
                @foreach ($sessions as $session)
                    <div class="ccard">
                        <div class="ccard-date">
                            <span class="mo">{{ $session->session_date->format('M') }}</span>
                            <span class="dy">{{ $session->session_date->format('j') }}</span>
                        </div>
                        <div class="ccard-body">
                            <h3>{{ $session->trainingClass->name }}</h3>
                            <div class="ccard-meta">
                                @if($session->start_time){{ \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') }} &middot; @endif
                                {{ $session->location ?? 'TBD' }}
                            </div>
                            <div class="ccard-foot">
                                <span class="seats">{{ $session->seatsLeft() }} seats</span>
                                <a class="signup" href="{{ route('public.signup', $session) }}">Sign up &rarr;</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- QUALIFICATION RUN SLOTS --}}
    <section class="section" id="runs">
        <div class="sec-head">
            <h2><span class="dot dot-gold"></span> Qualification Run Slots</h2>
            <p class="sub">Request a cleanroom run slot published by QC Micro. Requests are approved before the run.</p>
        </div>
        @if ($runSlots->isEmpty())
            <div class="empty">No open run slots right now.</div>
        @else
            <div class="grid">
                @foreach ($runSlots as $slot)
                    <div class="ccard ccard-run">
                        <div class="ccard-date">
                            <span class="mo">{{ $slot->slot_date->format('M') }}</span>
                            <span class="dy">{{ $slot->slot_date->format('j') }}</span>
                        </div>
                        <div class="ccard-body">
                            <h3>{{ $slot->cleanroom }}</h3>
                            <div class="ccard-meta">
                                @if($slot->start_time){{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} &middot; @endif
                                Cleanroom run
                            </div>
                            <div class="ccard-foot">
                                <span class="seats">{{ max(0, $slot->capacity - $slot->approvedCount()) }} spots</span>
                                <a class="signup signup-gold" href="{{ route('public.run.signup', $slot) }}">Request &rarr;</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
