<x-filament-panels::page>
    @php $tab = $this->tab ?? 'runs'; $canApprove = $this->canApprove(); @endphp

    @include('filament.page-hero', ['title' => 'QA Review', 'icon' => 'heroicon-o-clipboard-document-check', 'actions' => '
        <button type="button" wire:click="setTab(\'runs\')" class="gqs-tab ' . ($tab === 'runs' ? 'active' : '') . '">Run Sign-off</button>
        <button type="button" wire:click="setTab(\'classroom\')" class="gqs-tab ' . ($tab === 'classroom' ? 'active' : '') . '">Classroom Sign-off</button>
    '])

    @if($tab === 'classroom')
        @php
            $classQueue = $this->getClassroomQueue();
            $sessCount = is_countable($classQueue) ? count($classQueue) : 0;
            $traineeCount = collect($classQueue)->sum(fn ($s) => count($s['rows']));
        @endphp

        <div class="gqs-stats">
            <div class="gqs-stat green"><div class="n">{{ $sessCount }}</div><div class="l">Sessions Awaiting Approval</div><span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span></div>
            <div class="gqs-stat gold"><div class="n">{{ $traineeCount }}</div><div class="l">Trainees Pending</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
            <div class="gqs-stat purple"><div class="n">{{ $canApprove ? 'You' : '—' }}</div><div class="l">{{ $canApprove ? 'Can Approve' : 'Review Only' }}</div><span class="wm"><x-filament::icon icon="heroicon-o-clipboard-document-check"/></span></div>
        </div>

        @unless($canApprove)
            <div class="gqs-panel"><div class="gqs-empty" style="padding:14px;color:#8A6D0B;">You can review classroom submissions, but only a QA Approver can approve them.</div></div>
        @endunless
        @forelse($classQueue as $session)
            <div class="gqs-panel" style="margin-bottom:16px;">
                <div class="gqs-panel-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:linear-gradient(135deg,#2E7D5B,#225F46);">
                    <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-academic-cap"/> {{ $session['title'] }}</span>
                    <span style="font-size:12px;font-weight:600;opacity:.92;">{{ count($session['rows']) }} pending · submitted {{ $session['submitted_at'] }}@if($session['submitted_by']) by {{ $session['submitted_by'] }}@endif</span>
                </div>
                <div class="gqs-panel-body">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                        <a href="{{ $session['form_url'] }}" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">View Signed Form</a>
                        @if($canApprove)
                            <button type="button" wire:click="openClassSignoff({{ $session['id'] }})" class="gqs-btn" style="background:#2E7D5B;color:#fff;">Sign Off Session</button>
                        @endif
                    </div>
                    <table class="gqs-tbl">
                        <thead><tr><th>Name</th><th>Employee ID</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                            @foreach($session['rows'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['employee_id'] ?: '—' }}</td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if($canApprove)
                                            <button type="button" wire:click="approveClassroom({{ $row['id'] }})" class="sb-act sb-act-green">Approve</button>
                                            <button type="button" wire:click="returnClassroom({{ $row['id'] }})" class="sb-act" style="background:#6A6A72;">Return</button>
                                        @else
                                            <span class="gqs-pill gqs-pill-purple">Pending QA</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No classroom training awaiting QA approval. Submitted sessions appear here.</div></div>
        @endforelse
        @if($canApprove)
            @php $signedClass = $this->recentlySignedClassrooms(); @endphp
            @if(count($signedClass))
                <div class="gqs-panel" style="margin-top:8px;">
                    <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-lock-closed"/> Recently Signed Off
                        <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">reopen requires e-signature</span>
                    </div>
                    <div class="gqs-panel-body">
                        <table class="gqs-tbl">
                            <thead><tr><th>Session</th><th>Veeva</th><th>Signed</th><th style="text-align:right;">Action</th></tr></thead>
                            <tbody>
                                @foreach($signedClass as $sc)
                                    <tr>
                                        <td>{{ $sc['title'] }}</td>
                                        <td>{{ $sc['veeva'] ?: '—' }}</td>
                                        <td>{{ $sc['signed_at'] }}</td>
                                        <td style="text-align:right;"><button type="button" wire:click="openClassReopen({{ $sc['id'] }})" class="sb-act" style="background:#6A6A72;">Reopen</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    @else
    {{-- ===================== RUN SIGN-OFF TAB ===================== --}}
    @php $queue = $this->getQueue(); $failed = $this->getFailed();
        $unassigned = $queue->filter(fn ($q) => ! $q->qa_owner_id)->count();
        $oldest = $queue->min('stage_changed_at');
        $waitDays = $oldest ? (int) \Illuminate\Support\Carbon::parse($oldest)->diffInDays(now()) : 0;
    @endphp

    <div class="gqs-stats">
        <div class="gqs-stat magenta"><div class="n">{{ $queue->count() }}</div><div class="l">Awaiting Sign-off</div><span class="wm"><x-filament::icon icon="heroicon-o-inbox-stack"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $unassigned }}</div><div class="l">Unassigned Owner</div><span class="wm"><x-filament::icon icon="heroicon-o-user-plus"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $waitDays }}d</div><div class="l">Oldest In Queue</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
        <div class="gqs-stat red"><div class="n">{{ $failed->count() }}</div><div class="l">Failed, Need Determination</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
    </div>

    @unless($canApprove)
        <div class="gqs-panel"><div class="gqs-empty" style="padding:14px;color:#8A6D0B;">You can review this queue, but only a QA Approver can sign off.</div></div>
    @endunless

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-inbox-stack"/> Awaiting QA Sign-off
            <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $queue->count() }} in queue</span>
        </div>
        <div class="gqs-panel-body">
            @forelse ($queue as $q)
                @php $latestRun = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first(); @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <div>
                        <div style="font-weight:700;color:var(--gqs-text,#1A1A1F);">{{ $q->personnel?->full_name ?? 'Unknown' }}</div>
                        <div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">
                            {{ $q->personnel?->employee_id }} · {{ $q->runs_completed }}/{{ $q->runs_required }} runs · {{ $q->workflow_stage?->label() }}
                            @if($latestRun?->run_uid) · <span style="font-weight:700;color:#A4123F;">{{ $latestRun->run_uid }}</span>@endif
                            @if($latestRun?->veeva_doc_number)
                                · @if($latestRun->veeva_url)<a href="{{ $latestRun->veeva_url }}" target="_blank" style="color:#A4123F;font-weight:700;">Veeva {{ $latestRun->veeva_doc_number }} ↗</a>@else Veeva {{ $latestRun->veeva_doc_number }} @endif
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;white-space:nowrap;">
                        <select wire:change="assignOwner({{ $q->id }}, $event.target.value)" style="font-size:12px;padding:5px 8px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:7px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
                            <option value="">Owner: Unassigned</option>
                            @foreach($this->qaReviewers() as $uid => $uname)
                                <option value="{{ $uid }}" @selected($q->qa_owner_id == $uid)>{{ $uname }}</option>
                            @endforeach
                        </select>
                        @if($canApprove)
                            <button type="button" wire:click="openSignoff({{ $q->id }})" class="gqs-btn gqs-btn-primary" style="padding:7px 14px;">Review / Sign-off</button>
                        @else
                            <span class="gqs-pill gqs-pill-purple">Pending QA</span>
                        @endif
                    </div>
                </div>
            @empty<div class="gqs-empty">Nothing Awaiting Sign-off.</div>@endforelse
        </div>
    </div>

    @if($failed->isNotEmpty())
    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C8102E,#920B22);"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Failed, Needs Determination</div>
        <div class="gqs-panel-body">
            @foreach ($failed as $q)
                @php
                    $failedRun = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)->where('result', 'fail')->latest('id')->first();
                    $nc = $failedRun ? \App\Models\NonConformance::where('qualification_run_id', $failedRun->id)->first() : null;
                @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <span><strong>{{ $q->personnel?->full_name }}</strong> <span style="color:var(--gqs-text-dim,#6A6A72);font-size:12.5px;">· {{ $q->personnel?->employee_id }}@if($failedRun?->run_uid) · {{ $failedRun->run_uid }}@endif</span>
                        @if($nc)<span class="gqs-pill gqs-pill-red" style="margin-left:6px;">NC {{ $nc->nc_number }}@if($nc->trackwise_id) · TW {{ $nc->trackwise_id }}@endif</span>@endif
                    </span>
                    @if($canApprove)
                        <button type="button" wire:click="openDetermination({{ $q->id }})" class="gqs-btn" style="background:#C8102E;color:#fff;padding:6px 13px;">QA Determination</button>
                    @else
                        <span class="gqs-pill gqs-pill-red">Determination Pending</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif
    @endif

    <style>
        .sb-act{font-size:12px;font-weight:700;padding:5px 13px;border-radius:7px;border:none;cursor:pointer;margin-left:6px;color:#fff;}
        .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
        .sb-act-red{background:#C8102E;} .sb-act-red:hover{background:#9A0C23;}
    </style>

    {{-- QA Sign-off wizard --}}
    @php $wz = $this->wizData(); @endphp
    @if($wz)
        <div class="gqs-modal-overlay" wire:click.self="closeSignoff">
            <div class="gqs-modal" style="width:560px;">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-clipboard-document-check"/></span>QA Sign-off · {{ $wz['name'] }}</div>
                <div class="gqs-modal-body">
                    {{-- Step indicator --}}
                    <div style="display:flex;gap:6px;margin-bottom:4px;">
                        <span class="gqs-pill {{ $wizStep==='review'?'gqs-pill-purple':'' }}">1 · Review</span>
                        <span class="gqs-pill {{ in_array($wizStep,['pass','fail'])?'gqs-pill-purple':'' }}">2 · Decision</span>
                    </div>

                    @if($wizStep === 'review')
                        <div class="gqs-tbl" style="display:block;">
                            <div style="display:grid;grid-template-columns:130px 1fr;gap:6px 12px;font-size:13px;">
                                <div style="color:var(--gqs-text-dim,#6A6A72);">Employee</div><div>{{ $wz['employee_id'] }}</div>
                                <div style="color:var(--gqs-text-dim,#6A6A72);">Cycle</div><div>{{ $wz['type'] }} · {{ $wz['progress'] }} runs</div>
                                <div style="color:var(--gqs-text-dim,#6A6A72);">Run</div><div>{{ $wz['run_uid'] ?: '—' }}</div>
                                <div style="color:var(--gqs-text-dim,#6A6A72);">Veeva</div><div>@if($wz['veeva'])@if($wz['veeva_url'])<a href="{{ $wz['veeva_url'] }}" target="_blank" style="color:#A4123F;font-weight:700;">{{ $wz['veeva'] }} ↗</a>@else {{ $wz['veeva'] }} @endif @else <span style="color:#C8102E;">Not entered</span> @endif</div>
                                <div style="color:var(--gqs-text-dim,#6A6A72);">LMS Number</div><div>{{ $wz['lms'] ?: '—' }}</div>
                                @if($wz['nc'])<div style="color:var(--gqs-text-dim,#6A6A72);">Non-Conformance</div><div><span class="gqs-pill gqs-pill-red">{{ $wz['nc'] }}</span></div>@endif
                            </div>
                        </div>
                        @if($wz['is_subject'])
                            <div style="padding:10px 12px;background:#FBE9EC;border:1px solid #E9B8C2;border-radius:8px;font-size:12.5px;color:#8A1029;">Two-person rule: you cannot sign off your own qualification.</div>
                        @endif
                    @elseif($wizStep === 'pass')
                        <div style="font-size:13px;line-height:1.5;">By signing, I, <strong>{{ auth()->user()->name }}</strong>, certify I have reviewed this qualification record and approve it as complete. This electronic signature is the legally binding equivalent of my handwritten signature.</div>
                        <div><label class="gqs-flbl">Signature Meaning</label><input type="text" wire:model="wizMeaning" class="gqs-fld"></div>
                        @if($wz['esig'])<div><label class="gqs-flbl">Confirm Your Password</label><input type="password" wire:model="wizPassword" class="gqs-fld"></div>@endif
                    @else
                        <div><label class="gqs-flbl">Requalification Path</label>
                            <select wire:model="wizPath" class="gqs-fld">
                                <option value="requal_three">Full requalification · 3 successful runs</option>
                                <option value="requal_one">Annual requalification · 1 successful run</option>
                            </select>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;"><input type="checkbox" wire:model="wizRetrain"> Require Gowning Class Retraining (clears class on file)</label>
                        <div><label class="gqs-flbl">Determination Note</label><input type="text" wire:model="wizNote" class="gqs-fld"></div>
                        @if($wz['esig'])<div><label class="gqs-flbl">Confirm Your Password</label><input type="password" wire:model="wizPassword" class="gqs-fld"></div>@endif
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeSignoff" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <span style="display:flex;gap:8px;">
                        @if($wizStep === 'review')
                            <button type="button" wire:click="wizSetStep('fail')" class="gqs-btn" style="background:#C8102E;color:#fff;" @disabled($wz['is_subject'])>Fail</button>
                            <button type="button" wire:click="wizSetStep('pass')" class="gqs-btn gqs-btn-primary" @disabled($wz['is_subject'])>Pass · Sign Off</button>
                        @elseif($wizStep === 'pass')
                            <button type="button" wire:click="wizSetStep('review')" class="gqs-btn gqs-btn-ghost">Back</button>
                            <button type="button" wire:click="finalizeSignoff" class="gqs-btn gqs-btn-primary">Sign Off → Qualified</button>
                        @else
                            <button type="button" wire:click="wizSetStep('review')" class="gqs-btn gqs-btn-ghost">Back</button>
                            <button type="button" wire:click="finalizeFail" class="gqs-btn" style="background:#C8102E;color:#fff;">Record Determination</button>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- Classroom sign-off / reopen --}}
    @php $cd = $this->clsData(); @endphp
    @if($cd)
        <div class="gqs-modal-overlay" wire:click.self="closeClassSignoff">
            <div class="gqs-modal" style="width:520px;">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-academic-cap"/></span>{{ $clsMode === 'reopen' ? 'Reopen Classroom Sign-off' : 'Classroom Sign-off' }} · {{ $cd['title'] }}</div>
                <div class="gqs-modal-body">
                    @if($clsMode === 'reopen')
                        <div style="font-size:13px;line-height:1.5;">Reopening returns the completed trainees on this session to QA review for correction. This is a recorded, electronically-signed action.</div>
                        @if($cd['esig'])<div><label class="gqs-flbl">Confirm Your Password</label><input type="password" wire:model="clsPassword" class="gqs-fld"></div>@endif
                    @else
                        <div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">{{ $cd['count'] }} trainee(s) pending: {{ $cd['names'] ?: '—' }}</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><label class="gqs-flbl">Veeva Report Number</label><input type="text" wire:model="clsVeeva" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">LMS Number (Optional)</label><input type="text" wire:model="clsLms" class="gqs-fld"></div>
                            <div style="grid-column:1 / -1;"><label class="gqs-flbl">Veeva Link (Optional)</label><input type="url" wire:model="clsVeevaUrl" class="gqs-fld"></div>
                        </div>
                        <div style="font-size:13px;line-height:1.5;">By signing, I, <strong>{{ auth()->user()->name }}</strong>, certify the classroom training record is reviewed and approved as complete. This electronic signature is the legally binding equivalent of my handwritten signature.</div>
                        @if($cd['signer_is_trainee'])
                            <div style="padding:10px 12px;background:#FBE9EC;border:1px solid #E9B8C2;border-radius:8px;font-size:12.5px;color:#8A1029;">Two-person rule: you are a trainee on this session and cannot sign it off.</div>
                        @endif
                        @if($cd['esig'])<div><label class="gqs-flbl">Confirm Your Password</label><input type="password" wire:model="clsPassword" class="gqs-fld"></div>@endif
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeClassSignoff" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    @if($clsMode === 'reopen')
                        <button type="button" wire:click="finalizeClassReopen" class="gqs-btn" style="background:#C8102E;color:#fff;">Reopen & Sign</button>
                    @else
                        <button type="button" wire:click="finalizeClassSignoff" class="gqs-btn" style="background:#2E7D5B;color:#fff;" @disabled($cd['signer_is_trainee'])>Sign Off Session</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
