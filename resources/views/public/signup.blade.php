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
                <label>Full name</label>
                <input name="name" value="{{ old('name') }}" required>
                @error('name')<div class="err">{{ $message }}</div>@enderror
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required>
                @error('email')<div class="err">{{ $message }}</div>@enderror
                <label>Employee ID (optional)</label>
                <input name="employee_id" value="{{ old('employee_id') }}">
                @error('employee_id')<div class="err">{{ $message }}</div>@enderror
                <button class="submit" type="submit">Confirm sign-up</button>
            </form>
        </div>
    </div>
@endsection
