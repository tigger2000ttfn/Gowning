<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Notification Settings', 'subtitle' => 'Choose which events notify you, and how.', 'icon' => 'heroicon-o-bell-alert'])

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-bell"/> My Notification Preferences</div>
        <div class="gqs-panel-body">
            <table class="gqs-tbl">
                <thead><tr><th>Event</th><th style="width:120px;text-align:center;">In-App</th><th style="width:120px;text-align:center;">Email</th></tr></thead>
                <tbody>
                    @foreach($this->events() as $e)
                        <tr>
                            <td>{{ $e['label'] }}</td>
                            <td style="text-align:center;">
                                <input type="checkbox" wire:model="prefs.{{ $e['value'] }}.in_app" style="width:18px;height:18px;cursor:pointer;accent-color:#A4123F;">
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" wire:model="prefs.{{ $e['value'] }}.email" style="width:18px;height:18px;cursor:pointer;accent-color:#A4123F;">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:16px;border-top:1px solid var(--gqs-border,#F2F2F4);">
                <label class="gqs-flbl">Remind Me Before My Scheduled Run / Class</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="number" min="0" max="60" wire:model="reminderDays" class="gqs-fld" style="max-width:110px;">
                    <span style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);">days before (0 = day of). A reminder and calendar invite go out this many days ahead.</span>
                </div>
            </div>
            <div style="padding:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <span style="font-size:12.5px;color:var(--gqs-text-dim,#9A9AA4);">Email is delivered once the mail relay is live; until then emails queue.</span>
                <button type="button" wire:click="save" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Save Preferences</button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
