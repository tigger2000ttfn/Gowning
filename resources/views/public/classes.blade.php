@extends('public.layout')
@section('title', 'Gowning Classes')
@section('content')
    <section class="pagehead">
        <div class="pagehead-inner">
            <h1><img src="{{ asset('images/title-graduation-cap.svg') }}" alt="" class="title-icon"> Gowning Classes</h1>
            <p>Completing a gowning class is the prerequisite for initial qualification runs.</p>
        </div>
    </section>
    <section class="section">
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
    </section>
@endsection
