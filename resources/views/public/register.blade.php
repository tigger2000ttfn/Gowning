@extends('public.layout')
@section('title', 'Request Access')
@section('content')
    <div class="formwrap">
        <a href="{{ route('public.home') }}" class="backlink">&larr; Back</a>
        <div class="formcard">
            <h2>Request Access</h2>
            <p class="sub">Enter your Employee ID first &mdash; if you are already on file, your details fill in automatically.
               Then choose a password. An administrator approves access before you can sign in.</p>
            <form method="POST" action="{{ route('public.register.store') }}">
                @csrf
                @include('public.partials.person-lookup-fields', ['people' => $people])

                <label>Password</label>
                <input name="password" type="password" required placeholder="At least 10 characters, letters and numbers">
                @error('password')<div class="err">{{ $message }}</div>@enderror

                <label>Confirm Password</label>
                <input name="password_confirmation" type="password" required>

                <button class="submit" type="submit">Request Access</button>
            </form>
        </div>
    </div>
@endsection
