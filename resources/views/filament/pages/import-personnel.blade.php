<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Import LMS Data', 'icon' => 'heroicon-o-arrow-up-tray'])

    <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-bottom:14px;">Import gowning-class completions exported from the LMS. Each row is matched to a person, recorded as a completion, and marks their class on file so they can start qualification runs. Bulk personnel loading is done separately by an administrator.</div>

    <form wire:submit.prevent>{{ $this->form }}</form>

    <div style="margin:14px 0;">
        <button type="button" wire:click="parse" class="gqs-btn gqs-btn-primary">Parse File</button>
    </div>

    @if ($parsed)
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-link"/> 2. Map Columns</div>
            <div class="gqs-panel-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="gqs-flbl">Match Person By</label>
                        <select wire:model="data.map_match_type" class="gqs-fld">
                            <option value="lims">LIMS Username</option>
                            <option value="employee_id">Employee ID (A-Number)</option>
                            <option value="email">Email Address</option>
                        </select>
                    </div>
                    @php $fields = [
                        'map_match' => 'Match Column *',
                        'map_class' => 'Class Name',
                        'map_date'  => 'Completion Date',
                    ]; @endphp
                    @foreach ($fields as $key => $label)
                        <div>
                            <label class="gqs-flbl">{{ $label }}</label>
                            <select wire:model="data.{{ $key }}" class="gqs-fld">
                                @foreach ($this->columnOptions() as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Preview</div>
            <div class="gqs-panel-body" style="padding:0;">
                <p style="margin:0;padding:10px 16px 4px;color:var(--gqs-text-dim,#6A6A72);font-size:13px;">First {{ count($preview) }} of {{ count($rows) }} rows.</p>
                <div style="overflow-x:auto;">
                    <table class="gqs-tbl">
                        <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($preview as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach</tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="margin:14px 0;">
            <button type="button" wire:click="import" class="gqs-btn gqs-btn-primary">Import {{ count($rows) }} Rows</button>
        </div>
    @endif

    @if ($imported)
        <div class="gqs-stats">
            <div class="gqs-stat green"><div class="n">{{ $createdCount }}</div><div class="l">Completions Recorded</div><span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span></div>
            <div class="gqs-stat gold"><div class="n">{{ $skippedCount }}</div><div class="l">Skipped (No Match)</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
        </div>
        @if (! empty($skippedRows))
            <div class="gqs-panel">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Skipped Rows</div>
                <div class="gqs-panel-body" style="padding:0;">
                    <table class="gqs-tbl"><thead><tr><th>Value</th><th>Reason</th></tr></thead>
                        <tbody>@foreach ($skippedRows as $sr)<tr><td>{{ $sr[0] ?? '—' }}</td><td>{{ $sr[1] ?? '' }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>
