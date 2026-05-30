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
                <div class="sb-card-body" @click="openCard({{ $card['id'] }})">
                    <div class="sb-name">{{ $card['name'] }}</div>
                    <div class="sb-meta">{{ $card['employee_id'] }}@if(!empty($card['department'])) · {{ $card['department'] }}@endif</div>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px;">
                        @if(!empty($card['status']))
                            <span class="sb-pill sb-pill-{{ $card['status_key'] }}">{{ $card['status'] }}</span>
                        @endif
                        @if(!empty($card['type']))<span class="sb-tag">{{ $card['type'] }}</span>@endif
                    </div>
                    @if(($card['runs_req'] ?? 0) > 0)
                        <div class="sb-runs" title="{{ $card['runs_done'] }} of {{ $card['runs_req'] }} runs">
                            @for($r = 0; $r < $card['runs_req']; $r++)
                                <span class="sb-pip {{ $r < $card['runs_done'] ? 'on' : '' }}"></span>
                            @endfor
                            <span class="sb-runs-lbl">{{ $card['runs_done'] }}/{{ $card['runs_req'] }} runs</span>
                        </div>
                    @endif
                    @if(!empty($card['last_run_date']))
                        <div class="sb-line"><span class="sb-line-l">Last run</span> {{ $card['last_run_date'] }}@if($card['last_run_worklist']) · {{ $card['last_run_worklist'] }}@endif</div>
                    @endif
                    @if($card['due'] ?? false)<div class="sb-line"><span class="sb-line-l">Next due</span> {{ $card['due'] }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>
</div>
