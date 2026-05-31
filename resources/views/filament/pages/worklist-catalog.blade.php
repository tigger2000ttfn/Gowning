<x-filament-panels::page>
    @php $tab = $this->tab ?? 'upload'; @endphp

    @include('filament.page-hero', ['title' => 'Worklist Catalog', 'icon' => 'heroicon-o-beaker', 'actions' => '
        <button type="button" wire:click="setTab(\'upload\')" class="gqs-tab ' . ($tab === 'upload' ? 'active' : '') . '">Upload</button>
        <button type="button" wire:click="setTab(\'catalog\')" class="gqs-tab ' . ($tab === 'catalog' ? 'active' : '') . '">Catalog</button>
        <button type="button" wire:click="setTab(\'sql\')" class="gqs-tab ' . ($tab === 'sql' ? 'active' : '') . '">SQL Query</button>
    '])

    @if ($tab === 'upload')
        <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
            <span class="gqs-pill {{ ! $parsed && ! $imported ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">1 · Upload</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $parsed ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">2 · Review &amp; Import</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $imported ? 'gqs-pill-green' : 'gqs-pill-gray' }}">3 · Done</span>
        </div>

        @if (! $parsed && ! $imported)
            <div class="gqs-panel"
                 x-data="{ busy: false }"
                 x-on:form-processing-started="busy = true"
                 x-on:form-processing-finished="busy = false">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-up-tray"/> Upload LIMS Worklist Export</div>
                <div class="gqs-panel-body" style="padding:16px;position:relative;">
                    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:10px 0 18px;">
                        <span style="width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2E7D5B,#1C5740);box-shadow:0 10px 28px rgba(46,125,91,.32);margin-bottom:14px;">
                            <x-filament::icon icon="heroicon-o-beaker" style="width:38px;height:38px;color:#fff;"/>
                        </span>
                        <div style="font-size:16px;font-weight:800;color:var(--gqs-text,#1A1A1F);">Drop Your LIMS Worklist Export Here</div>
                        <p style="margin:6px 0 0;font-size:13px;color:var(--gqs-text-dim,#6A6A72);max-width:460px;">Upload the LabWare EM export (.xlsx or .csv). Columns are detected automatically. Each refresh brings in newer LIMS data that drives linked runs forward.</p>
                    </div>
                    <form wire:submit.prevent>{{ $this->form }}</form>
                    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <span style="font-size:12px;color:#2E7D5B;font-weight:600;" x-show="busy" x-cloak>Uploading file, please wait...</span>
                        <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">{{ $this->catalogCount() }} in catalog</span>
                        <button type="button" wire:click="parse" wire:loading.attr="disabled" wire:target="parse" class="gqs-btn gqs-btn-primary" style="margin-left:auto;"
                                x-bind:disabled="busy"
                                x-bind:style="busy ? 'opacity:.5;cursor:wait;' : ''">
                            <span x-show="busy" x-cloak>Uploading...</span>
                            <span x-show="!busy">Parse</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if ($parsed)
            <div class="gqs-panel" style="position:relative;">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Review &amp; Import</div>
                <div class="gqs-panel-body" style="padding:0;">
                    <p style="margin:0;padding:12px 16px 6px;color:var(--gqs-text-dim,#6A6A72);font-size:13px;">Detected {{ $rowCount }} rows. Showing the first {{ count($preview) }}.</p>
                    <div style="overflow-x:auto;">
                        <table class="gqs-tbl">
                            <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($preview as $row)
                                <tr>@foreach ($row as $cell)<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $cell }}">{{ \Illuminate\Support\Str::limit((string) $cell, 50) }}</td>@endforeach</tr>
                            @endforeach</tbody>
                        </table>
                    </div>
                    <div style="padding:14px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" class="gqs-btn gqs-btn-primary" style="margin-left:auto;">
                            <span wire:loading.remove wire:target="import">Import {{ $rowCount }} Rows</span>
                            <span wire:loading wire:target="import">Importing...</span>
                        </button>
                    </div>
                </div>
                <div wire:loading.flex wire:target="import" class="gqs-import-overlay">
                    <div class="gqs-import-card">
                        <div class="gqs-spinner"></div>
                        <div style="font-weight:700;color:#fff;margin-top:12px;">Importing {{ $rowCount }} worklists...</div>
                        <div style="font-size:12px;color:#C9C9D2;margin-top:4px;">Updating the catalog and syncing linked runs</div>
                    </div>
                </div>
            </div>
        @endif

        @if ($imported)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="background:linear-gradient(135deg,#2E7D5B,#225F46);"><x-filament::icon icon="heroicon-m-check-circle"/> Import Complete</div>
                <div class="gqs-panel-body" style="padding:16px;">
                    <p style="margin:0 0 14px;color:#1E7A52;font-size:13.5px;">Worklist catalog updated: added {{ $lastCreated }}, updated {{ $lastUpdated }}@if($lastSkipped), skipped {{ $lastSkipped }}@endif.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" wire:click="setTab('catalog')" class="gqs-btn gqs-btn-primary">View Catalog</button>
                        <button type="button" wire:click="runSync" class="gqs-btn gqs-btn-ghost">Sync Linked Runs Now</button>
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Upload Another</button>
                    </div>
                </div>
            </div>
        @endif
    @elseif ($tab === 'catalog')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-beaker"/> Worklist Catalog
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $this->catalogCount() }} worklists</span>
            </div>
            <div class="gqs-panel-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                    <input type="text" wire:model.live.debounce.300ms="search" class="gqs-fld" placeholder="Search worklist, person, description..." style="flex:1;min-width:200px;max-width:340px;">
                    <select wire:model.live="filterType" class="gqs-fld" style="max-width:160px;">
                        <option value="">All Types</option>
                        <option value="initial">Initial</option>
                        <option value="annual">Annual Requal</option>
                        <option value="additional">Additional Requal</option>
                        <option value="routine">Routine EM</option>
                    </select>
                    <select wire:model.live="filterStatus" class="gqs-fld" style="max-width:150px;">
                        <option value="">All Statuses</option>
                        <option value="A">Authorized (A)</option>
                        <option value="P">Pending (P)</option>
                        <option value="I">Incomplete (I)</option>
                        <option value="C">Complete (C)</option>
                        <option value="X">Cancelled (X)</option>
                    </select>
                    <select wire:model.live="filterReady" class="gqs-fld" style="max-width:140px;">
                        <option value="">QCM: Any</option>
                        <option value="ready">QCM Ready</option>
                        <option value="not">Not Ready</option>
                    </select>
                    <select wire:model.live="filterLegacy" class="gqs-fld" style="max-width:150px;">
                        <option value="">All Rows</option>
                        <option value="legacy">Legacy Only</option>
                        <option value="active">Active Only</option>
                        <option value="nonreportable">Non-Reportable</option>
                    </select>
                    <button type="button" wire:click="runSync" class="gqs-btn gqs-btn-ghost">Sync Linked Runs</button>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;padding:12px;border:1px solid var(--gqs-border,#E2E2E8);border-radius:10px;background:var(--gqs-surface-2,#F7F7F9);">
                    <span style="font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);text-transform:uppercase;letter-spacing:.04em;">Historic Backfill</span>
                    <select wire:model="backfillPersonId" class="gqs-fld" style="max-width:300px;">
                        <option value="">All personnel (bulk, one-time)</option>
                        @foreach($this->backfillPersonOptions() as $pid => $lbl)<option value="{{ $pid }}">{{ $lbl }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="previewBackfill" class="gqs-btn gqs-btn-primary">Preview Backfill</button>
                    @if($this->bulkBackfillDone())
                        <span class="gqs-pill gqs-pill-green" title="The one-time bulk backfill has run">Bulk Done</span>
                        <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Pick a person to backfill individuals.</span>
                    @endif
                </div>
                <table class="gqs-tbl wl-tbl">
                    <thead><tr>
                        <th style="width:1%;white-space:nowrap;">Actions</th>
                        <th>Worklist</th>
                        <th>Person</th>
                        <th class="wl-hide-sm">Type</th>
                        <th>Eval</th>
                        <th>Sample</th>
                        <th>Inc</th>
                        <th>QCM</th>
                        <th class="wl-hide-sm">NC Ref</th>
                        <th class="wl-hide-md">Description</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($this->catalogRows() as $d)
                            <tr wire:key="wl-{{ $d['id'] }}-{{ md5($d['worklist'].($d['type']??'').($d['evaluation']??'').($d['sample_status']??'').($d['inc_status']??'').($d['legacy']?'1':'0').($d['non_reportable']?'1':'0')) }}" wire:click="viewRow({{ $d['id'] }})" style="cursor:pointer;">
                                <td style="white-space:nowrap;" wire:click.stop>
                                    <button type="button" wire:click.stop="viewRow({{ $d['id'] }})" class="wl-act" title="View all fields">View</button>
                                    <button type="button" wire:click.stop="editRow({{ $d['id'] }})" class="wl-act" title="Edit fields">Edit</button>
                                    <button type="button" wire:click.stop="toggleLegacy({{ $d['id'] }})" class="wl-act" style="{{ $d['legacy'] ? 'color:#7A3FA4;' : '' }}" title="{{ $d['legacy'] ? 'Allow imports again' : 'Lock from imports' }}">{{ $d['legacy'] ? 'Unlock' : 'Lock' }}</button>
                                </td>
                                <td style="font-weight:700;white-space:nowrap;">{{ $d['worklist'] }}@if($d['legacy']) <span class="gqs-pill gqs-pill-purple" style="font-size:9px;">Legacy</span>@endif@if($d['non_reportable']) <span class="gqs-pill gqs-pill-red" style="font-size:9px;">Non-Reportable</span>@endif</td>
                                <td style="white-space:nowrap;">{{ $d['personnel'] ?: '—' }}</td>
                                <td class="wl-hide-sm" style="white-space:normal;max-width:130px;line-height:1.25;">@if($d['routine'])<span class="gqs-pill gqs-pill-gray">Routine EM</span>@else {{ $d['type'] ?: '—' }}@endif</td>
                                <td>
                                    @if(strcasecmp((string)$d['evaluation'],'pass')===0)<span class="gqs-pill gqs-pill-green">Pass</span>
                                    @elseif(strcasecmp((string)$d['evaluation'],'fail')===0)<span class="gqs-pill gqs-pill-red">Fail</span>
                                    @else <span style="color:var(--gqs-text-dim,#9A9AA4);">—</span>@endif
                                </td>
                                <td>@if($d['sample_status']==='Authorized')<span class="gqs-pill gqs-pill-green">A</span>@else<span class="gqs-pill gqs-pill-gold">{{ $d['sample_status'] }}</span>@endif</td>
                                <td>@if($d['inc_status']==='Authorized')<span class="gqs-pill gqs-pill-green">A</span>@else<span class="gqs-pill gqs-pill-gold">{{ $d['inc_status'] }}</span>@endif</td>
                                <td>@if($d['qcm_ready'])<span class="gqs-pill gqs-pill-green">Ready</span>@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</td>
                                <td class="wl-hide-sm" style="white-space:nowrap;">{{ $d['reference'] ?: '—' }}</td>
                                <td class="wl-hide-md" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $d['description'] }}">{{ $d['description'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" style="text-align:center;padding:18px;color:var(--gqs-text-dim,#6A6A72);">No worklists match. Adjust filters, or load a LIMS export on the Upload tab.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-circle-stack"/> LIMS SQL Query
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">Reference</span>
            </div>
            <div class="gqs-panel-body" style="padding:16px;">
                <p style="margin:0 0 12px;font-size:13px;color:var(--gqs-text-dim,#6A6A72);">The LabWare query that produces the worklist export loaded on the Upload tab. Stored here for reference and easy copy. Edit and Save to keep your current version.</p>
                <textarea wire:model="sqlQuery" spellcheck="false" style="width:100%;min-height:420px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.5;padding:12px 14px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:10px;background:#1C1C21;color:#E6E6EA;resize:vertical;"></textarea>
                <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" wire:click="saveSql" class="gqs-btn gqs-btn-primary">Save Query</button>
                    <button type="button"
                            x-data
                            x-on:click="navigator.clipboard.writeText($wire.sqlQuery); $el.textContent='Copied'; setTimeout(()=>$el.textContent='Copy To Clipboard',1500)"
                            class="gqs-btn gqs-btn-ghost">Copy To Clipboard</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Historic backfill preview --}}
    @if($showBackfill)
        <div class="gqs-modal-overlay" wire:click.self="closeBackfill">
            <div class="gqs-modal" style="width:760px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#2E7D5B,#225F46);padding:16px 20px;display:flex;align-items:center;gap:12px;border-radius:14px 14px 0 0;">
                    <span style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <x-filament::icon icon="heroicon-o-clock" style="width:26px;height:26px;color:#fff;"/>
                    </span>
                    <div>
                        <div style="font-weight:800;font-size:17px;color:#fff;">Backfill Historic Qualifications</div>
                        <div style="font-size:12px;color:#D7EFE4;">@if($backfillPersonId){{ $this->backfillPersonOptions()[$backfillPersonId] ?? 'Selected person' }} only@else All personnel (one-time bulk)@endif</div>
                    </div>
                </div>
                <div class="gqs-modal-body">
                    @php $bp = $backfillPreview; @endphp
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px;">
                        <div class="bf-stat"><div class="bf-n">{{ $bp['quals'] ?? 0 }}</div><div class="bf-l">Qualifications</div></div>
                        <div class="bf-stat"><div class="bf-n">{{ $bp['created'] ?? 0 }}</div><div class="bf-l">Run Records</div></div>
                        <div class="bf-stat"><div class="bf-n">{{ $bp['matched'] ?? 0 }}</div><div class="bf-l">Matched</div></div>
                        <div class="bf-stat"><div class="bf-n" style="color:#C79A2E;">{{ $bp['unmatched'] ?? 0 }}</div><div class="bf-l">Unmatched</div></div>
                        <div class="bf-stat"><div class="bf-n" style="color:#9A9AA4;">{{ $bp['skipped'] ?? 0 }}</div><div class="bf-l">Skipped</div></div>
                    </div>
                    <p style="margin:0 0 10px;font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">Passing runs land in Lab Review (Results Released) for QCM review and cover-page creation. They are not submitted to QA. Unmatched rows are left for manual handling. This runs only for personnel already in the system.</p>
                    <div style="overflow:auto;max-height:300px;border:1px solid var(--gqs-border,#E2E2E8);border-radius:10px;">
                        <table class="gqs-tbl">
                            <thead><tr><th>Worklist</th><th>Person</th><th>Type</th><th>Runs</th><th>Eval</th><th>Action</th></tr></thead>
                            <tbody>
                                @forelse($bp['rows'] ?? [] as $r)
                                    <tr>
                                        <td style="font-weight:700;white-space:nowrap;">{{ $r['worklist'] }}</td>
                                        <td>{{ $r['person'] ?: '—' }}</td>
                                        <td>{{ $r['type'] ?? '—' }}</td>
                                        <td>{{ $r['runs'] ?? '—' }}</td>
                                        <td>{{ $r['eval'] ?? '—' }}</td>
                                        <td>@if(($r['status'] ?? '')==='unmatched')<span class="gqs-pill gqs-pill-gold">Unmatched</span>@else<span class="gqs-pill gqs-pill-green">Create</span>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" style="text-align:center;padding:16px;color:var(--gqs-text-dim,#6A6A72);">Nothing to backfill.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeBackfill" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="runBackfill" wire:loading.attr="disabled" wire:target="runBackfill" class="gqs-btn gqs-btn-primary">
                        <span wire:loading.remove wire:target="runBackfill">Create {{ $bp['created'] ?? 0 }} Run(s)</span>
                        <span wire:loading wire:target="runBackfill">Working...</span>
                    </button>
                </div>
            </div>
        </div>
        <style>
            .bf-stat{background:var(--gqs-surface-2,#F3F3F6);border:1px solid var(--gqs-border,#E2E2E8);border-radius:10px;padding:10px 16px;min-width:96px;text-align:center;}
            .bf-n{font-size:22px;font-weight:800;color:#2E7D5B;line-height:1;}
            .bf-l{font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;text-transform:uppercase;letter-spacing:.04em;}
        </style>
    @endif

    {{-- Full-row detail (click a row) --}}
    @php $vr = $this->viewRecord(); @endphp
    @if($vr)
        @php $vm = $this->viewMatchedPerson(); @endphp
        <div class="gqs-modal-overlay" wire:click.self="closeView">
            <div class="gqs-modal" style="width:820px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#2E7D5B,#225F46);padding:16px 20px;display:flex;align-items:center;gap:12px;border-radius:14px 14px 0 0;">
                    <span style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <x-filament::icon icon="heroicon-o-beaker" style="width:26px;height:26px;color:#fff;"/>
                    </span>
                    <div style="min-width:0;">
                        <div style="font-weight:800;font-size:17px;color:#fff;">{{ $vr['worklist'] }}@if($vr['legacy']) <span class="gqs-pill gqs-pill-purple" style="font-size:10px;">Legacy</span>@endif</div>
                        <div style="font-size:12px;color:#D7EFE4;">@if($vr['qcm_ready'])LIMS: Pass - Authorized - Incubation Complete @else Full imported worklist record @endif</div>
                    </div>
                    <button wire:click="closeView" style="margin-left:auto;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;opacity:.8;">&times;</button>
                </div>
                <div class="gqs-modal-body" style="max-height:64vh;overflow:auto;">
                    @foreach($vr['groups'] as $groupName => $fields)
                        <div style="margin-bottom:16px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#2E7D5B;margin-bottom:8px;border-bottom:1px solid var(--gqs-border,#E2E2E8);padding-bottom:4px;">{{ $groupName }}</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;">
                                @foreach($fields as $label => $value)
                                    <div style="display:flex;gap:8px;font-size:12.5px;">
                                        <span style="color:var(--gqs-text-dim,#6A6A72);min-width:160px;flex-shrink:0;">{{ $label }}</span>
                                        <span style="font-weight:600;color:var(--gqs-text,#1A1A1F);word-break:break-word;">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" wire:click="editRow({{ $vr['id'] }})" class="gqs-btn gqs-btn-ghost">Edit</button>
                        <button type="button" wire:click="toggleLegacy({{ $vr['id'] }})" class="gqs-btn gqs-btn-ghost">{{ $vr['legacy'] ? 'Unlock (allow import)' : 'Mark Legacy' }}</button>
                        <button type="button" wire:click="toggleNonReportable({{ $vr['id'] }})" class="gqs-btn gqs-btn-ghost" style="{{ $vr['non_reportable'] ?? false ? 'color:#A4123F;border-color:#A4123F;' : '' }}">{{ ($vr['non_reportable'] ?? false) ? 'Clear Non-Reportable' : 'Mark Non-Reportable' }}</button>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        @if($vr['non_reportable'] ?? false)
                            <span style="font-size:12px;color:#A4123F;font-weight:600;">Non-reportable - won't link to anyone</span>
                        @elseif($vm)
                            <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Matches {{ $vm['name'] }}</span>
                            <button type="button" wire:click="backfillThisPerson" class="gqs-btn gqs-btn-primary">Backfill This Person</button>
                        @else
                            <span style="font-size:12px;color:#C79A2E;">No single person match</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Hand-edit a worklist row (marks it legacy) --}}
    @if($editId)
        <div class="gqs-modal-overlay" wire:click.self="closeEdit">
            <div class="gqs-modal" style="width:720px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#7A3FA4,#5E2F80);padding:16px 20px;display:flex;align-items:center;gap:12px;border-radius:14px 14px 0 0;">
                    <span style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <x-filament::icon icon="heroicon-o-pencil-square" style="width:26px;height:26px;color:#fff;"/>
                    </span>
                    <div>
                        <div style="font-weight:800;font-size:17px;color:#fff;">Edit Worklist (Legacy)</div>
                        <div style="font-size:12px;color:#EBDDF5;">Saving locks this row from future imports.</div>
                    </div>
                </div>
                <div class="gqs-modal-body" style="max-height:62vh;overflow:auto;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="grid-column:1 / -1;"><label class="gqs-flbl">Description</label><input type="text" wire:model="editData.worklist_description" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">Person (LIMS Login)</label><input type="text" wire:model="editData.personnel" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">Qualification Type</label>
                            <select wire:model="editData.qualification_type" class="gqs-fld">
                                <option value="">— (none / routine)</option>
                                <option value="Initial Gowning Qualification">Initial Gowning Qualification</option>
                                <option value="Annual Requalification">Annual Requalification</option>
                                <option value="Additional Requalification">Additional Requalification</option>
                            </select></div>
                        <div><label class="gqs-flbl">Evaluation</label>
                            <select wire:model="editData.evaluation" class="gqs-fld"><option value="">—</option><option value="Pass">Pass</option><option value="Fail">Fail</option></select></div>
                        <div><label class="gqs-flbl">EM Area</label><input type="text" wire:model="editData.em_area" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">Sample Status</label>
                            <select wire:model="editData.sample_status" class="gqs-fld"><option value="">—</option><option value="A">A — Authorized</option><option value="C">C — Complete</option><option value="I">I — Incomplete</option><option value="P">P — Pending</option><option value="X">X — Cancelled</option></select></div>
                        <div><label class="gqs-flbl">Incubation Status</label>
                            <select wire:model="editData.inc_sample_status" class="gqs-fld"><option value="">—</option><option value="A">A — Authorized</option><option value="C">C — Complete</option><option value="I">I — Incomplete</option><option value="P">P — Pending</option><option value="X">X — Cancelled</option></select></div>
                        <div><label class="gqs-flbl">Worklist All Final</label>
                            <select wire:model="editData.worklist_all_final" class="gqs-fld"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                        <div><label class="gqs-flbl">CR Grade 1</label><input type="text" wire:model="editData.cr_grade_1" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">CR Grade 2</label><input type="text" wire:model="editData.cr_grade_2" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">CR Grade 3</label><input type="text" wire:model="editData.cr_grade_3" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">Qual Date 1</label><input type="text" wire:model="editData.qual_date_1" class="gqs-fld" placeholder="M/D/YYYY"></div>
                        <div><label class="gqs-flbl">Qual Date 2</label><input type="text" wire:model="editData.qual_date_2" class="gqs-fld" placeholder="M/D/YYYY"></div>
                        <div><label class="gqs-flbl">Qual Date 3</label><input type="text" wire:model="editData.qual_date_3" class="gqs-fld" placeholder="M/D/YYYY"></div>
                        <div><label class="gqs-flbl">Run 2 Rescheduled?</label>
                            <select wire:model="editData.run2_rescheduled" class="gqs-fld"><option value="">—</option><option value="No">No</option><option value="Yes">Yes</option></select></div>
                        <div><label class="gqs-flbl">Run 3 Rescheduled?</label>
                            <select wire:model="editData.run3_rescheduled" class="gqs-fld"><option value="">—</option><option value="No">No</option><option value="Yes">Yes</option></select></div>
                        <div><label class="gqs-flbl">Qual Reference (NC)</label><input type="text" wire:model="editData.qual_reference" class="gqs-fld"></div>
                        <div><label class="gqs-flbl">Inc Reference</label><input type="text" wire:model="editData.inc_reference" class="gqs-fld"></div>
                    </div>
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <button type="button" wire:click="closeEdit" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="saveRow" class="gqs-btn gqs-btn-primary">Save &amp; Lock</button>
                </div>
            </div>
        </div>
    @endif
    <style>
        .wl-act{font-size:11px;font-weight:700;padding:3px 9px;border-radius:6px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);cursor:pointer;margin-right:4px;}
        .wl-act:hover{background:var(--gqs-surface-2,#F3F3F6);}
        .wl-tbl{width:100%;table-layout:auto;}
        @media (max-width:1100px){ .wl-hide-md{display:none;} }
        @media (max-width:820px){ .wl-hide-sm{display:none;} }
    </style>
</x-filament-panels::page>
