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

    <div class="ar-hero">
        <div style="display:flex;align-items:center;gap:16px;min-width:0;">
            @php
                $nm = trim((string) ($q->personnel?->full_name ?? ''));
                $parts = preg_split('/\s+/', $nm);
                $initials = strtoupper(substr($parts[0] ?? 'Q', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
            @endphp
            <span class="ar-avatar">{{ $initials ?: 'Q' }}</span>
            <div style="min-width:0;">
                <h2>{{ $q->personnel?->full_name ?? 'Qualification' }}</h2>
                <div class="sub">{{ $q->personnel?->employee_id ?: 'No ID' }}@if($q->personnel?->job_title) · {{ $q->personnel->job_title }}@endif@if($q->personnel?->department) · {{ $q->personnel->department }}@endif</div>
                <div class="sub" style="margin-top:5px;">{{ $q->sessionLabel() }}@if(($q->cycle_number ?: 1) > 1) · Cycle {{ $q->cycle_number }}@endif@if($q->superseded_at) (superseded){{-- --}}@endif</div>
            </div>
        </div>
        <span class="ar-pill" style="background:{{ $pillBg }};">{{ $pillTxt }}</span>
    </div>

    <div class="ar-tiles">
        @php /* tiles below */ @endphp
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

    {{-- Person --}}
    <div class="ar-sec">
        <h3><x-filament::icon icon="heroicon-m-user" /> Person</h3>
        <div class="ar-grid">
            <div class="ar-f"><div class="l">Employee ID</div><div class="v">{{ $q->personnel?->employee_id ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Department</div><div class="v">{{ $q->personnel?->department ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Job Title</div><div class="v">{{ $q->personnel?->job_title ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">LIMS Username</div><div class="v">{{ $q->personnel?->lims_username ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Email</div><div class="v">{{ $q->personnel?->email ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Phone</div><div class="v">{{ $q->personnel?->phone ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Supervisor</div><div class="v">{{ $q->personnel?->supervisor ?: '—' }}</div></div>
        </div>
    </div>

    {{-- Qualification details --}}
    <div class="ar-sec">
        <h3><x-filament::icon icon="heroicon-m-clipboard-document-check" /> Qualification</h3>
        <div class="ar-grid">
            <div class="ar-f"><div class="l">Status</div><div class="v"><span class="ar-chip" style="background:{{ $pillBg }}1A;color:{{ $pillBg }};">{{ $statusLabel }}</span></div></div>
            <div class="ar-f"><div class="l">Type</div><div class="v">{{ $q->sessionLabel() }}</div></div>
            <div class="ar-f"><div class="l">Runs</div><div class="v">{{ (int) $q->runs_completed }} / {{ (int) $q->runs_required }} passing</div></div>
            <div class="ar-f"><div class="l">Due Date</div><div class="v">{{ $q->due_date?->gmp() ?? '—' }}</div></div>
            <div class="ar-f"><div class="l">Qualified Date</div><div class="v">{{ $q->qualified_date?->gmp() ?? 'Not yet (awaiting QA)' }}</div></div>
            <div class="ar-f"><div class="l">QA Owner</div><div class="v">{{ $q->qaOwner()?->name ?? 'Unassigned' }}</div></div>
            <div class="ar-f"><div class="l">Cycle</div><div class="v">#{{ (int) ($q->cycle_number ?: 1) }}@if($q->cycle_started_at) · started {{ $q->cycle_started_at->gmp() }}@endif</div></div>
            <div class="ar-f"><div class="l">Class On File</div><div class="v">{{ $q->class_on_file ? 'Yes' : 'No' }}@if($q->class_on_file_date) · {{ $q->class_on_file_date->gmp() }}@endif</div></div>
            <div class="ar-f"><div class="l">LMS Number</div><div class="v">{{ $q->lms_number ?: '—' }}</div></div>
            <div class="ar-f"><div class="l">Last QA Determination</div><div class="v">{{ $q->qa_recommendation ? \Illuminate\Support\Str::title(str_replace('_',' ',$q->qa_recommendation)) : '—' }}</div></div>
            @if($q->needsRetrainingFirst())
                <div class="ar-f"><div class="l">Retraining</div><div class="v"><span class="ar-chip" style="background:#FCEEF0;color:#C8102E;">Required First</span></div></div>
            @endif
        </div>
    </div>

    {{-- LIMS & incubation --}}
    @if($lrun)
        <div class="ar-sec">
            <h3><x-filament::icon icon="heroicon-m-beaker" /> LIMS &amp; Incubation</h3>
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
                <div class="ar-f"><div class="l">NC / TrackWise</div><div class="v">@if($lrun->lims_nc_number)@if($lrun->lims_nc_url)<a href="{{ $lrun->lims_nc_url }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $lrun->lims_nc_number }} &nearr;</a>@else{{ $lrun->lims_nc_number }}@endif @else — @endif</div></div>
                <div class="ar-f"><div class="l">Veeva Report</div><div class="v">@if($lrun->veeva_doc_number)@if($lrun->veeva_url)<a href="{{ $lrun->veeva_url }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $lrun->veeva_doc_number }} &nearr;</a>@else{{ $lrun->veeva_doc_number }}@endif @else — @endif</div></div>
            </div>
        </div>
    @endif

    {{-- Run history --}}
    @if($recentRuns->count())
        <div class="ar-sec">
            <h3><x-filament::icon icon="heroicon-m-arrow-path" /> Run History</h3>
            <table class="ar-tbl">
                <thead><tr><th>Date</th><th>Result</th><th>Worklist</th><th>Inc Status</th><th>Evaluation</th><th>NC</th><th>Veeva</th></tr></thead>
                <tbody>
                    @foreach($recentRuns as $r)
                        @php
                            $res = strtolower((string) ($r->result instanceof \BackedEnum ? $r->result->value : $r->result));
                            $rc = $res==='pass'?'#2E7D5B':($res==='fail'?'#C8102E':'#6B6B73');
                            $rinc = $r->lims_inc2_end ? 'Complete' : ($r->lims_inc1_start ? 'Incubating' : '—');
                        @endphp
                        <tr>
                            <td style="white-space:nowrap;">{{ $r->run_date?->gmp() ?? '—' }}</td>
                            <td><span class="ar-chip" style="background:{{ $rc }}1A;color:{{ $rc }};">{{ ucfirst($res ?: 'pending') }}</span></td>
                            <td>{{ $r->lims_worklist_id ?: '—' }}</td>
                            <td>{{ $rinc }}</td>
                            <td>{{ $r->lims_evaluation ?: '—' }}</td>
                            <td>@if($r->lims_nc_number)@if($r->lims_nc_url)<a href="{{ $r->lims_nc_url }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $r->lims_nc_number }} &nearr;</a>@else{{ $r->lims_nc_number }}@endif @else — @endif</td>
                            <td>@if($r->veeva_doc_number)@if($r->veeva_url)<a href="{{ $r->veeva_url }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $r->veeva_doc_number }} &nearr;</a>@else{{ $r->veeva_doc_number }}@endif @else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Cycle history (prior/superseded cycles for this person) --}}
    @php
        $cycles = \App\Models\Qualification::where('personnel_id', $q->personnel_id)
            ->where('id', '!=', $q->id)
            ->orderByDesc('cycle_number')->orderByDesc('id')->limit(12)->get();
    @endphp
    @if($cycles->count())
        <div class="ar-sec">
            <h3><x-filament::icon icon="heroicon-m-clock" /> Cycle History</h3>
            <table class="ar-tbl">
                <thead><tr><th>Cycle</th><th>Type</th><th>Started</th><th>Qualified</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($cycles as $c)
                        @php $cs = $c->status instanceof \BackedEnum ? $c->status->value : (string) $c->status; @endphp
                        <tr>
                            <td>#{{ (int) ($c->cycle_number ?: 1) }}@if($c->superseded_at) <span class="ar-chip" style="background:#6B6B731A;color:#6B6B73;">superseded</span>@endif</td>
                            <td>{{ $c->sessionLabel() }}</td>
                            <td>{{ $c->cycle_started_at?->gmp() ?? '—' }}</td>
                            <td>{{ $c->qualified_date?->gmp() ?? '—' }}</td>
                            <td>{{ $c->due_date?->gmp() ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::title(str_replace('_',' ',$cs)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
