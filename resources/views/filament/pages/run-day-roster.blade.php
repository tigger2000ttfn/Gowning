<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Run Day', 'subtitle' => 'Who's scheduled for each run slot.', 'icon' => 'heroicon-o-clipboard-document-list'])
    <div style="margin-bottom:18px;max-width:260px;">
        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Select date</label>
        <input type="date" wire:model.live="date"
               style="width:100%;padding:9px 12px;border:1px solid #C4C4CC;border-radius:9px;">
    </div>

    @php $slots = $this->slots; @endphp

    @if ($slots->isEmpty())
        <x-filament::section>
            <p style="color:var(--gray-500)">No qualification run slots scheduled for this date.</p>
        </x-filament::section>
    @else
        @foreach ($slots as $slot)
            <x-filament::section style="margin-bottom:16px;">
                <x-slot name="heading">
                    {{ $slot->cleanroom }}
                    @if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} @endif
                </x-slot>
                <x-slot name="description">
                    {{ $slot->reservations->count() }} attending · capacity {{ $slot->capacity }}
                </x-slot>

                @if ($slot->reservations->isEmpty())
                    <p style="color:var(--gray-500)">No one scheduled yet.</p>
                @else
                    <table style="width:100%;border-collapse:collapse;font-size:14px;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid #A4123F;">
                                <th style="padding:8px;">#</th>
                                <th style="padding:8px;">Employee ID</th>
                                <th style="padding:8px;">Name</th>
                                <th style="padding:8px;">Status</th>
                                <th style="padding:8px;">Sampling (fingers / chest / forearms)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($slot->reservations as $i => $res)
                                <tr style="border-bottom:1px solid #E0E0E6;">
                                    <td style="padding:8px;">{{ $i + 1 }}</td>
                                    <td style="padding:8px;font-weight:600;">{{ $res->personnel?->employee_id }}</td>
                                    <td style="padding:8px;">{{ $res->personnel?->full_name }}</td>
                                    <td style="padding:8px;">
                                        <span style="font-size:12px;font-weight:600;color:{{ $res->status === 'completed' ? '#2E7D5B' : '#B8860B' }};">
                                            {{ ucfirst($res->status) }}
                                        </span>
                                    </td>
                                    <td style="padding:8px;color:#9A9AA2;">☐ &nbsp; ☐ &nbsp; ☐</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
