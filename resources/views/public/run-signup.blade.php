@extends('public.layout')
@section('title', 'Request run slot')
@section('content')
    <div class="formwrap">
        <a href="{{ route('public.home') }}" class="backlink">&larr; Back</a>
        <div class="formcard">
            <h2>Qualification Run Slot</h2>
            <p class="sub">{{ $slot->slot_date->format('l, M j, Y') }}
                @if($slot->start_time) &middot; {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} @endif
                &middot; {{ $slot->cleanroom }}</p>
            <form method="POST" action="{{ route('public.run.signup.store', $slot) }}">
                @csrf
                <label>Employee ID</label>
                <input name="employee_id" value="{{ old('employee_id') }}" required placeholder="e.g. EMP1001">
                @error('employee_id')<div class="err">{{ $message }}</div>@enderror
                <button class="submit" type="submit">Request this slot</button>
            </form>
        </div>
    </div>
@endsection
