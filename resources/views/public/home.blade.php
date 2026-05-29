@extends('public.layout')
@section('title', 'MATC Gowning Qualification')
@section('content')
    <section class="hero">
        <div class="hero-inner">
            <img src="{{ asset('images/flying-star.svg') }}" alt="" class="hero-star">
            <h1>Cleanroom Gowning <span class="accent">Qualification</span></h1>
            <p>Schedule and track the full gowning qualification cycle at the Manufacturing
               Technology Center: gowning classes, cleanroom qualification runs, and annual
               requalification &mdash; all in one place.</p>
            <div class="cta">
                <a href="#classes" class="primary">Browse classes</a>
                <a href="{{ route('public.register') }}" class="ghost">Request access</a>
            </div>
        </div>
    </section>

    <section class="section" id="classes">
        <h2>Upcoming gowning classes</h2>
        <p class="sub">Sign up for an available session. Completing a gowning class is the
           prerequisite for initial qualification runs.</p>

        @if ($sessions->isEmpty())
            <div class="empty">No upcoming class sessions are open right now. Check back soon.</div>
        @else
            <div class="grid">
                @foreach ($sessions as $session)
                    <div class="card">
                        <h3>{{ $session->trainingClass->name }}</h3>
                        <div class="meta">{{ $session->session_date->format('l, M j, Y') }}
                            @if($session->start_time) &middot; {{ \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') }} @endif
                        </div>
                        @if($session->location)<div class="meta">{{ $session->location }}</div>@endif
                        @if($session->instructor)<div class="meta">Instructor: {{ $session->instructor }}</div>@endif
                        <div class="seats">{{ $session->seatsLeft() }} seats remaining</div>
                        <a class="signup" href="{{ route('public.signup', $session) }}">Sign up</a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
