<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Audit Trail', 'subtitle' => 'Computer-generated record of every change. Part 11 / ALCOA+.', 'icon' => 'heroicon-o-document-magnifying-glass'])

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-funnel"/> Filters</div>
        <div class="gqs-panel-body" style="padding:14px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
            <div>
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:5px;">Record Type</label>
                <select wire:model.live="fLog" style="padding:8px 10px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);min-width:160px;">
                    <option value="">All Types</option>
                    @foreach($this->getLogNames() as $ln)<option value="{{ $ln }}">{{ $ln }}</option>@endforeach
                </select>
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:5px;">Event</label>
                <select wire:model.live="fEvent" style="padding:8px 10px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);min-width:130px;">
                    <option value="">All Events</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                </select>
            </div>
            <div style="flex:1;min-width:180px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:5px;">Search</label>
                <input type="text" wire:model.live.debounce.400ms="fSearch" placeholder="Search descriptions…"
                       style="width:100%;padding:8px 10px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
            </div>
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clipboard-document-list"/> Activity (Latest 200)</div>
        <div class="gqs-panel-body">
            @php $entries = $this->getEntries(); @endphp
            @forelse($entries as $a)
                @php
                    $ev = $a->event ?? 'updated';
                    $pill = ['created'=>'gqs-pill-green','updated'=>'gqs-pill-gold','deleted'=>'gqs-pill-red'][$ev] ?? 'gqs-pill-purple';
                    $old = $a->properties['old'] ?? [];
                    $new = $a->properties['attributes'] ?? [];
                @endphp
                <div style="padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span class="gqs-pill {{ $pill }}">{{ ucfirst($ev) }}</span>
                        <strong style="color:var(--gqs-text,#1A1A1F);">{{ $a->log_name }}</strong>
                        <span style="color:var(--gqs-text-dim,#6A6A72);font-size:13px;">#{{ $a->subject_id }}</span>
                        <span style="margin-left:auto;font-size:12px;color:var(--gqs-text-dim,#9A9AA4);">
                            {{ $a->causer?->name ?? 'System' }} · {{ $a->created_at?->setTimezone('America/New_York')?->format('M j, Y g:i A') }}
                        </span>
                    </div>
                    @if(!empty($new))
                        <div style="margin-top:7px;font-size:12.5px;color:var(--gqs-text-dim,#5A5A62);">
                            @foreach($new as $field => $val)
                                @continue(in_array($field, ['updated_at','created_at']))
                                <span style="display:inline-block;margin:2px 10px 2px 0;">
                                    <strong>{{ $field }}:</strong>
                                    @if(isset($old[$field]))<span style="color:#C8102E;text-decoration:line-through;">{{ \Illuminate\Support\Str::limit((string)$old[$field],30) }}</span> → @endif
                                    <span style="color:#2E7D5B;">{{ \Illuminate\Support\Str::limit((string)$val,30) }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="gqs-empty">No Audit Entries Match.</div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
