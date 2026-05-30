<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GQS Document' }}</title>
    <style>
        :root { --magenta:#A4123F; --charcoal:#1C1C21; --gold:#E0B83C; --ink:#1A1A1F; --dim:#6A6A72; --line:#E2E2E6; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--ink); margin: 0; background: #f4f4f6; }
        .sheet { max-width: 1300px; margin: 0 auto; background: #fff; padding: 28px 36px 44px; min-height: 100vh; }
        .hd { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 3px solid var(--magenta); padding-bottom: 14px; margin-bottom: 6px; }
        .hd-brand { display: flex; align-items: center; gap: 14px; }
        .hd-logo { height: 34px; width: auto; }
        .hd-title { font-size: 19px; font-weight: 800; color: var(--ink); letter-spacing: -.01em; }
        .hd-sub { font-size: 12px; color: var(--dim); margin-top: 1px; }
        .hd-meta { text-align: right; font-size: 11px; color: var(--dim); }
        .doc-title { font-size: 15px; font-weight: 700; color: var(--magenta); margin: 16px 0 4px; }
        .sec { margin-top: 22px; page-break-inside: avoid; }
        .sec-h { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #fff; background: var(--magenta); padding: 7px 12px; border-radius: 6px 6px 0 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th { text-align: left; padding: 7px 12px; background: var(--charcoal); color: #fff; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 7px 12px; border-bottom: 1px solid var(--line); }
        tr:nth-child(even) td { background: #faf7f8; }
        .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 20px; }
        .pill-red { background: #FBE3E7; color: #C8102E; } .pill-green { background: #DDF3E9; color: #1E7A52; }
        .pill-gold { background: #FBF3DC; color: #8A6D0B; }
        .stats { display: flex; gap: 14px; margin: 14px 0; }
        .stat { flex: 1; border: 1px solid var(--line); border-top: 3px solid var(--magenta); border-radius: 8px; padding: 12px 14px; }
        .stat .n { font-size: 24px; font-weight: 800; } .stat .l { font-size: 11px; color: var(--dim); }
        .sign { margin-top: 30px; display: flex; gap: 40px; page-break-inside: avoid; }
        .sign-box { flex: 1; } .sign-line { border-bottom: 1px solid var(--ink); height: 34px; } .sign-lbl { font-size: 10.5px; color: var(--dim); margin-top: 4px; }
        .ftr { margin-top: 34px; border-top: 1px solid var(--line); padding-top: 8px; font-size: 10px; color: var(--dim); display: flex; justify-content: space-between; }
        .toolbar { max-width: 1000px; margin: 14px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
        .btn { font-size: 13px; font-weight: 700; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-print { background: var(--magenta); color: #fff; } .btn-close { background: #e6e6ea; color: var(--ink); }
        .empty { padding: 14px 12px; color: var(--dim); font-size: 12.5px; }
        @media print {
            body { background: #fff; } .toolbar { display: none; } .sheet { box-shadow: none; max-width: none; padding: 0; }
            @page { margin: 1.1cm; size: landscape; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-print" onclick="window.print()">Print / Save PDF</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>
    <div class="sheet">
        <div class="hd">
            <div class="hd-brand">
                <img class="hd-logo" src="{{ asset('images/astellas-logo.png') }}" alt="Astellas">
                <div>
                    <div class="hd-title">Gowning Qualification</div>
                    <div class="hd-sub">{{ $org ?? 'MATC, Astellas' }} · {{ $site ?? '' }}</div>
                </div>
            </div>
            <div class="hd-meta">
                Generated {{ ($generated ?? now()->setTimezone('America/New_York'))->format('M j, Y g:i A') }}<br>
                21 CFR Part 11 Controlled Record
            </div>
        </div>
        @yield('body')
        <div class="ftr">
            <span>MATC Gowning Qualification System</span>
            <span>This is a controlled printout. Verify currency against the live system.</span>
        </div>
    </div>
    <script>
        // auto-open print dialog shortly after load (user can cancel)
        window.addEventListener('load', () => setTimeout(() => window.print(), 350));
    </script>
</body>
</html>
