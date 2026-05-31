@php
    /** @var \App\Models\Qualification $q */
    $q = $getRecord();
    $q->loadMissing('personnel', 'children');

    $statusEnum = $q->status instanceof \BackedEnum ? $q->status : \App\Enums\QualificationStatus::tryFrom((string) $q->status);
    $statusLabel = $statusEnum?->label() ?? \Illuminate\Support\Str::title(str_replace('_', ' ', (string) $q->status));
    $stageEnum = $q->workflow_stage instanceof \BackedEnum ? $q->workflow_stage : \App\Enums\WorkflowStage::tryFrom((string) $q->workflow_stage);
    $stageVal = $stageEnum?->value ?? (string) $q->workflow_stage;
    $stageLabel = $stageEnum ? \App\Models\WorkflowStatus::labelFor('run', $stageVal, $stageEnum->label()) : '—';

    $pastDue = $q->isPastDue();
    $isPipeline = in_array($stageVal, ['run_scheduled','run_performed','incubating','awaiting_results','results_released','qa_review'], true);

    // headline status pill (mirrors the board logic)
    if (($statusEnum?->value) === 'lapsed' || $pastDue) { $pillTxt = 'Lapsed Qual'; $pillBg = '#C8102E'; }
    elseif ($stageVal === 'qa_signoff') { $pillTxt = 'Qualified'; $pillBg = '#2E7D5B'; }
    elseif ($stageVal === 'failed') { $pillTxt = 'Failed'; $pillBg = '#C8102E'; }
    elseif (($statusEnum?->value) === 'qualified' && $q->qualified_date && ! $isPipeline) { $pillTxt = 'Qualified'; $pillBg = '#2E7D5B'; }
    elseif ($isPipeline || ($statusEnum?->value) === 'in_progress') { $pillTxt = 'In Progress'; $pillBg = '#C79A2E'; }
    else { $pillTxt = $statusLabel; $pillBg = '#6B6B73'; }

    // ordered run-pipeline stepper
    $steps = [
        'class_complete' => 'Class',
        'run_scheduled' => 'Scheduled',
        'run_performed' => 'Performed',
        'incubating' => 'Incubating',
        'awaiting_results' => 'Results',
        'results_released' => 'QCM Review',
        'qa_review' => 'QA Review',
        'qa_signoff' => 'QA Approved',
    ];
    $order = array_keys($steps);
    $curIdx = array_search($stageVal, $order, true);
    if ($curIdx === false) $curIdx = ($stageVal === 'class_pending') ? -1 : -1;

    // latest linked run for LIMS data
    $lrun = $q->runs()->whereNotNull('lims_worklist_id')->latest('id')->first();
    $incBadge = '—';
    if ($lrun) {
        if ($lrun->lims_inc2_end) { $incBadge = 'Complete'; }
        elseif ($lrun->lims_inc_due) {
            try { $d = \Illuminate\Support\Carbon::parse($lrun->lims_inc_due); $days = (int) ceil(now()->floatDiffInDays($d, false)); $incBadge = $days >= 0 ? $days.'d left' : abs($days).'d overdue'; } catch (\Throwable $e) { $incBadge = 'Incubating'; }
        } elseif ($lrun->lims_inc1_start) { $incBadge = 'Incubating'; }
    }

    $recentRuns = $q->runs()->orderByDesc('run_date')->orderByDesc('id')->limit(8)->get();
@endphp

