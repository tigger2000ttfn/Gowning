@extends('public.layout')
@section('title', 'Sign up — ' . $session->trainingClass->name)
@section('content')
    <div class="formwrap">
        <a href="{{ route('public.home') }}" class="backlink">&larr; Back to classes</a>
        <div class="formcard">
            <h2>{{ $session->trainingClass->name }}</h2>
            <p class="sub">{{ $session->session_date->format('l, M j, Y') }}
                @if($session->location) &middot; {{ $session->location }} @endif
                &middot; {{ $session->seatsLeft() }} seats left</p>
            <form method="POST" action="{{ route('public.signup.store', $session) }}">
                @csrf
                @include('public.partials.person-lookup-fields', ['people' => $people])
                <button class="submit" type="submit">Confirm Sign-Up</button>
            </form>
        </div>
    </div>
@endsection
