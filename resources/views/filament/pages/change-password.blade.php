<x-filament-panels::page>
    <div style="max-width:460px;">
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-key"/> Set A New Password</div>
            <div class="gqs-panel-body" style="padding:16px;">
                <p style="font-size:13px;line-height:1.5;color:var(--gqs-text-dim,#6A6A72);margin-bottom:14px;">
                    Your password must be changed before you can continue.
                </p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="gqs-flbl">Current Password</label>
                        <input type="password" wire:model="current" class="gqs-fld">
                        @error('current')<div style="color:#C8102E;font-size:12px;margin-top:3px;">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="gqs-flbl">New Password</label>
                        <input type="password" wire:model="password" class="gqs-fld">
                        @error('password')<div style="color:#C8102E;font-size:12px;margin-top:3px;">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="gqs-flbl">Confirm New Password</label>
                        <input type="password" wire:model="password_confirmation" class="gqs-fld">
                    </div>
                    <button type="button" wire:click="save" class="gqs-btn gqs-btn-primary" style="margin-top:4px;">Update Password</button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
