<x-filament-panels::page>
    @php $stats = $this->stats(); $gaps = $this->gaps(); $totalGaps = $gaps['booked_no_qual']['count'] + $gaps['no_worklist']['count'] + $gaps['no_class']['count']; @endphp

    @include('filament.page-hero', ['title' => 'Active Runs', 'icon' => 'heroicon-o-shield-check', 'actions' => '
        <button type="button" wire:click="setTab(\'roster\')" class="gqs-tab ' . ($tab === 'roster' ? 'active' : '') . '">Roster</button>
        <button type="button" wire:click="setTab(\'dashboard\')" class="gqs-tab ' . ($tab === 'dashboard' ? 'active' : '') . '">Dashboard</button>
    '])

    {{-- Stat cards --}}
    <div class="gqs-stats">
        <div class="gqs-stat charcoal"><div class="n">{{ $stats['total'] }}</div><div class="l">Active Records</div><span class="wm"><x-filament::icon icon="heroicon-o-shield-check"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $stats['in_pipeline'] }}</div><div class="l">In Run Pipeline</div><span class="wm"><x-filament::icon icon="heroicon-o-arrow-path"/></span></div>
        <div class="gqs-stat blue"><div class="n">{{ $stats['awaiting_class'] }}</div><div class="l">Awaiting / Done Class</div><span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $stats['in_qa'] }}</div><div class="l">In QA Review</div><span class="wm"><x-filament::icon icon="heroicon-o-clipboard-document-check"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $stats['due_soon'] }}</div><div class="l">Due Soon</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
        <div class="gqs-stat red"><div class="n">{{ $stats['past_due'] }}</div><div class="l">Past Due</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
    </div>

    @if($tab === 'roster')
        {{-- Fix-it cards: only when there is something to fix --}}
        @if($totalGaps > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:18px;">
                @if($gaps['no_worklist']['count'] > 0)
                    <div class="ar-fix" style="--fix:#1F6FB2;">
                        <div class="ar-fix-h"><x-filament::icon icon="heroicon-m-beaker"/> <span class="ar-fix-n">{{ $gaps['no_worklist']['count'] }}</span> No LIMS Worklist</div>
                        <div class="ar-fix-sub">Performed runs with no worklist linked, so LIMS cannot sync. Link it here.</div>
                        <div class="ar-fix-list">
                            @foreach($gaps['no_worklist']['people'] as $person)
                                <button type="button" wire:click="openLinkWorklist({{ $person['id'] }})" class="ar-fix-btn">
                                    <span>{{ $person['name'] }} <span style="opacity:.6;">{{ $person['employee_id'] }}</span></span>
                                    <span class="ar-fix-go">Link &rarr;</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($gaps['booked_no_qual']['count'] > 0)
                    <div class="ar-fix" style="--fix:#C8102E;">
                        <div class="ar-fix-h"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> <span class="ar-fix-n">{{ $gaps['booked_no_qual']['count'] }}</span> Booked, No Record</div>
                        <div class="ar-fix-sub">Has a run reservation but no qualification record. {{ $this->isSuperUser() ? 'Set up their qualification.' : 'A super user must set up their qualification.' }}</div>
                        <div class="ar-fix-list">
                            @foreach($gaps['booked_no_qual']['people'] as $person)
                                @if($this->isSuperUser())
                                    <button type="button" wire:click="openOnboard({{ $person['id'] }})" class="ar-fix-btn">
                                        <span>{{ $person['name'] }} <span style="opacity:.6;">{{ $person['employee_id'] }}</span></span>
                                        <span class="ar-fix-go">Set Up &rarr;</span>
                                    </button>
                                @else
                                    <div class="ar-fix-btn" style="cursor:default;">{{ $person['name'] }}</div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($gaps['no_class']['count'] > 0)
                    <div class="ar-fix" style="--fix:#C79A2E;">
                        <div class="ar-fix-h"><x-filament::icon icon="heroicon-m-academic-cap"/> <span class="ar-fix-n">{{ $gaps['no_class']['count'] }}</span> Missing Class</div>
                        <div class="ar-fix-sub">In the run pipeline without gowning class on file. Record their class on the Class Scheduler.</div>
                        <div class="ar-fix-list">
                            @foreach($gaps['no_class']['people'] as $person)
                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}" class="ar-fix-btn" style="text-decoration:none;">
                                    <span>{{ $person['name'] }} <span style="opacity:.6;">{{ $person['employee_id'] }}</span></span>
                                    <span class="ar-fix-go">Class &rarr;</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Roster table --}}
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <x-filament::icon icon="heroicon-m-users"/> Active Qualification Records
                <span style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID" class="gqs-fld" style="width:auto;min-width:170px;padding:5px 10px;">
                    <select wire:model.live="filterStage" class="gqs-fld" style="width:auto;min-width:150px;padding:5px 10px;">
                        @foreach($this->stageOptions() as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                    </select>
                </span>
            </div>
            <div class="gqs-panel-body">
                @php $rows = $this->rows(); @endphp
                @if(empty($rows))
                    <div class="gqs-empty">No active records match. Completed records move to Run Completions.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Department</th><th>Type</th><th>Stage</th><th>Status</th><th>Runs</th><th>Due</th><th>Worklist</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr wire:key="ar-{{ $row['id'] }}-{{ $row['stage'] }}" style="cursor:pointer;" wire:click="openRow({{ $row['id'] }})">
                                    <td style="font-weight:600;">{{ $row['employee_id'] ?: '-' }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['department'] ?: '-' }}</td>
                                    <td>{{ $row['type'] }}</td>
                                    <td><span class="gqs-pill" style="background:{{ $row['stage_color'] }}1A;color:{{ $row['stage_color'] }};font-weight:700;">{{ $row['stage_label'] }}</span></td>
                                    <td><span class="gqs-pill" style="background:{{ $row['status_color'] }}1A;color:{{ $row['status_color'] }};font-weight:700;">{{ $row['status_pill'] }}</span></td>
                                    <td style="white-space:nowrap;">
                                        <span style="display:inline-flex;gap:3px;align-items:center;">
                                            @for($i = 0; $i < $row['required']; $i++)
                                                <span style="width:9px;height:9px;border-radius:50%;background:{{ $i < $row['passes'] ? '#2E7D5B' : 'var(--gqs-border,#D5D5DC)' }};"></span>
                                            @endfor
                                            <span style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-left:3px;">{{ $row['passes'] }}/{{ $row['required'] }}</span>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;{{ $row['past_due'] ? 'color:#C8102E;font-weight:700;' : '' }}">{{ $row['due'] ?: '-' }}</td>
                                    <td style="white-space:nowrap;" wire:click.stop>
                                        @if($row['worklist']){{ $row['worklist'] }}
                                        @elseif($row['needs_worklist'])
                                            <button type="button" wire:click="openLinkWorklist({{ $row['id'] }})" class="sb-act" style="background:#1F6FB2;">+ Worklist</button>
                                        @else <span style="color:var(--gqs-text-dim,#9A9AA4);">-</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
        {{-- Dashboard tab: clean stage progression cards --}}
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-chart-bar"/> Pipeline By Stage</div>
            <div class="gqs-panel-body" style="padding:18px;">
                @php $funnel = $this->stageFunnel(); $maxF = max(1, collect($funnel)->max('count')); @endphp
                <div class="ar-funnel">
                    @foreach($funnel as $f)
                        <div class="ar-fcell">
                            <div class="ar-fbar-wrap">
                                <div class="ar-fbar" style="height:{{ $f['count'] > 0 ? max(8, round($f['count'] / $maxF * 100)) : 3 }}%;background:{{ $f['color'] }};"></div>
                            </div>
                            <div class="ar-fnum" style="color:{{ $f['count'] > 0 ? $f['color'] : 'var(--gqs-text-dim,#9A9AA4)' }};">{{ $f['count'] }}</div>
                            <div class="ar-flbl">{{ $f['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:16px;">
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clock"/> Due Soon</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:#C79A2E;line-height:1;">{{ $stats['due_soon'] }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">within the requal window</div></div></div>
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Past Due</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:#C8102E;line-height:1;">{{ $stats['past_due'] }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">lapsed or lapsing</div></div></div>
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-wrench-screwdriver"/> Data Gaps</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:{{ $totalGaps > 0 ? '#C8102E' : '#2E7D5B' }};line-height:1;">{{ $totalGaps }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">records need attention</div></div></div>
        </div>
    @endif

    {{-- Row detail modal: rich info before drilling into the full record --}}
    @if($rowDetail)
        <div class="gqs-modal-overlay" wire:click.self="closeRowDetail">
            <div class="gqs-modal" style="width:660px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,{{ $rowDetail['stage_color'] }},{{ $rowDetail['stage_color'] }}CC);padding:18px 20px;border-radius:14px 14px 0 0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-weight:800;font-size:19px;color:#fff;">{{ $rowDetail['name'] }}</div>
                        <div style="font-size:12px;color:rgba(255,255,255,.92);">{{ $rowDetail['employee_id'] }}@if($rowDetail['job_title']) · {{ $rowDetail['job_title'] }}@endif@if($rowDetail['department']) · {{ $rowDetail['department'] }}@endif</div>
                    </div>
                    <span style="background:rgba(255,255,255,.22);color:#fff;font-weight:700;font-size:12px;padding:5px 12px;border-radius:999px;white-space:nowrap;">{{ $rowDetail['stage_label'] }}</span>
                </div>
                <div class="gqs-modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        <div><div class="dm-l">Status</div><div class="dm-v"><span class="gqs-pill" style="background:{{ $rowDetail['status_color'] }}1A;color:{{ $rowDetail['status_color'] }};font-weight:700;">{{ $rowDetail['status_pill'] }}</span></div></div>
                        <div><div class="dm-l">Session Type</div><div class="dm-v">{{ $rowDetail['type'] }}</div></div>
                        <div><div class="dm-l">Runs</div><div class="dm-v">{{ $rowDetail['passes'] }} / {{ $rowDetail['required'] }} passing</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v" style="{{ $rowDetail['past_due'] ? 'color:#C8102E;font-weight:700;' : '' }}">{{ $rowDetail['due'] ?: '-' }}@if($rowDetail['past_due']) (past due)@endif</div></div>
                        <div><div class="dm-l">Qualified Date</div><div class="dm-v">{{ $rowDetail['qualified_date'] ?: 'Not yet (awaiting QA)' }}</div></div>
                        <div><div class="dm-l">QA Owner</div><div class="dm-v">{{ $rowDetail['qa_owner'] ?: 'Unassigned' }}</div></div>
                        <div><div class="dm-l">Class On File</div><div class="dm-v">@if($rowDetail['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span> {{ $rowDetail['class_on_file_date'] }}@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                        <div style="grid-column:span 2;"><div class="dm-l">LIMS Worklist</div><div class="dm-v">
                            @if($rowDetail['worklist']){{ $rowDetail['worklist'] }}
                            @elseif($rowDetail['needs_worklist'])<button type="button" wire:click="openLinkWorklist({{ $rowDetail['id'] }})" class="sb-act" style="background:#1F6FB2;">+ Link Worklist</button>
                            @else <span style="color:var(--gqs-text-dim,#9A9AA4);">Not linked yet</span>@endif
                        </div></div>
                    </div>

                    @if(count($rowDetail['runs']))
                        <div class="dm-l" style="margin-top:18px;">Run History</div>
                        <table class="gqs-tbl" style="margin-top:6px;">
                            <thead><tr><th>Date</th><th>Result</th><th>Worklist</th><th>Inc Status</th><th>Evaluation</th><th>NC</th></tr></thead>
                            <tbody>@foreach($rowDetail['runs'] as $r)
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
                    <button wire:click="closeRowDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                    <span style="display:flex;gap:8px;">
                        @if(!empty($rowDetail['review_url']))
                            <a href="{{ $rowDetail['review_url'] }}" class="gqs-btn" style="background:#1F6FB2;color:#fff;text-decoration:none;">{{ $rowDetail['review_label'] }}</a>
                        @endif
                        <a href="{{ $rowDetail['record_url'] }}" class="gqs-btn gqs-btn-primary" style="text-decoration:none;">Open Full Record</a>
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- Link Worklist modal --}}
    @if($wlQid)
        <div class="gqs-modal-overlay" wire:click.self="closeLinkWorklist">
            <div class="gqs-modal" style="width:430px;max-width:94vw;">
                <div style="background:linear-gradient(135deg,#1F6FB2,#185A92);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:17px;color:#fff;">Link LIMS Worklist</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $this->wlPersonName() }}</div>
                </div>
                <div class="gqs-modal-body">
                    <label class="gqs-flbl">LIMS Worklist <span style="color:#C8102E;">*</span></label>
                    <div style="display:flex;align-items:stretch;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;overflow:hidden;">
                        <span style="display:flex;align-items:center;padding:0 12px;background:var(--gqs-surface-2,#F1F1F4);font-weight:800;color:var(--gqs-text-dim,#6A6A72);border-right:1px solid var(--gqs-border,#C4C4CC);">EM-</span>
                        <input type="text" wire:model.live.debounce.250ms="wlValue" class="gqs-fld" list="wl-suggest" style="border:none;border-radius:0;flex:1;" placeholder="type the numbers" wire:keydown.enter="saveLinkWorklist" autofocus>
                    </div>
                    <datalist id="wl-suggest">
                        @foreach($this->worklistSuggestions() as $s)<option value="{{ $s }}"></option>@endforeach
                    </datalist>
                    <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:6px;">Just type the numbers - EM- is added automatically. Matching worklists suggest as you type. Links the run so incubation status, evaluation, and NC data sync.</div>
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closeLinkWorklist" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button wire:click="saveLinkWorklist" class="gqs-btn gqs-btn-primary">Link Worklist</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Onboarding modal --}}
    @if($onboardPersonId)
        <div class="gqs-modal-overlay" wire:click.self="closeOnboard">
            <div class="gqs-modal" style="width:560px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#2E7D5B,#225F46);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:17px;color:#fff;">Set Up Qualification</div>
                    <div style="font-size:12px;color:#D7EFE4;">{{ $this->onboardPersonName() }} - kick them into the workflow</div>
                </div>
                <div class="gqs-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="gqs-flbl">Qualification Type</label>
                        <select wire:model.live="onboard.type" class="gqs-fld">
                            <option value="initial">Initial Gowning Qualification (3 runs)</option>
                            <option value="annual">Requalification Transfer (already qualified)</option>
                        </select>
                    </div>
                    <div>
                        <label class="gqs-flbl">{{ ($onboard['type'] ?? 'initial') === 'annual' ? 'Next Requalification Due Date' : 'Initial Qualification Must Be Completed By' }}</label>
                        <input type="date" wire:model="onboard.due_date" class="gqs-fld">
                    </div>
                    @if(($onboard['type'] ?? 'initial') === 'annual')
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" wire:model.live="onboard.class_done"> Already took the gowning class
                        </label>
                        @if($onboard['class_done'] ?? false)
                            <div>
                                <label class="gqs-flbl">Class Completion Date</label>
                                <input type="date" wire:model="onboard.class_date" class="gqs-fld">
                                <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">Recorded to their class completion history; they skip the class step.</div>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closeOnboard" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button wire:click="saveOnboard" class="gqs-btn gqs-btn-primary">Create Qualification</button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ar-fix{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#E2E2E8);border-top:3px solid var(--fix);border-radius:12px;padding:14px 16px;}
        .ar-fix-h{display:flex;align-items:center;gap:7px;font-size:13.5px;font-weight:800;color:var(--gqs-text,#1A1A1F);}
        .ar-fix-h svg{width:18px;height:18px;color:var(--fix);}
        .ar-fix-n{color:var(--fix);font-size:18px;}
        .ar-fix-sub{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin:5px 0 10px;line-height:1.4;}
        .ar-fix-list{display:flex;flex-direction:column;gap:5px;max-height:230px;overflow-y:auto;}
        .ar-fix-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;font-weight:600;padding:7px 11px;border-radius:8px;border:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F8F8FA);color:var(--gqs-text,#1A1A1F);cursor:pointer;text-align:left;width:100%;}
        .ar-fix-btn:hover{border-color:var(--fix);}
        .ar-fix-go{color:var(--fix);font-weight:800;white-space:nowrap;}
        .ar-funnel{display:flex;align-items:flex-end;gap:10px;min-height:190px;}
        .ar-fcell{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;min-width:0;}
        .ar-fbar-wrap{width:100%;height:140px;display:flex;align-items:flex-end;justify-content:center;}
        .ar-fbar{width:70%;max-width:48px;border-radius:7px 7px 3px 3px;transition:height .3s ease;min-height:3px;}
        .ar-fnum{font-size:19px;font-weight:800;line-height:1;}
        .ar-flbl{font-size:10.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);text-align:center;line-height:1.2;}
    </style>
</x-filament-panels::page>