<div class="ar-detail">
    <style>
        .ar-detail{--ar-line:var(--gqs-border,#E5E5EA);}
        .ar-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;background:linear-gradient(135deg,#1C1C21,#34343D);border-radius:14px;padding:18px 22px;color:#fff;flex-wrap:wrap;}
        .ar-hero h2{font-size:22px;font-weight:800;margin:0;line-height:1.1;}
        .ar-hero .sub{font-size:12.5px;color:rgba(255,255,255,.8);margin-top:3px;}
        .ar-pill{display:inline-flex;align-items:center;padding:7px 16px;border-radius:999px;font-weight:800;font-size:13px;color:#fff;white-space:nowrap;}
        .ar-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:16px;}
        .ar-tile{background:var(--gqs-surface,#fff);border:1px solid var(--ar-line);border-radius:11px;padding:13px 15px;}
        .ar-tile .l{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);}
        .ar-tile .v{font-size:17px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-top:3px;line-height:1.2;}
        .ar-tile .v.sm{font-size:14px;}
        .ar-tile.danger{border-color:#F2B8C0;background:#FCEEF0;} .ar-tile.danger .v{color:#C8102E;}
        .ar-stepper{display:flex;align-items:center;gap:0;margin-top:18px;overflow-x:auto;padding-bottom:4px;}
        .ar-step{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;min-width:74px;position:relative;}
        .ar-step .dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;border:2px solid var(--ar-line);background:var(--gqs-surface,#fff);color:var(--gqs-text-dim,#9A9AA4);z-index:1;}
        .ar-step.done .dot{background:#2E7D5B;border-color:#2E7D5B;color:#fff;}
        .ar-step.current .dot{background:#1F6FB2;border-color:#1F6FB2;color:#fff;box-shadow:0 0 0 4px rgba(31,111,178,.18);}
        .ar-step .lbl{font-size:10.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);text-align:center;white-space:nowrap;}
        .ar-step.current .lbl{color:#1F6FB2;font-weight:800;}
        .ar-step .bar{position:absolute;top:13px;left:50%;width:100%;height:2px;background:var(--ar-line);z-index:0;}
        .ar-step.done .bar{background:#2E7D5B;}
        .ar-step:last-child .bar{display:none;}
        .ar-sec{margin-top:22px;}
        .ar-sec h3{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--gqs-text-dim,#6A6A72);margin:0 0 9px;}
        .ar-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;}
        .ar-f .l{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);}
        .ar-f .v{font-weight:600;color:var(--gqs-text,#1A1A1F);margin-top:2px;font-size:13.5px;}
        .ar-chip{display:inline-block;padding:2px 9px;border-radius:6px;font-size:12px;font-weight:700;}
        .ar-tbl{width:100%;border-collapse:collapse;margin-top:4px;}
        .ar-tbl th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);padding:7px 10px;border-bottom:1px solid var(--ar-line);}
        .ar-tbl td{padding:8px 10px;border-bottom:1px solid var(--ar-line);font-size:13px;color:var(--gqs-text,#1A1A1F);}
    </style>

    <div class="ar-hero">
        <div style="min-width:0;">
            <h2>{{ $q->personnel?->full_name ?? 'Qualification' }}</h2>
            <div class="sub">{{ $q->personnel?->employee_id ?: 'No ID' }}@if($q->personnel?->job_title) · {{ $q->personnel->job_title }}@endif@if($q->personnel?->department) · {{ $q->personnel->department }}@endif</div>
            <div class="sub" style="margin-top:5px;">{{ $q->sessionLabel() }}@if(($q->cycle_number ?: 1) > 1) · Cycle {{ $q->cycle_number }}@endif@if($q->superseded_at) (superseded){{-- --}}@endif</div>
        </div>
        <span class="ar-pill" style="background:{{ $pillBg }};">{{ $pillTxt }}</span>
    </div>

    <div class="ar-tiles">
        <div class="ar-tile"><div class="l">Stage</div><div class="v sm">{{ $stageLabel }}</div></div>
        <div class="ar-tile"><div class="l">Passes This Cycle</div><div class="v">{{ (int) $q->runs_completed }} / {{ (int) $q->runs_required }}</div></div>
        <div class="ar-tile {{ $pastDue ? 'danger' : '' }}"><div class="l">Due</div><div class="v sm">{{ $q->due_date?->gmp() ?? '—' }}</div></div>
        <div class="ar-tile"><div class="l">Last Qualified</div><div class="v sm">{{ $q->qualified_date?->gmp() ?? '—' }}</div></div>
    </div>

    {{-- Pipeline stepper --}}
    <div class="ar-stepper">
        @foreach($steps as $sk => $slabel)
            @php $i = $loop->index; $cls = $i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : ''); @endphp
            <div class="ar-step {{ $cls }}">
                <span class="bar"></span>
                <span class="dot">@if($i < $curIdx)&check;@else{{ $i + 1 }}@endif</span>
                <span class="lbl">{{ $slabel }}</span>
            </div>
        @endforeach
    </div>

    {{-- Qualification details --}}
    <div class="ar-sec">
        <h3>Qualification</h3>
        <div class="ar-grid">
            <div class="ar-f"><div class="l">Status</div><div class="v"><span class="ar-chip" style="background:{{ $pillBg }}1A;color:{{ $pillBg }};">{{ $statusLabel }}</span></div></div>
            <div class="ar-f"><div class="l">Type</div><div class="v">{{ $q->sessionLabel() }}</div></div>
            <div class="ar-f"><div class="l">Class On File</div><div class="v">{{ $q->class_on_file ? 'Yes' : 'No' }}</div></div>
            <div class="ar-f"><div class="l">Class Completed</div><div class="v">{{ $q->class_on_file_date?->gmp() ?? '—' }}</div></div>
            <div class="ar-f"><div class="l">Cycle Started</div><div class="v">{{ $q->cycle_started_at?->gmp() ?? '—' }}</div></div>
            <div class="ar-f"><div class="l">Last QA Determination</div><div class="v">{{ $q->qa_recommendation ? \Illuminate\Support\Str::title(str_replace('_',' ',$q->qa_recommendation)) : '—' }}</div></div>
            @if($q->needsRetrainingFirst())
                <div class="ar-f"><div class="l">Retraining</div><div class="v"><span class="ar-chip" style="background:#FCEEF0;color:#C8102E;">Required First</span></div></div>
            @endif
        </div>
    </div>

    {{-- LIMS & incubation --}}
    @if($lrun)
        <div class="ar-sec">
            <h3>LIMS &amp; Incubation</h3>
            <div class="ar-grid">
                <div class="ar-f"><div class="l">Worklist</div><div class="v">{{ $lrun->lims_worklist_id ?: '—' }}</div></div>
                <div class="ar-f"><div class="l">LIMS Evaluation</div><div class="v">
                    @php $ev = strtolower((string) $lrun->lims_evaluation); $evc = $ev==='pass'?'#2E7D5B':($ev==='fail'?'#C8102E':'#6B6B73'); @endphp
                    <span class="ar-chip" style="background:{{ $evc }}1A;color:{{ $evc }};">{{ $lrun->lims_evaluation ?: '—' }}</span>
                </div></div>
                <div class="ar-f"><div class="l">Incubation</div><div class="v">
                    @php $ic = str_contains($incBadge,'overdue')?'#C8102E':($incBadge==='Complete'?'#2E7D5B':'#C79A2E'); @endphp
                    <span class="ar-chip" style="background:{{ $ic }}1A;color:{{ $ic }};">{{ $incBadge }}</span>
                </div></div>
                <div class="ar-f"><div class="l">1st Incubation (30-35C)</div><div class="v">{{ trim(($lrun->lims_inc1_incubator ? $lrun->lims_inc1_incubator.': ' : '').($lrun->lims_inc1_start ?: '?').($lrun->lims_inc1_end ? ' -> '.$lrun->lims_inc1_end : '')) ?: '—' }}</div></div>
                <div class="ar-f"><div class="l">2nd Incubation (20-25C)</div><div class="v">{{ trim(($lrun->lims_inc2_incubator ? $lrun->lims_inc2_incubator.': ' : '').($lrun->lims_inc2_start ?: '?').($lrun->lims_inc2_end ? ' -> '.$lrun->lims_inc2_end : '')) ?: '—' }}</div></div>
                @if($lrun->lims_nc_number)<div class="ar-f"><div class="l">NC / TrackWise</div><div class="v">{{ $lrun->lims_nc_number }}</div></div>@endif
            </div>
        </div>
    @endif

    {{-- Run history --}}
    @if($recentRuns->count())
        <div class="ar-sec">
            <h3>Run History</h3>
            <table class="ar-tbl">
                <thead><tr><th>Date</th><th>Result</th><th>Worklist</th><th>Cycle</th></tr></thead>
                <tbody>
                    @foreach($recentRuns as $r)
                        @php $res = strtolower((string) ($r->result instanceof \BackedEnum ? $r->result->value : $r->result)); $rc = $res==='pass'?'#2E7D5B':($res==='fail'?'#C8102E':'#6B6B73'); @endphp
                        <tr>
                            <td>{{ $r->run_date?->gmp() ?? '—' }}</td>
                            <td><span class="ar-chip" style="background:{{ $rc }}1A;color:{{ $rc }};">{{ ucfirst($res ?: 'pending') }}</span></td>
                            <td>{{ $r->lims_worklist_id ?: '—' }}</td>
                            <td>{{ $r->cycle_type instanceof \BackedEnum ? ucfirst($r->cycle_type->value) : ucfirst((string) ($r->cycle_type ?: '—')) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
