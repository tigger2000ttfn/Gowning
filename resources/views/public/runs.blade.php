@extends('public.layout')
@section('title', 'Qualification Run Slots')
@section('content')
    <section class="pagehead">
        <div class="pagehead-inner">
            <h1><span class="dot dot-gold"></span> Qualification Run Slots</h1>
            <p>Request a cleanroom run slot published by QC Micro. Requests are approved before the run.</p>
        </div>
    </section>
    <section class="section">
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
    </section>
@endsection
