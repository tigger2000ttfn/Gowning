<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Active Bookings', 'icon' => 'heroicon-o-user-group'])

    @php $stats = $this->stats(); $rows = $this->rows(); @endphp

    <div class="gqs-stats">
        <div class="gqs-stat charcoal"><div class="n">{{ $stats['total'] }}</div><div class="l">Active Bookings</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
        <div class="gqs-stat" style="--g1:#1F6FB2;--g2:#185A92;"><div class="n">{{ $stats['scheduled'] }}</div><div class="l">Scheduled</div><span class="wm"><x-filament::icon icon="heroicon-o-calendar"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $stats['attended'] }}</div><div class="l">Attended</div><span class="wm"><x-filament::icon icon="heroicon-o-check"/></span></div>
        <div class="gqs-stat" style="--g1:#2563EB;--g2:#1E50C0;"><div class="n">{{ $stats['qcm'] }}</div><div class="l">QCM Reviewed</div><span class="wm"><x-filament::icon icon="heroicon-o-shield-check"/></span></div>
        <div class="gqs-stat" style="--g1:#6B2C91;--g2:#54226F;"><div class="n">{{ $stats['pending_qa'] }}</div><div class="l">Pending QA</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head" style="display:flex;align-items:center;gap:12px;">
            <x-filament::icon icon="heroicon-m-user-group"/> People In An Active Class
            <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                <label style="font-size:12px;font-weight:600;opacity:.9;">Status</label>
                <select wire:model.live="filterStatus" class="gqs-fld" style="width:auto;min-width:150px;padding:5px 10px;">
                    @foreach($this->statusOptions() as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </span>
        </div>
        <div class="gqs-panel-body">
            @if(empty($rows))
                <div class="gqs-empty">No active class bookings. People appear here from sign-up through QA approval, then move to Class Completions.</div>
            @else
                <table class="gqs-tbl">
                    <thead><tr><th>Employee</th><th>Name</th><th>Department</th><th>Class</th><th>Session Date</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr wire:key="ab-{{ $row['id'] }}-{{ $row['status'] }}">
                                <td style="font-weight:600;">{{ $row['employee_id'] ?: '—' }}</td>
                                <td>
                                    @if($row['personnel_id'])
                                        <button type="button" wire:click="showPersonDetail({{ $row['personnel_id'] }})" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;">{{ $row['name'] }}</button>
                                    @else {{ $row['name'] }} @endif
                                </td>
                                <td>{{ $row['department'] ?: '—' }}</td>
                                <td>{{ $row['class'] }}</td>
                                <td style="white-space:nowrap;">{{ $row['session_date'] ?: '—' }}@if($row['session_past'] && in_array($row['status'], ['signed_up'])) <span class="gqs-pill gqs-pill-red" style="margin-left:4px;">Past</span>@endif</td>
                                <td><span class="gqs-pill" style="background:{{ $row['status_color'] }}1A;color:{{ $row['status_color'] }};font-weight:700;">{{ $row['status_label'] }}</span></td>
                                <td style="text-align:right;white-space:nowrap;">
                                    @if($row['personnel_id'])
                                        <button type="button" wire:click="showPersonDetail({{ $row['personnel_id'] }})" class="sb-act" style="background:#1C1C21;">Details</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Per-person detail modal (shared trait) --}}
    @if($personDetail)
        <div class="gqs-modal-overlay" wire:click.self="closePersonDetail">
            <div class="gqs-modal" style="width:640px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#1C1C21,#34343D);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:18px;color:#fff;">{{ $personDetail['name'] }}</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $personDetail['employee_id'] }}@if($personDetail['job_title']) · {{ $personDetail['job_title'] }}@endif</div>
                </div>
                <div class="gqs-modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div><div class="dm-l">Department</div><div class="dm-v">{{ $personDetail['department'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Stage</div><div class="dm-v">{{ $personDetail['stage'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $personDetail['status'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Session Type</div><div class="dm-v">{{ $personDetail['type'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $personDetail['due'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Class Completed</div><div class="dm-v">{{ $personDetail['class_date'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Class On File</div><div class="dm-v">@if($personDetail['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span>@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                    </div>
                    @if(count($personDetail['enrollments']))
                        <div class="dm-l" style="margin-top:18px;">Class Enrollments</div>
                        <table class="gqs-tbl" style="margin-top:6px;">
                            <thead><tr><th>Class</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>@foreach($personDetail['enrollments'] as $e)
                                <tr><td>{{ $e['class'] }}</td><td>{{ $e['date'] ?: '—' }}</td><td>{{ $e['status'] }}</td></tr>
                            @endforeach</tbody>
                        </table>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closePersonDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                    <a href="{{ $personDetail['view_url'] }}" class="gqs-btn gqs-btn-primary" style="text-decoration:none;">Open Record</a>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
