{{--
  Personnel self-identification fields with cross-population.
  Expects $people = collection/array of ['f'=>first,'l'=>last,'e'=>email,'id'=>employee_id].
  Type in any field; an exact match on Employee ID, Email, or First+Last fills the others.
  Native <datalist> gives offline autocomplete (no CDN).
--}}
<label>First Name</label>
<input id="pf_first" name="first_name" value="{{ old('first_name') }}" list="dl_first" autocomplete="off" required>
@error('first_name')<div class="err">{{ $message }}</div>@enderror

<label>Last Name</label>
<input id="pf_last" name="last_name" value="{{ old('last_name') }}" list="dl_last" autocomplete="off" required>
@error('last_name')<div class="err">{{ $message }}</div>@enderror

<label>Email</label>
<input id="pf_email" name="email" type="email" value="{{ old('email') }}" list="dl_email" autocomplete="off" required>
@error('email')<div class="err">{{ $message }}</div>@enderror

<label>Employee ID</label>
<input id="pf_emp" name="employee_id" value="{{ old('employee_id') }}" list="dl_emp" autocomplete="off" required
       placeholder="Required">
@error('employee_id')<div class="err">{{ $message }}</div>@enderror

<p style="font-size:12.5px;color:var(--muted);margin-top:8px;">
    Start typing your name, email, or Employee ID to find yourself in the list. If you are not listed, type all four fields and QC Micro will verify.
</p>

@php
    $opts = collect($people)->filter(fn ($p) => trim(($p['l'] ?? '')) !== '' && ! str_starts_with((string) ($p['f'] ?? ''), '_'))->values();
@endphp
<datalist id="dl_first">@foreach($opts->pluck('f')->filter()->unique()->sort() as $v)<option value="{{ $v }}"></option>@endforeach</datalist>
<datalist id="dl_last">@foreach($opts->pluck('l')->filter()->unique()->sort() as $v)<option value="{{ $v }}"></option>@endforeach</datalist>
<datalist id="dl_email">@foreach($opts->pluck('e')->filter()->unique()->sort() as $v)<option value="{{ $v }}"></option>@endforeach</datalist>
<datalist id="dl_emp">@foreach($opts->filter(fn ($p) => ($p['id'] ?? '') !== '') as $p)<option value="{{ $p['id'] }}">{{ trim(($p['f'] ?? '') . ' ' . ($p['l'] ?? '')) }}</option>@endforeach</datalist>

<script>
(() => {
    const PEOPLE = @json($opts);
    const $ = id => document.getElementById(id);
    const first = $('pf_first'), last = $('pf_last'), email = $('pf_email'), emp = $('pf_emp');
    if (!first) return;
    const norm = s => (s || '').trim().toLowerCase();

    function findMatch() {
        const e = norm(emp.value), m = norm(email.value), f = norm(first.value), l = norm(last.value);
        // Strongest key first: Employee ID, then Email, then First+Last.
        if (e) { const hit = PEOPLE.filter(p => norm(p.id) === e); if (hit.length === 1) return hit[0]; }
        if (m) { const hit = PEOPLE.filter(p => norm(p.e) === m); if (hit.length === 1) return hit[0]; }
        if (f && l) { const hit = PEOPLE.filter(p => norm(p.f) === f && norm(p.l) === l); if (hit.length === 1) return hit[0]; }
        return null;
    }

    function fillFrom(p, skip) {
        if (skip !== 'pf_first' && p.f) first.value = p.f;
        if (skip !== 'pf_last'  && p.l) last.value = p.l;
        if (skip !== 'pf_email' && p.e) email.value = p.e;
        if (skip !== 'pf_emp'   && p.id) emp.value = p.id;
    }

    [first, last, email, emp].forEach(el => {
        const handler = () => { const p = findMatch(); if (p) fillFrom(p, el.id); };
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });
})();
</script>
