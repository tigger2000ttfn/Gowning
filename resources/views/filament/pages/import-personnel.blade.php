<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Import Personnel', 'subtitle' => 'Bulk-load people from CSV.', 'icon' => 'heroicon-o-arrow-up-tray'])

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-up-tray"/> 1. Upload CSV</div>
        <div class="gqs-panel-body" style="padding:16px;">
            <form wire:submit.prevent>
                {{ $this->form }}
                <div style="margin-top:14px;">
                    <x-filament::button wire:click="parse" icon="heroicon-m-magnifying-glass">Parse File</x-filament::button>
                </div>
            </form>
        </div>
    </div>

    @if ($parsed)
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#6B2C91,#4A1E66);"><x-filament::icon icon="heroicon-m-table-cells"/> 2. Map Columns</div>
            <div class="gqs-panel-body" style="padding:16px;">
                <p style="margin:0 0 14px;color:var(--gqs-text-dim,#6A6A72);font-size:13.5px;">Match your CSV columns to the personnel fields. Employee ID is required.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                    @foreach ([
                        'map_employee_id' => 'Employee ID *',
                        'map_first' => 'First Name',
                        'map_last' => 'Last Name',
                        'map_name' => 'Full Name (If Not Split)',
                        'map_email' => 'Email',
                        'map_department' => 'Department',
                    ] as $key => $label)
                        <div>
                            <label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:5px;">{{ $label }}</label>
                            <select wire:model="data.{{ $key }}"
                                    style="width:100%;padding:9px 10px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
                                @foreach ($this->columnOptions() as $val => $name)
                                    <option value="{{ $val }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-eye"/> 3. Preview</div>
            <div class="gqs-panel-body">
                <p style="margin:0;padding:10px 16px 4px;color:var(--gqs-text-dim,#6A6A72);font-size:13px;">First {{ count($preview) }} of {{ count($rows) }} rows.</p>
                <div style="overflow-x:auto;">
                    <table class="gqs-tbl">
                        <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($preview as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach</tbody>
                    </table>
                </div>
                <div style="padding:16px;">
                    <x-filament::button wire:click="import" color="success" icon="heroicon-m-check">Import {{ count($rows) }} Rows</x-filament::button>
                </div>
            </div>
        </div>
    @endif

    @if ($imported)
        <div class="gqs-stats" style="margin-top:18px;">
            <div class="gqs-stat green"><div class="n">{{ $createdCount }}</div><div class="l">Created</div><span class="wm"><x-filament::icon icon="heroicon-o-user-plus"/></span></div>
            <div class="gqs-stat charcoal"><div class="n">{{ $updatedCount }}</div><div class="l">Updated</div><span class="wm"><x-filament::icon icon="heroicon-o-arrow-path"/></span></div>
            <div class="gqs-stat gold"><div class="n">{{ $skippedCount }}</div><div class="l">Skipped</div><span class="wm"><x-filament::icon icon="heroicon-o-minus-circle"/></span></div>
        </div>
    @endif
</x-filament-panels::page>
