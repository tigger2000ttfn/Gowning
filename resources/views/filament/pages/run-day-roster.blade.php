<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Run Day', 'subtitle' => "Who's scheduled for each run slot.", 'icon' => 'heroicon-o-clipboard-document-list'])

    <div style="margin-bottom:18px;max-width:260px;">
        <label style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:6px;">Select Date</label>
        <input type="date" wire:model.live="date"
               style="width:100%;padding:10px 12px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
    </div>

    @php $slots = $this->slots; @endphp

    @if ($slots->isEmpty())
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Qualification Run Slots Scheduled For This Date.</div></div>
    @else
        @foreach ($slots as $slot)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:9px;">
                        <x-filament::icon icon="heroicon-m-beaker"/>
                        {{ $slot->cleanroom }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}@endif
                    </span>
                    <span style="font-size:12px;font-weight:600;opacity:.92;">{{ $slot->reservations->count() }} attending · cap {{ $slot->capacity }}</span>
                </div>
                <div class="gqs-panel-body">
                    @if ($slot->reservations->isEmpty())<div class="gqs-empty">No One Scheduled Yet.</div>@else
                        <table class="gqs-tbl">
                            <thead><tr><th>#</th><th>Employee ID</th><th>Name</th><th>Status</th><th>Run</th><th>Results</th></tr></thead>
                            <tbody>@foreach ($slot->reservations as $i => $res)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="font-weight:600;">{{ $res->personnel?->employee_id }}</td>
                                    <td>{{ $res->personnel?->full_name }}</td>
                                    <td><span class="gqs-pill {{ $res->status === 'completed' ? 'gqs-pill-green' : 'gqs-pill-gold' }}">{{ ucfirst($res->status) }}</span></td>
                                    <td>@if($res->status !== 'completed'){{ ($this->markPerformedAction)(['reservation_id' => $res->id]) }}@else<span style="color:#2E7D5B;font-weight:600;font-size:12.5px;">Performed</span>@endif</td>
                                    <td>{{ ($this->recordSamplesAction)(['reservation_id' => $res->id]) }}</td>
                                </tr>
                            @endforeach</tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
