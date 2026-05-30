<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Reports', 'subtitle' => 'Compliance reports and exports.', 'icon' => 'heroicon-o-chart-bar'])
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:8px;">
        @php $pf = $this->passFail; @endphp
        <div style="background:linear-gradient(135deg,#C8102E,#9A0C23);color:#fff;border-radius:14px;padding:18px;">
            <div style="font-size:28px;font-weight:800;">{{ $this->overdue->count() }}</div><div>Overdue</div>
        </div>
        <div style="background:linear-gradient(135deg,#6B2C91,#4E1F6B);color:#fff;border-radius:14px;padding:18px;">
            <div style="font-size:28px;font-weight:800;">{{ $this->upcoming->count() }}</div><div>Due in 60 Days</div>
        </div>
        <div style="background:linear-gradient(135deg,#2E7D5B,#246148);color:#fff;border-radius:14px;padding:18px;">
            <div style="font-size:28px;font-weight:800;">{{ $pf['pass'] ?? 0 }}</div><div>Total Passes</div>
        </div>
        <div style="background:linear-gradient(135deg,#C79A2E,#9E7818);color:#fff;border-radius:14px;padding:18px;">
            <div style="font-size:28px;font-weight:800;">{{ $pf['fail'] ?? 0 }}</div><div>Total Fails</div>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Overdue Qualifications</x-slot>
        @if ($this->overdue->isEmpty())<p style="color:var(--gray-500)">None overdue.</p>@else
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead><tr style="text-align:left;border-bottom:2px solid #C8102E;"><th style="padding:7px;">Employee</th><th>Name</th><th>Due</th></tr></thead>
                <tbody>@foreach ($this->overdue as $q)<tr style="border-bottom:1px solid #E0E0E6;"><td style="padding:7px;">{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td><td style="color:#C8102E;font-weight:600;">{{ $q->due_date?->format('M j, Y') }}</td></tr>@endforeach</tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section style="margin-top:16px;">
        <x-slot name="heading">Upcoming (Next 60 Days)</x-slot>
        @if ($this->upcoming->isEmpty())<p style="color:var(--gray-500)">None upcoming.</p>@else
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead><tr style="text-align:left;border-bottom:2px solid #6B2C91;"><th style="padding:7px;">Employee</th><th>Name</th><th>Due</th></tr></thead>
                <tbody>@foreach ($this->upcoming as $q)<tr style="border-bottom:1px solid #E0E0E6;"><td style="padding:7px;">{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td><td>{{ $q->due_date?->format('M j, Y') }}</td></tr>@endforeach</tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section style="margin-top:16px;">
        <x-slot name="heading">Class Completions by Class</x-slot>
        @foreach ($this->classStats as $row)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #E0E0E6;">
                <span>{{ $row->class_name }}</span><strong>{{ $row->n }}</strong>
            </div>
        @endforeach
    </x-filament::section>

    <x-filament::section style="margin-top:16px;">
        <x-slot name="heading">LIMS Handoff Export</x-slot>
        <x-slot name="description">Download recent run results as CSV for LIMS / records.</x-slot>
        <x-filament::button wire:click="exportRuns" icon="heroicon-m-arrow-down-tray">Export Run Results (CSV)</x-filament::button>
    </x-filament::section>
</x-filament-panels::page>
