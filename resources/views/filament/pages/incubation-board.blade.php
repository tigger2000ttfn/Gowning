<x-filament-panels::page>
    @php
        $tab = $this->tab ?? 'incubating';
        $incubating = $this->getIncubating();
        $evaluation = $this->getEvaluation();
        $canEval = $this->canEvaluate();
    @endphp

    @include('filament.page-hero', ['title' => 'Lab Review', 'icon' => 'heroicon-o-beaker', 'actions' => '
        <button type="button" wire:click="setTab(\'incubating\')" class="gqs-tab ' . ($tab === 'incubating' ? 'active' : '') . '">Incubating (' . $incubating->count() . ')</button>
        <button type="button" wire:click="setTab(\'evaluation\')" class="gqs-tab ' . ($tab === 'evaluation' ? 'active' : '') . '">Result Evaluation (' . $evaluation->count() . ')</button>
        <button type="button" wire:click="setTab(\'history\')" class="gqs-tab ' . ($tab === 'history' ? 'active' : '') . '">History</button>
    '])

    <div class="gqs-stats">
        <div class="gqs-stat gold"><div class="n">{{ $incubating->count() }}</div><div class="l">Incubating</div><span class="wm"><x-filament::icon icon="heroicon-o-beaker"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $evaluation->count() }}</div><div class="l">Ready To Evaluate</div><span class="wm"><x-filament::icon icon="heroicon-o-clipboard-document-check"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $this->incubationDays() }}d</div><div class="l">Incubation Period</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
    </div>


    @if($tab === 'incubating')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-beaker"/> In Incubation
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $incubating->count() }} plates</span>
            </div>
            <div class="gqs-panel-body">
                @if($incubating->isEmpty())
                    <div class="gqs-empty">Nothing in incubation. Performed runs appear here while their plates incubate.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Worklist</th><th>Started</th><th>Ready</th><th>Remaining</th></tr></thead>
                        <tbody>
                            @foreach($incubating as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r->employee_id }}</td>
                                    <td>{{ $r->name }}</td>
                                    <td>{{ $r->worklist ?: '—' }}</td>
                                    <td>{{ $r->started ? \Illuminate\Support\Carbon::parse($r->started)->format('d M Y') : '—' }}</td>
                                    <td>{{ $r->ready ? \Illuminate\Support\Carbon::parse($r->ready)->format('d M Y') : '—' }}</td>
                                    <td>
                                        @if($r->remaining === null)<span class="gqs-pill">—</span>
                                        @elseif($r->remaining > 0)<span class="gqs-pill gqs-pill-gold">{{ (int) ceil($r->remaining) }}d left</span>
                                        @else<span class="gqs-pill gqs-pill-green">Ready</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @elseif($tab === 'evaluation')
        @unless($canEval)
            <div class="gqs-panel"><div class="gqs-empty" style="padding:14px;color:#8A6D0B;">You can view evaluations, but a QC Micro analyst enters results.</div></div>
        @endunless
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clipboard-document-check"/> Ready For Result Evaluation
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $evaluation->count() }} Awaiting Results</span>
            </div>
            <div class="gqs-panel-body">
                @if($evaluation->isEmpty())
                    <div class="gqs-empty">No runs ready to evaluate. Runs move here once their incubation period elapses.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Cycle</th><th>Run</th><th>Worklist</th><th>Performed</th><th>Progress</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                            @foreach($evaluation as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r->employee_id }}</td>
                                    <td>{{ $r->name }}</td>
                                    <td>{{ $r->cycle }}</td>
                                    <td>{{ $r->run_uid ?: '—' }}</td>
                                    <td>{{ $r->worklist ?: '—' }}</td>
                                    <td>{{ $r->performed ? \Illuminate\Support\Carbon::parse($r->performed)->format('d M Y') : '—' }}</td>
                                    <td style="white-space:nowrap;">{{ $r->progress }}</td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if(! $canEval)
                                            <span class="gqs-pill gqs-pill-purple">Awaiting QCM</span>
                                        @elseif($r->step === 'signoff')
                                            <a href="{{ $r->form_url }}" target="_blank" class="sb-act" style="background:#1C1C21;color:#fff;text-decoration:none;">Approval Form</a>
                                            {{ ($this->qcmSignOffAction)(['id' => $r->id]) }}
                                        @else
                                            {{ ($this->enterResultsAction)(['id' => $r->id]) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
        @php $history = $this->getHistory(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clock"/> Recently Evaluated
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">last {{ $history->count() }} results</span>
            </div>
            <div class="gqs-panel-body">
                @if($history->isEmpty())
                    <div class="gqs-empty">No results entered yet.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Worklist</th><th>Run Date</th><th>Result</th><th>Evaluated</th></tr></thead>
                        <tbody>
                            @foreach($history as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r->employee_id }}</td>
                                    <td>{{ $r->name }}</td>
                                    <td>{{ $r->worklist ?: '—' }}</td>
                                    <td>{{ $r->run_date ? \Illuminate\Support\Carbon::parse($r->run_date)->format('d M Y') : '—' }}</td>
                                    <td><span class="gqs-pill {{ $r->result === 'Pass' ? 'gqs-pill-green' : 'gqs-pill-red' }}">{{ $r->result }}</span></td>
                                    <td>{{ $r->entered_at ? \Illuminate\Support\Carbon::parse($r->entered_at)->format('d M Y H:i') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
