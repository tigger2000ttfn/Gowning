<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'QA Sign-off Queue', 'subtitle' => 'Review released results and sign off to complete qualification.', 'icon' => 'heroicon-o-clipboard-document-check'])

    @php $queue = $this->getQueue(); $failed = $this->getFailed(); $canApprove = $this->canApprove(); @endphp

    @unless($canApprove)
        <div class="gqs-panel"><div class="gqs-empty" style="padding:14px;color:#8A6D0B;">You can review this queue, but only a QA Approver can sign off.</div></div>
    @endunless

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-inbox-stack"/> Awaiting QA Sign-off
            <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $queue->count() }} in queue</span>
        </div>
        <div class="gqs-panel-body">
            @forelse ($queue as $q)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <div>
                        <div style="font-weight:700;color:var(--gqs-text,#1A1A1F);">{{ $q->personnel?->full_name ?? 'Unknown' }}</div>
                        <div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">{{ $q->personnel?->employee_id }} · {{ $q->runs_completed }}/{{ $q->runs_required }} runs · {{ $q->workflow_stage?->label() }}</div>
                    </div>
                    @if($canApprove)
                        <div style="white-space:nowrap;">
                            {{ ($this->signOffAction)(['id' => $q->id]) }}
                            <button wire:click="markFailed({{ $q->id }})" class="sb-act sb-act-red">Fail</button>
                        </div>
                    @endif
                </div>
            @empty<div class="gqs-empty">Nothing Awaiting Sign-off.</div>@endforelse
        </div>
    </div>

    @if($failed->isNotEmpty())
    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C8102E,#920B22);"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Failed, Needs Determination</div>
        <div class="gqs-panel-body">
            @foreach ($failed as $q)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <span><strong>{{ $q->personnel?->full_name }}</strong> <span style="color:var(--gqs-text-dim,#6A6A72);font-size:12.5px;">· {{ $q->personnel?->employee_id }}</span></span>
                    <span class="gqs-pill gqs-pill-red">Determination Pending</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <style>
        .sb-act{font-size:12px;font-weight:700;padding:5px 13px;border-radius:7px;border:none;cursor:pointer;margin-left:6px;color:#fff;}
        .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
        .sb-act-red{background:#C8102E;} .sb-act-red:hover{background:#9A0C23;}
    </style>

    <x-filament-actions::modals />
</x-filament-panels::page>
