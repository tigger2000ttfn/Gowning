<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'GQS Document' }}</title>
    <style>
        @page { margin: 1.1cm; }
        * { box-sizing: border-box; }
        body { font-family: "Helvetica", Arial, sans-serif; color: #1A1A1F; margin: 0; }
        .sheet { padding: 0; }
        .hd-tbl { width: 100%; border-collapse: collapse; border-bottom: 3px solid #A4123F; }
        .hd-tbl td { vertical-align: bottom; padding: 0 0 12px; }
        .hd-logo { height: 32px; width: auto; }
        .hd-title { font-size: 19px; font-weight: 800; color: #1A1A1F; }
        .hd-sub { font-size: 11px; color: #6A6A72; margin-top: 1px; }
        .hd-meta { text-align: right; font-size: 10px; color: #6A6A72; }
        .doc-title { font-size: 15px; font-weight: 700; color: #A4123F; margin: 16px 0 4px; }
        .sec { margin-top: 18px; page-break-inside: avoid; }
        .sec-h { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #fff; background: #A4123F; padding: 6px 10px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.data th { text-align: left; padding: 6px 9px; background: #1C1C21; color: #fff; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; }
        table.data td { padding: 6px 9px; border-bottom: 1px solid #E2E2E6; }
        table.data tr:nth-child(even) td { background: #faf7f8; }
        .pill { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
        .pill-red { background: #FBE3E7; color: #C8102E; } .pill-green { background: #DDF3E9; color: #1E7A52; }
        .pill-gold { background: #FBF3DC; color: #8A6D0B; }
        .stats-tbl { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 14px 0; }
        .stat { border: 1px solid #E2E2E6; border-top: 3px solid #A4123F; padding: 10px 12px; }
        .stat .n { font-size: 22px; font-weight: 800; } .stat .l { font-size: 10px; color: #6A6A72; }
        .sign-tbl { width: 100%; margin-top: 26px; page-break-inside: avoid; border-collapse: separate; border-spacing: 30px 0; }
        .sign-line { border-bottom: 1px solid #1A1A1F; height: 30px; } .sign-lbl { font-size: 10px; color: #6A6A72; margin-top: 4px; }
        .ftr { margin-top: 26px; border-top: 1px solid #E2E2E6; padding-top: 8px; font-size: 9px; color: #6A6A72; }
        .ftr td { font-size: 9px; color: #6A6A72; }
        .empty { padding: 12px 10px; color: #6A6A72; font-size: 11px; }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="hd-tbl">
            <tr>
                <td>
                    <table style="border-collapse:collapse;"><tr>
                        <td style="padding:0 12px 0 0;vertical-align:middle;"><img class="hd-logo" src="{{ public_path('images/astellas-logo.png') }}" alt="Astellas"></td>
                        <td style="vertical-align:middle;">
                            <div class="hd-title">Gowning Qualification</div>
                            <div class="hd-sub">{{ $org ?? 'MATC, Astellas' }}@if(!empty($site)) · {{ $site }}@endif</div>
                        </td>
                    </tr></table>
                </td>
                <td class="hd-meta">
                    Generated {{ ($generated ?? now()->setTimezone('America/New_York'))->format('d M Y H:i') }}<br>
                    21 CFR Part 11 Controlled Record
                </td>
            </tr>
        </table>

        @yield('body')

        <table class="ftr"><tr>
            <td>MATC Gowning Qualification System</td>
            <td style="text-align:right;">This is a controlled printout. Verify currency against the live system.</td>
        </tr></table>
    </div>
</body>
</html>
