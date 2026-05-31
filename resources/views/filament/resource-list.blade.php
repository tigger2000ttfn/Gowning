<x-filament-panels::page>
    {{-- GQS hero header with header actions docked on the right (in-header, not above the table) --}}
    <div class="pg-head" style="justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:16px;min-width:0;">
            <span class="pg-head-ico"><x-filament::icon :icon="$this->gqsIcon ?? 'heroicon-o-square-3-stack-3d'" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>{{ $this->gqsTitle ?? $this->getTitle() }}</h1>
            </div>
        </div>
        @if(count($this->getCachedHeaderActions()))
            <div style="display:flex;gap:8px;flex:0 0 auto;align-items:center;">
                <x-filament::actions :actions="$this->getCachedHeaderActions()" />
            </div>
        @endif
    </div>

    {{-- Optional data-gap / status alert boxes (only if the page provides them) --}}
    @if(method_exists($this, 'gqsAlerts') && count($alerts = $this->gqsAlerts()))
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            @foreach($alerts as $a)
                <div style="flex:1;min-width:180px;background:var(--gqs-surface,#fff);border:1px solid {{ $a['border'] ?? 'var(--gqs-border,#E2E2E8)' }};border-left:4px solid {{ $a['accent'] ?? '#C79A2E' }};border-radius:10px;padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:{{ $a['accent'] ?? '#C79A2E' }};">@if(!empty($a['icon']))<x-filament::icon :icon="$a['icon']" style="width:20px;height:20px;"/>@endif</span>
                        <span style="font-size:22px;font-weight:800;color:var(--gqs-text,#1A1A1F);line-height:1;">{{ $a['count'] }}</span>
                    </div>
                    <div style="font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-top:6px;">{{ $a['label'] }}</div>
                    @if(!empty($a['hint']))<div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;">{{ $a['hint'] }}</div>@endif
                    @if(!empty($a['people']) && !empty($a['action']))
                        <div style="display:flex;flex-direction:column;gap:4px;margin-top:8px;">
                            @foreach($a['people'] as $person)
                                <button type="button" wire:click="{{ $a['action'] }}({{ $person['id'] }})"
                                        style="display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;font-weight:600;padding:5px 9px;border-radius:7px;border:1px solid {{ $a['accent'] ?? '#C79A2E' }};background:transparent;color:var(--gqs-text,#1A1A1F);cursor:pointer;text-align:left;">
                                    <span>{{ $person['name'] }}</span>
                                    <span style="font-size:11px;color:{{ $a['accent'] ?? '#C79A2E' }};font-weight:700;">Set Up &rarr;</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(!empty($a['names']))<div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">{{ $a['names'] }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{ $this->table }}

    @if(method_exists($this, 'openOnboard') && $this->onboardPersonId)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="closeOnboard">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:560px;max-width:96vw;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:linear-gradient(135deg,#2E7D5B,#225F46);padding:16px 20px;">
                    <div style="font-weight:800;font-size:17px;color:#fff;">Set Up Qualification</div>
                    <div style="font-size:12px;color:#D7EFE4;">{{ $this->onboardPersonName() }} - kick them into the workflow</div>
                </div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="gqs-flbl">Qualification Type</label>
                        <select wire:model.live="onboard.type" class="gqs-fld">
                            <option value="initial">Initial Gowning Qualification (3 runs)</option>
                            <option value="annual">Requalification Transfer (already qualified)</option>
                        </select>
                    </div>
                    <div>
                        <label class="gqs-flbl">{{ ($onboard['type'] ?? 'initial') === 'annual' ? 'Next Requalification Due Date' : 'Initial Qualification Must Be Completed By' }}</label>
                        <input type="date" wire:model="onboard.due_date" class="gqs-fld">
                    </div>
                    @if(($onboard['type'] ?? 'initial') === 'annual')
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" wire:model.live="onboard.class_done"> Already took the gowning class
                        </label>
                        @if($onboard['class_done'] ?? false)
                            <div>
                                <label class="gqs-flbl">Class Completion Date</label>
                                <input type="date" wire:model="onboard.class_date" class="gqs-fld">
                                <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">Recorded to their class completion history; they skip the class step.</div>
                            </div>
                        @endif
                    @endif
                </div>
                <div style="padding:14px 20px;border-top:1px solid var(--gqs-border,#E2E2E8);display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="closeOnboard" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="saveOnboard" class="gqs-btn gqs-btn-primary">Create Qualification</button>
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
