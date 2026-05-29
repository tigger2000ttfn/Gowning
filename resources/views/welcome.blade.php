<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MATC Gowning Qualification System</title>
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#0e7490; --bg:#f8fafc; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
               background:var(--bg); color:var(--ink); min-height:100vh;
               display:flex; align-items:center; justify-content:center; padding:24px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px;
                max-width:560px; width:100%; padding:40px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .tag { display:inline-block; font-size:12px; letter-spacing:.08em; text-transform:uppercase;
               color:var(--brand); font-weight:600; }
        h1 { font-size:26px; margin:8px 0 6px; }
        p { color:var(--muted); line-height:1.6; margin:0 0 24px; }
        a.btn { display:inline-block; background:var(--brand); color:#fff; text-decoration:none;
                padding:12px 20px; border-radius:10px; font-weight:600; }
        ul { color:var(--muted); line-height:1.8; padding-left:18px; margin:0 0 24px; }
        .foot { margin-top:28px; padding-top:16px; border-top:1px solid var(--line);
                font-size:12px; color:var(--muted); }
    </style>
</head>
<body>
    <div class="card">
        <span class="tag">MATC &middot; QC Micro</span>
        <h1>Gowning Qualification System</h1>
        <p>Centralized tracking of the cleanroom gowning qualification cycle:
           run scheduling, qualification status and due dates, class-completion import,
           notifications, and reporting.</p>
        <ul>
            <li>Initial qualification: 3 successful cleanroom runs</li>
            <li>Annual requalification: 1 run on or before the due date</li>
            <li>Lapsed (past due): 3 runs required again</li>
        </ul>
        <a class="btn" href="{{ url('/admin') }}">Open the admin panel &rarr;</a>
        <div class="foot">Built with 21 CFR Part 11 controls &middot; audit trail enabled</div>
    </div>
</body>
</html>
