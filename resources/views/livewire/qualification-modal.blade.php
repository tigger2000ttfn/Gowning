<div>
@if($detail)
    <div class="gqs-modal-overlay" wire:click.self="close" style="z-index:9999;">
        <div class="gqs-modal" style="width:680px;max-width:96vw;">
            <div style="background:linear-gradient(135deg,{{ $detail['stage_color'] }},{{ $detail['stage_color'] }}CC);padding:18px 20px;border-radius:14px 14px 0 0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div>
                    <div style="font-weight:800;font-size:19px;color:#fff;">{{ $detail['name'] }}</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.92);">{{ $detail['employee_id'] }}@if($detail['job_title']) · {{ $detail['job_title'] }}@endif@if($detail['department']) · {{ $detail['department'] }}@endif</div>
                </div>
                <span style="background:rgba(255,255,255,.22);color:#fff;font-weight:700;font-size:12px;padding:5px 12px;border-radius:999px;white-space:nowrap;">{{ $detail['stage_label'] }}</span>
            </div>
            <div class="gqs-modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                    <div><div class="dm-l">Status</div><div class="dm-v"><span class="gqs-pill" style="background:{{ $detail['status_color'] }}1A;color:{{ $detail['status_color'] }};font-weight:700;">{{ $detail['status_pill'] }}</span></div></div>
                    <div><div class="dm-l">Session Type</div><div class="dm-v">{{ $detail['type'] }}</div></div>
                    <div><div class="dm-l">Runs</div><div class="dm-v">{{ $detail['passes'] }} / {{ $detail['required'] }} passing</div></div>
                    <div><div class="dm-l">{{ $detail['due_label'] }}</div><div class="dm-v" style="{{ $detail['past_due'] ? 'color:#C8102E;font-weight:700;' : '' }}">{{ $detail['due'] ?: '-' }} <span class="gqs-pill {{ $detail['due_tag'] === 'Lapsed' ? 'gqs-pill-red' : 'gqs-pill-gray' }}">{{ $detail['due_tag'] }}</span></div></div>
                    <div><div class="dm-l">Qualified Date</div><div class="dm-v">{{ $detail['qualified_date'] ?: 'Not yet (awaiting QA)' }}</div></div>
                    <div><div class="dm-l">QA Owner</div><div class="dm-v">{{ $detail['qa_owner'] ?: 'Unassigned' }}</div></div>
                    <div><div class="dm-l">Class On File</div><div class="dm-v">@if($detail['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span> {{ $detail['class_on_file_date'] }}@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                    <div style="grid-column:span 2;"><div class="dm-l">LIMS Worklist</div><div class="dm-v">
                        @if($linking)
                            <div style="display:flex;align-items:stretch;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;overflow:hidden;max-width:320px;">
                                <span style="display:flex;align-items:center;padding:0 10px;background:var(--gqs-surface-2,#F1F1F4);font-weight:800;color:var(--gqs-text-dim,#6A6A72);border-right:1px solid var(--gqs-border,#C4C4CC);">EM-</span>
                                <input type="text" wire:model.live.debounce.250ms="wlValue" list="qm-wl-suggest" class="gqs-fld" style="border:none;border-radius:0;flex:1;" placeholder="numbers" wire:keydown.enter="saveLink">
                            </div>
                            <datalist id="qm-wl-suggest">@foreach($this->worklistSuggestions() as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
                            <div style="margin-top:6px;display:flex;gap:6px;"><button wire:click="saveLink" class="sb-act" style="background:#2E7D5B;">Save</button><button wire:click="cancelLink" class="sb-act" style="background:#6A6A72;">Cancel</button></div>
                        @elseif($detail['worklist'])
                            {{ $detail['worklist'] }}
                        @elseif($detail['needs_worklist'])
                            <button type="button" wire:click="startLink" class="sb-act" style="background:#1F6FB2;">+ Link Worklist</button>
                        @else
                            <span style="color:var(--gqs-text-dim,#9A9AA4);">Not linked yet</span>
                        @endif
                    </div></div>
                </div>

                {{-- Pipeline stepper --}}
                <div class="qm-step">
                    @foreach($detail['steps'] as $s)
                        <div class="qm-step-cell {{ $s['done'] ? 'done' : '' }} {{ $s['current'] ? 'current' : '' }}">
                            <span class="qm-step-bar"></span>
                            <span class="qm-step-dot">@if($s['done'])&check;@endif</span>
                            <span class="qm-step-lbl">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                @if($detail['lims'])
                    <div class="dm-l" style="margin-top:18px;">LIMS &amp; Incubation</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:6px;">
                        <div><div class="dm-l">Evaluation</div><div class="dm-v">{{ $detail['lims']['evaluation'] ?: '-' }}</div></div>
                        <div><div class="dm-l">NC / TrackWise</div><div class="dm-v">@if($detail['lims']['nc'])@if($detail['lims']['nc_url'])<a href="{{ $detail['lims']['nc_url'] }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $detail['lims']['nc'] }} &nearr;</a>@else {{ $detail['lims']['nc'] }} @endif @else - @endif</div></div>
                        <div><div class="dm-l">1st Incubation (30-35C)</div><div class="dm-v">{{ $detail['lims']['inc1'] ?: '-' }}</div></div>
                        <div><div class="dm-l">2nd Incubation (20-25C)</div><div class="dm-v">{{ $detail['lims']['inc2'] ?: '-' }}</div></div>
                    </div>
                @endif

                @if(count($detail['runs']))
                    <div class="dm-l" style="margin-top:18px;">Run History</div>
                    <table class="gqs-tbl" style="margin-top:6px;">
                        <thead><tr><th>Date</th><th>Result</th><th>Worklist</th><th>Inc Status</th><th>Evaluation</th><th>NC</th></tr></thead>
                        <tbody>@foreach($detail['runs'] as $r)
                            <tr>
                                <td style="white-space:nowrap;">{{ $r['date'] ?: '-' }}</td>
                                <td>{{ $r['result'] }}</td>
                                <td>{{ $r['worklist'] ?: '-' }}</td>
                                <td>{{ $r['inc_status'] ?: '-' }}</td>
                                <td>{{ $r['evaluation'] ?: '-' }}</td>
                                <td>@if($r['nc'])@if($r['nc_url'])<a href="{{ $r['nc_url'] }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $r['nc'] }} &nearr;</a>@else {{ $r['nc'] }} @endif @else - @endif</td>
                            </tr>
                        @endforeach</tbody>
                    </table>
                @endif
            </div>
            <div class="gqs-modal-foot" style="justify-content:space-between;">
                <button wire:click="close" class="gqs-btn gqs-btn-ghost">Close</button>
                <span style="display:flex;gap:8px;">
                    @if($detail['can_signoff'])
                        <a href="{{ $detail['signoff_url'] }}" class="gqs-btn" style="background:#6B2C91;color:#fff;text-decoration:none;">Review / Sign-off</a>
                    @elseif(!empty($detail['review_url']))
                        <a href="{{ $detail['review_url'] }}" class="gqs-btn" style="background:#1F6FB2;color:#fff;text-decoration:none;">{{ $detail['review_label'] }}</a>
                    @endif
                    <a href="{{ $detail['record_url'] }}" target="_blank" rel="noopener" class="gqs-btn gqs-btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">Open Full Record <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" style="width:14px;height:14px;"/></a>
                </span>
            </div>
        </div>
    </div>

    <style>
        .qm-step{display:flex;align-items:flex-start;gap:0;margin-top:16px;overflow-x:auto;padding-bottom:4px;}
        .qm-step-cell{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;min-width:62px;position:relative;}
        .qm-step-dot{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;border:2px solid var(--gqs-border,#E5E5EA);background:var(--gqs-surface,#fff);color:var(--gqs-text-dim,#9A9AA4);z-index:1;}
        .qm-step-cell.done .qm-step-dot{background:#2E7D5B;border-color:#2E7D5B;color:#fff;}
        .qm-step-cell.current .qm-step-dot{background:#1F6FB2;border-color:#1F6FB2;color:#fff;box-shadow:0 0 0 4px rgba(31,111,178,.18);}
        .qm-step-lbl{font-size:9.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);text-align:center;white-space:nowrap;}
        .qm-step-cell.current .qm-step-lbl{color:#1F6FB2;font-weight:800;}
        .qm-step-bar{position:absolute;top:11px;left:50%;width:100%;height:2px;background:var(--gqs-border,#E5E5EA);z-index:0;}
        .qm-step-cell.done .qm-step-bar{background:#2E7D5B;}
        .qm-step-cell:last-child .qm-step-bar{display:none;}
    </style>
@endif
</div>
