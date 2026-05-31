{{-- One Status Board stage column. Expects $stage = ['key','label','color','cards']. Renders inside the sbBoard x-data scope. --}}
<div class="sb-col" data-lane="{{ $stage['key'] }}">
    <div class="sb-head" style="background:{{ $stage['color'] }};" :class="canReorder ? 'sb-head-grab' : ''">
        <span class="sb-head-label">{{ $stage['label'] }}</span>
        <span class="sb-count">{{ count($stage['cards']) }}</span>
    </div>
    <div class="sb-lane" data-stage="{{ $stage['key'] }}">
        @foreach ($stage['cards'] as $card)
            <div class="sb-card" data-id="{{ $card['id'] }}" style="border-left-color:{{ $stage['color'] }};"
                 :class="{ 'sb-selected': isSelected({{ $card['id'] }}) }">
                @if(auth()->user()?->hasCapability(\App\Enums\Capability::ManageScheduling))
                <label class="sb-check" @click.stop>
                    <input type="checkbox" :checked="isSelected({{ $card['id'] }})" @change="toggleSelect({{ $card['id'] }})">
                </label>
                @endif
                <div class="sb-card-body" wire:click="$dispatch('open-qual-modal', { id: {{ $card['id'] }} })" style="cursor:pointer;">
                    <div class="sb-name-row">
                        <span class="sb-name">{{ $card['name'] }}</span>
                        @if(!empty($card['employee_id']))<span class="sb-emp">{{ $card['employee_id'] }}</span>@endif
                    </div>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px;">
                        @if(!empty($card['flag']))
                            <span class="sb-pill sb-pill-{{ $card['flag_key'] }}">{{ $card['flag'] }}</span>
                        @endif
                        @if(!empty($card['type']))<span class="sb-tag">{{ $card['type'] }}</span>@endif
                        @if(!empty($card['department']))<span class="sb-tag">{{ $card['department'] }}</span>@endif
                    </div>
                    @if(!empty($card['nc']))
                        <div class="sb-line"><span class="sb-line-l">NC</span> @if(!empty($card['nc_url']))<a href="{{ $card['nc_url'] }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">{{ $card['nc'] }} ↗</a>@else {{ $card['nc'] }} @endif@if(!empty($card['nc_status'])) · {{ $card['nc_status'] }}@endif</div>
                    @endif
                    @if(($card['runs_req'] ?? 0) > 0)
                        <div class="sb-runs" title="{{ $card['runs_done'] }} of {{ $card['runs_req'] }} runs">
                            @for($r = 0; $r < $card['runs_req']; $r++)
                                <span class="sb-pip {{ $r < $card['runs_done'] ? 'on' : '' }}"></span>
                            @endfor
                            <span class="sb-runs-lbl">{{ $card['runs_done'] }}/{{ $card['runs_req'] }} runs</span>
                        </div>
                    @endif
                    <div class="sb-foot">
                        @if(!empty($card['last_run_date']))<span class="sb-foot-i" title="Last run">Last: {{ $card['last_run_date'] }}</span>@endif
                        @if(!empty($card['last_run_worklist']))<span class="sb-foot-i sb-wl" title="Worklist {{ $card['last_run_worklist'] }}">{{ $card['last_run_worklist'] }}</span>@endif
                    </div>
                    @if($card['due'] ?? false)
                        <div class="sb-line"><span class="sb-line-l">Due Date</span> {{ $card['due'] }}@if(!empty($card['due_tag'])) <span class="sb-tag {{ $card['due_tag'] === 'Lapsed' ? 'sb-tag-red' : '' }}">{{ $card['due_tag'] }}</span>@endif</div>
                    @endif
                </div>
                @if($stage['key'] === 'class_complete' && empty($card['has_booking']) && auth()->user()?->hasCapability(\App\Enums\Capability::ManageScheduling))
                    <button type="button" wire:click="openBookRun({{ $card['id'] }})" @click.stop class="sb-book-run">Book Run</button>
                @endif
                @if(!empty($card['review_url']) && !empty($card['review_label']))
                    <a href="{{ $card['review_url'] }}" @click.stop class="sb-review-btn">{{ $card['review_label'] }} &rarr;</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
