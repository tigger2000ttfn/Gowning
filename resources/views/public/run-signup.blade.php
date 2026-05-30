@extends('public.layout')
@section('title', 'Book Run')
@section('content')
    <div class="formwrap">
        <a href="{{ route('public.home') }}" class="backlink">&larr; Back</a>
        <div class="formcard">
            <h2>Book Run</h2>
            <p class="sub">{{ $slot->slot_date->format('l, d M Y') }}
                @if($slot->start_time) &middot; {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }} @endif
                &middot; {{ $slot->cleanroom }}</p>
            <form method="POST" action="{{ route('public.run.signup.store', $slot) }}">
                @csrf
                @include('public.partials.person-lookup-fields', ['people' => $people])
                <button class="submit" type="submit">Book Run</button>
            </form>
        </div>
    </div>
@endsection
