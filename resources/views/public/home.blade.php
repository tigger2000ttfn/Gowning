@extends('public.layout')
@section('title', 'MATC Gowning Qualification')
@section('content')
    <section class="hero">
        <div class="hero-inner hero-split">
            <img src="{{ asset('images/gowned-figure.svg') }}" alt="" class="hero-figure">
            <div class="hero-text">
                <h1>Cleanroom Gowning <span class="accent">Qualification</span></h1>
                <p>Schedule gowning classes, sign up for qualification run slots, and track your status across the full qualification cycle.</p>
                <div class="cta">
                    <a href="{{ route('public.register') }}" class="primary">Request Access</a>
                    <a href="#browse" class="ghost">Browse Sessions</a>
                </div>
            </div>
        </div>
    </section>

    <div class="strip">
        <div class="strip-item"><span class="num">{{ $sessions->count() }}</span><span class="lbl">Open Class Sessions</span></div>
        <div class="strip-item"><span class="num">{{ $runSlots->count() }}</span><span class="lbl">Open Run Slots</span></div>
        <div class="strip-item"><span class="num">3</span><span class="lbl">Runs For Initial Qual</span></div>
        <div class="strip-item"><span class="num">12</span><span class="lbl">Month Requal Cycle</span></div>
    </div>

    <section class="section" id="browse">
        <div class="tabs" x-data="{ tab: 'classes' }">
            <div class="tabbar">
                <button :class="{ 'active': tab==='classes' }" @click="tab='classes'">
                    <span class="dot dot-magenta"></span> Gowning Classes
                    <span class="count">{{ $sessions->count() }}</span>
                </button>
                <button :class="{ 'active': tab==='runs' }" @click="tab='runs'">
                    <span class="dot dot-gold"></span> Qualification Run Slots
                    <span class="count">{{ $runSlots->count() }}</span>
                </button>
            </div>

            {{-- CLASSES --}}
            <div x-show="tab==='classes'" x-cloak>
                <p class="tab-sub">Completing a gowning class is the prerequisite for initial qualification runs.</p>
                @if ($sessions->isEmpty())
                    <div class="empty">No Open Class Sessions Right Now</div>
                @else
                    <div class="grid">
                        @foreach ($sessions as $session)
                            <div class="ccard">
                                <div class="ccard-date">
                                    <span class="mo">{{ $session->session_date->format('M') }}</span>
                                    <span class="dy">{{ $session->session_date->format('j') }}</span>
                                </div>
                                <div class="ccard-body">
                                    <h3>{{ \Illuminate\Support\Str::title($session->trainingClass->name) }}</h3>
                                    <div class="ccard-meta">
                                        @if($session->start_time){{ \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') }} &middot; @endif
                                        {{ $session->location ?? 'TBD' }}
                                    </div>
                                    <div class="ccard-foot">
                                        <span class="seats">{{ $session->seatsLeft() }} Seats</span>
                                        <a class="signup" href="{{ route('public.signup', $session) }}">Sign Up &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- RUN SLOTS --}}
            <div x-show="tab==='runs'" x-cloak>
                <p class="tab-sub">Request a cleanroom run slot published by QC Micro. Requests are approved before the run.</p>
                @if ($runSlots->isEmpty())
                    <div class="empty">No Open Run Slots Right Now</div>
                @else
                    <div class="grid">
                        @foreach ($runSlots as $slot)
                            <div class="ccard ccard-run">
                                <div class="ccard-date">
                                    <span class="mo">{{ $slot->slot_date->format('M') }}</span>
                                    <span class="dy">{{ $slot->slot_date->format('j') }}</span>
                                </div>
                                <div class="ccard-body">
                                    <h3>{{ \Illuminate\Support\Str::title($slot->cleanroom) }}</h3>
                                    <div class="ccard-meta">
                                        @if($slot->start_time){{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} &middot; @endif
                                        Cleanroom Run
                                    </div>
                                    <div class="ccard-foot">
                                        <span class="seats">{{ max(0, $slot->capacity - $slot->approvedCount()) }} Spots</span>
                                        <a class="signup signup-gold" href="{{ route('public.run.signup', $slot) }}">Request &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
