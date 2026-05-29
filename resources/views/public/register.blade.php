@extends('public.layout')
@section('title', 'Request access')
@section('content')
    <div class="formwrap">
        <a href="{{ route('public.home') }}" class="backlink">&larr; Back</a>
        <div class="formcard">
            <h2>Request access</h2>
            <p class="sub">Create an account to access the qualification system. An administrator
               approves access before you can sign in.</p>
            <form method="POST" action="{{ route('public.register.store') }}">
                @csrf
                <label>Full name</label>
                <input name="name" value="{{ old('name') }}" required>
                @error('name')<div class="err">{{ $message }}</div>@enderror
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required>
                @error('email')<div class="err">{{ $message }}</div>@enderror
                <label>Password</label>
                <input name="password" type="password" required>
                @error('password')<div class="err">{{ $message }}</div>@enderror
                <label>Confirm password</label>
                <input name="password_confirmation" type="password" required>
                <button class="submit" type="submit">Request access</button>
            </form>
        </div>
    </div>
@endsection
