<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Import Personnel', 'subtitle' => 'Bulk-load people from CSV with column mapping and preview.', 'icon' => 'heroicon-o-arrow-up-tray'])
    <form wire:submit.prevent>
        {{ $this->form }}
        <div style="margin-top:14px;">
            <x-filament::button wire:click="parse" icon="heroicon-m-magnifying-glass">Parse File</x-filament::button>
        </div>
    </form>

    @if ($parsed)
        <x-filament::section style="margin-top:18px;">
            <x-slot name="heading">2. Map Columns</x-slot>
            <x-slot name="description">Match your CSV columns to the personnel fields. Employee ID is required.</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                @foreach ([
                    'map_employee_id' => 'Employee ID *',
                    'map_first' => 'First name',
                    'map_last' => 'Last name',
                    'map_name' => 'Full name (if not split)',
                    'map_email' => 'Email',
                    'map_department' => 'Department',
                ] as $key => $label)
                    <div>
                        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:5px;">{{ $label }}</label>
                        <select wire:model="data.{{ $key }}"
                                style="width:100%;padding:8px 10px;border:1px solid #C4C4CC;border-radius:8px;">
                            @foreach ($this->columnOptions() as $val => $name)
                                <option value="{{ $val }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section style="margin-top:18px;">
            <x-slot name="heading">3. Preview</x-slot>
            <x-slot name="description">First {{ count($preview) }} of {{ count($rows) }} rows.</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr style="text-align:left;border-bottom:2px solid #A4123F;">
                        @foreach ($headers as $h)<th style="padding:7px 10px;">{{ $h }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr style="border-bottom:1px solid #E0E0E6;">
                                @foreach ($row as $cell)<td style="padding:7px 10px;">{{ $cell }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;">
                <x-filament::button wire:click="import" color="success" icon="heroicon-m-check">
                    Import {{ count($rows) }} Rows
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($imported)
        <x-filament::section style="margin-top:18px;">
            <x-slot name="heading">Import Complete</x-slot>
            <div style="display:flex;gap:24px;font-size:15px;">
                <div><strong style="color:#2E7D5B;font-size:24px;">{{ $createdCount }}</strong><br>Created</div>
                <div><strong style="color:#1F6FB2;font-size:24px;">{{ $updatedCount }}</strong><br>Updated</div>
                <div><strong style="color:#B8860B;font-size:24px;">{{ $skippedCount }}</strong><br>Skipped</div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
