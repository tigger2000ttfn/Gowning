@extends('public.layout')
@section('title', 'MATC Gowning Qualification')
@section('content')
    <section class="hero">
        <div class="stars"><i class="" style="top:32.5%;left:16.3%;width:3px;height:3px;animation-delay:0.22s;animation-duration:3.43s;"></i><i class="" style="top:55.3%;left:88.4%;width:2px;height:2px;animation-delay:0.11s;animation-duration:3.2s;"></i><i class="" style="top:25.2%;left:54.3%;width:2px;height:2px;animation-delay:2.48s;animation-duration:2.48s;"></i><i class="" style="top:59.5%;left:57.4%;width:2px;height:2px;animation-delay:1.73s;animation-duration:3.11s;"></i><i class="g" style="top:8.1%;left:83.6%;width:4px;height:4px;animation-delay:1.26s;animation-duration:3.44s;"></i><i class="" style="top:31.1%;left:79.5%;width:2px;height:2px;animation-delay:0.31s;animation-duration:3.51s;"></i><i class="" style="top:36.8%;left:54.0%;width:3px;height:3px;animation-delay:1.69s;animation-duration:3.62s;"></i><i class="g" style="top:63.9%;left:42.6%;width:3px;height:3px;animation-delay:1.4s;animation-duration:4.32s;"></i><i class="" style="top:30.4%;left:77.5%;width:3px;height:3px;animation-delay:0.25s;animation-duration:2.89s;"></i><i class="g" style="top:81.0%;left:71.3%;width:4px;height:4px;animation-delay:1.83s;animation-duration:2.37s;"></i><i class="" style="top:40.8%;left:73.9%;width:2px;height:2px;animation-delay:2.8s;animation-duration:3.17s;"></i><i class="g" style="top:71.3%;left:56.4%;width:3px;height:3px;animation-delay:1.02s;animation-duration:3.01s;"></i><i class="" style="top:55.0%;left:45.3%;width:2px;height:2px;animation-delay:2.83s;animation-duration:3.29s;"></i><i class="p" style="top:9.3%;left:68.6%;width:3px;height:3px;animation-delay:0.85s;animation-duration:3.09s;"></i><i class="" style="top:6.0%;left:45.9%;width:2px;height:2px;animation-delay:1.83s;animation-duration:3.34s;"></i><i class="" style="top:71.6%;left:14.3%;width:3px;height:3px;animation-delay:1.19s;animation-duration:4.31s;"></i><i class="g" style="top:11.1%;left:44.7%;width:4px;height:4px;animation-delay:2.65s;animation-duration:4.08s;"></i><i class="g" style="top:28.5%;left:41.5%;width:2px;height:2px;animation-delay:2.05s;animation-duration:3.08s;"></i><i class="" style="top:17.3%;left:18.7%;width:4px;height:4px;animation-delay:1.98s;animation-duration:2.23s;"></i><i class="" style="top:20.0%;left:28.8%;width:4px;height:4px;animation-delay:1.26s;animation-duration:3.05s;"></i><i class="" style="top:32.0%;left:13.9%;width:4px;height:4px;animation-delay:1.37s;animation-duration:4.2s;"></i><i class="" style="top:38.5%;left:39.9%;width:2px;height:2px;animation-delay:1.44s;animation-duration:3.12s;"></i><i class="" style="top:9.9%;left:21.8%;width:2px;height:2px;animation-delay:0.33s;animation-duration:3.58s;"></i><i class="" style="top:4.0%;left:16.4%;width:2px;height:2px;animation-delay:2.85s;animation-duration:3.61s;"></i><i class="" style="top:80.9%;left:60.3%;width:4px;height:4px;animation-delay:1.9s;animation-duration:4.4s;"></i><i class="p" style="top:36.0%;left:13.7%;width:3px;height:3px;animation-delay:2.98s;animation-duration:3.27s;"></i><i class="g" style="top:31.4%;left:15.7%;width:2px;height:2px;animation-delay:2.22s;animation-duration:3.3s;"></i><i class="g" style="top:49.4%;left:21.5%;width:2px;height:2px;animation-delay:0.44s;animation-duration:3.45s;"></i><i class="" style="top:70.7%;left:30.3%;width:3px;height:3px;animation-delay:2.09s;animation-duration:2.8s;"></i><i class="" style="top:83.9%;left:35.8%;width:3px;height:3px;animation-delay:1.6s;animation-duration:3.99s;"></i><i class="" style="top:60.0%;left:60.3%;width:2px;height:2px;animation-delay:2.42s;animation-duration:4.08s;"></i><i class="" style="top:21.6%;left:48.8%;width:3px;height:3px;animation-delay:2.97s;animation-duration:4.02s;"></i><i class="g" style="top:26.8%;left:67.8%;width:3px;height:3px;animation-delay:1.34s;animation-duration:4.36s;"></i><i class="" style="top:88.0%;left:36.6%;width:3px;height:3px;animation-delay:0.31s;animation-duration:3.28s;"></i><i class="" style="top:22.0%;left:61.3%;width:2px;height:2px;animation-delay:1.44s;animation-duration:3.7s;"></i><i class="p" style="top:77.4%;left:13.4%;width:3px;height:3px;animation-delay:2.35s;animation-duration:3.93s;"></i><i class="g" style="top:82.2%;left:43.2%;width:3px;height:3px;animation-delay:0.26s;animation-duration:4.38s;"></i><i class="" style="top:44.8%;left:72.6%;width:2px;height:2px;animation-delay:2.17s;animation-duration:2.59s;"></i></div>
        <div class="hero-inner hero-split">
            <img src="{{ asset('images/hero-icon.svg') }}" alt="" class="hero-figure">
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
                    <svg class="tab-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    Gowning Classes
                    <span class="count">{{ $sessions->count() }}</span>
                </button>
                <button :class="{ 'active': tab==='runs' }" @click="tab='runs'">
                    <svg class="tab-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v6.5L5 18a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-8.5V3"/><path d="M7.5 14h9"/></svg>
                    Qualification Run Slots
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
                                    <h3><svg class="ccard-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>{{ $session->trainingClass->name }}</h3>
                                    <div class="ccard-meta">
                                        @if($session->start_time){{ \Illuminate\Support\Carbon::parse($session->start_time)->format('H:i') }} &middot; @endif
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
                <p class="tab-sub">Book a cleanroom qualification run published by QC Micro. Bookings are approved before the run.</p>
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
                                    <h3><svg class="ccard-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v6.5L5 18a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-8.5V3"/><path d="M7.5 14h9"/></svg>{{ $slot->cleanroom }}</h3>
                                    <div class="ccard-meta">
                                        @if($slot->start_time){{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }} &middot; @endif
                                        Cleanroom Run
                                    </div>
                                    <div class="ccard-foot">
                                        <span class="seats">{{ max(0, $slot->capacity - $slot->approvedCount()) }} Spots</span>
                                        <a class="signup signup-gold" href="{{ route('public.run.signup', $slot) }}">Book Run &rarr;</a>
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
