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
        <button type="button" wire:click="setTab(\'history\')" class="gqs-tab ' . ($tab === 'history' ? 'active' : '') . '">Historical</button>
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
                                    <td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $r->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $r->name }}</button></td>
                                    <td>
                                        @if($r->worklist){{ $r->worklist }}
                                        @elseif($this->canEvaluate())
                                            <button type="button" wire:click="openAddWorklist({{ $r->id }})" class="sb-act" style="background:#1F6FB2;" title="No worklist linked">+ Add Worklist</button>
                                        @else<span style="color:#C8102E;font-weight:600;">Missing</span>@endif
                                    </td>
                                    <td>{{ $r->started ? \Illuminate\Support\Carbon::parse($r->started)->gmp() : '—' }}</td>
                                    <td>{{ $r->ready ? \Illuminate\Support\Carbon::parse($r->ready)->gmp() : '—' }}</td>
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
                                    <td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $r->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $r->name }}</button></td>
                                    <td>{{ $r->cycle }}</td>
                                    <td>{{ $r->run_uid ?: '—' }}</td>
                                    <td>{{ $r->worklist ?: '—' }}</td>
                                    <td>{{ $r->performed ? \Illuminate\Support\Carbon::parse($r->performed)->gmp() : '—' }}</td>
                                    <td style="white-space:nowrap;">{{ $r->progress }}</td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <div style="display:inline-flex;align-items:center;gap:6px;justify-content:flex-end;">
                                        @if(! $canEval)
                                            <span class="gqs-pill gqs-pill-purple">Awaiting QCM</span>
                                        @elseif($r->step === 'signoff')
                                            <a href="{{ $r->form_url }}" target="_blank" rel="noopener" class="lab-icon-btn" title="Open Approval Form (FORM-AST-36749)" aria-label="Approval Form">
                                                <x-filament::icon icon="heroicon-m-document-arrow-down"/>
                                            </a>
                                            {{ ($this->qcmSignOffAction)(['id' => $r->id]) }}
                                        @else
                                            @if(! $r->worklist)
                                                <button type="button" wire:click="openEnterResults({{ $r->id }})" class="sb-act" style="background:#1F6FB2;" title="No worklist linked - add it and enter results">+ Worklist &amp; Results</button>
                                            @else
                                                <button type="button" wire:click="openEnterResults({{ $r->id }})" class="sb-act sb-act-green">Enter Results</button>
                                            @endif
                                        @endif
                                        </div>
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
                        <thead><tr><th>Employee</th><th>Name</th><th>Worklist</th><th>Run Date</th><th>Result</th><th>Evaluated</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                            @foreach($history as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r->employee_id }}</td>
                                    <td>@if($r->qualification_id ?? null)<button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $r->qualification_id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $r->name }}</button>@else {{ $r->name }} @endif</td>
                                    <td>{{ $r->worklist ?: '—' }}</td>
                                    <td>{{ $r->run_date ? \Illuminate\Support\Carbon::parse($r->run_date)->gmp() : '—' }}</td>
                                    <td><span class="gqs-pill {{ $r->result === 'Pass' ? 'gqs-pill-green' : 'gqs-pill-red' }}">{{ $r->result }}</span></td>
                                    <td>{{ $r->entered_at ? \Illuminate\Support\Carbon::parse($r->entered_at)->gmpDt() : '—' }}</td>
                                    <td style="text-align:right;">
                                        @if($this->canEvaluate() && ! $r->locked)
                                            <button type="button" wire:click="openResultUndo({{ $r->id }})" class="sb-act" style="background:#6A6A72;">Undo</button>
                                        @elseif($r->locked)
                                            <span class="gqs-pill gqs-pill-gray">Signed To QA</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif

    {{-- Lab result undo (revert a released result for re-entry) --}}
    @if($undoRunId)
        <div class="gqs-modal-overlay" wire:click.self="closeResultUndo">
            <div class="gqs-modal" style="width:480px;">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-arrow-uturn-left"/></span>Undo Result</div>
                <div class="gqs-modal-body">
                    <div style="font-size:13.5px;line-height:1.5;color:var(--gqs-text,#1A1A1F);">This returns the run to evaluation so the result can be re-entered. A reason and comment are required and recorded.</div>
                    <div><label class="gqs-flbl">Reason <span style="color:#C8102E;">*</span></label>
                        <select wire:model="undoReason" class="gqs-fld">
                            <option value="">Select a reason...</option>
                            @foreach($this->undoReasons() as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="gqs-flbl">Comment <span style="color:#C8102E;">*</span></label><input type="text" wire:model="undoComment" class="gqs-fld" placeholder="What was wrong?"></div>
                    @if((bool) \App\Models\Setting::get('esig_required', true))<div><label class="gqs-flbl">Confirm Your Password</label><input type="password" wire:model="undoPassword" class="gqs-fld"></div>@endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeResultUndo" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="finalizeResultUndo" class="gqs-btn" style="background:#6A6A72;color:#fff;">Revert For Re-entry</button>
                </div>
            </div>
        </div>
    @endif

    @if($awQid)
        <div class="gqs-modal-overlay" wire:click.self="closeAddWorklist">
            <div class="gqs-modal" style="width:420px;max-width:94vw;">
                <div style="background:linear-gradient(135deg,#1F6FB2,#185A92);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:17px;color:#fff;">Link LIMS Worklist</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $this->awPersonName() }}</div>
                </div>
                <div class="gqs-modal-body">
                    <label class="gqs-flbl">LIMS Worklist <span style="color:#C8102E;">*</span></label>
                    <input type="text" wire:model="awWorklist" class="gqs-fld" placeholder="EM-..." wire:keydown.enter="saveAddWorklist">
                    <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:6px;">Links the run to its LIMS worklist so incubation status, evaluation, and NC data sync automatically.</div>
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closeAddWorklist" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button wire:click="saveAddWorklist" class="gqs-btn gqs-btn-primary">Link Worklist</button>
                </div>
            </div>
        </div>
    @endif

    @if($erQid)
        <div class="gqs-modal-overlay" wire:click.self="closeEnterResults">
            <div class="gqs-modal" style="width:480px;max-width:94vw;">
                <div style="background:linear-gradient(135deg,#1F6FB2,#185A92);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:17px;color:#fff;">Enter LIMS Results</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $this->erPersonName() }}</div>
                </div>
                <div class="gqs-modal-body" style="display:flex;flex-direction:column;gap:13px;">
                    <div>
                        <label class="gqs-flbl">LIMS Worklist <span style="color:#C8102E;">*</span></label>
                        <div style="display:flex;align-items:stretch;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;overflow:hidden;">
                            <span style="display:flex;align-items:center;padding:0 12px;background:var(--gqs-surface-2,#F1F1F4);font-weight:800;color:var(--gqs-text-dim,#6A6A72);border-right:1px solid var(--gqs-border,#C4C4CC);">EM-</span>
                            <input type="text" wire:model.live.debounce.250ms="er.worklist" list="er-wl-suggest" class="gqs-fld" style="border:none;border-radius:0;flex:1;" placeholder="type the numbers">
                        </div>
                        <datalist id="er-wl-suggest">@foreach($this->erWorklistSuggestions() as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
                        <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">Type only the numbers - EM- is added automatically. You can save the worklist now and enter the result later.</div>
                    </div>
                    <div>
                        <label class="gqs-flbl">LMS Number (Optional)</label>
                        <input type="text" wire:model="er.lms_number" class="gqs-fld" placeholder="Optional tracking number">
                    </div>
                    <div>
                        <label class="gqs-flbl">Overall Result <span style="font-weight:600;color:var(--gqs-text-dim,#9A9AA4);">(optional - leave blank to record worklist only)</span></label>
                        <div style="display:flex;gap:8px;margin-top:4px;">
                            <button type="button" wire:click="$set('er.overall', @js($er['overall'] ?? '') === 'pass' ? '' : 'pass')" class="gqs-btn" style="flex:1;{{ ($er['overall'] ?? '')==='pass' ? 'background:#2E7D5B;color:#fff;' : 'background:#EAEAEF;color:#1A1A1F;' }}">Pass</button>
                            <button type="button" wire:click="$set('er.overall', @js($er['overall'] ?? '') === 'fail' ? '' : 'fail')" class="gqs-btn" style="flex:1;{{ ($er['overall'] ?? '')==='fail' ? 'background:#C8102E;color:#fff;' : 'background:#EAEAEF;color:#1A1A1F;' }}">Fail</button>
                        </div>
                    </div>
                    @if(($er['overall'] ?? '') === 'fail')
                        <div>
                            <label class="gqs-flbl">TrackWise NC Number <span style="color:#C8102E;">*</span></label>
                            <div style="display:flex;align-items:stretch;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;overflow:hidden;">
                                <span style="display:flex;align-items:center;padding:0 12px;background:var(--gqs-surface-2,#F1F1F4);font-weight:800;color:var(--gqs-text-dim,#6A6A72);border-right:1px solid var(--gqs-border,#C4C4CC);">NC-</span>
                                <input type="text" wire:model="er.trackwise_id" class="gqs-fld" style="border:none;border-radius:0;flex:1;" placeholder="type the numbers">
                            </div>
                            <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">Required on a fail (per SOP-AST-28480). Type only the numbers.</div>
                        </div>
                        <div>
                            <label class="gqs-flbl">Observation / Note (Optional)</label>
                            <input type="text" wire:model="er.nc_note" class="gqs-fld" placeholder="Brief observation for the NC">
                        </div>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closeEnterResults" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button wire:click="saveEnterResults" class="gqs-btn gqs-btn-primary">{{ ($er['overall'] ?? '') === '' ? 'Save Worklist' : 'Record Result' }}</button>
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
