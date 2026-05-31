@php
    /** @var \Illuminate\Support\Collection $runs */
    $failed = $runs->where('status', 'failed');
    $ok = $runs->where('status', 'success');
@endphp

<div x-data="{ tab: 'all' }" style="padding:2px;">
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
        <button type="button" @click="tab='all'" :class="tab==='all' ? 'ah-tab ah-on' : 'ah-tab'" class="ah-tab">All ({{ $runs->count() }})</button>
        <button type="button" @click="tab='failed'" :class="tab==='failed' ? 'ah-tab ah-on' : 'ah-tab'" class="ah-tab">Failed ({{ $failed->count() }})</button>
        <button type="button" @click="tab='success'" :class="tab==='success' ? 'ah-tab ah-on' : 'ah-tab'" class="ah-tab">Succeeded ({{ $ok->count() }})</button>
    </div>

    @if($runs->isEmpty())
        <div style="padding:24px;text-align:center;color:var(--gqs-text-dim,#9A9AA4);font-size:13.5px;">No automation runs recorded yet. Firings will appear here as rules trigger.</div>
    @else
        <div style="max-height:60vh;overflow-y:auto;border:1px solid var(--gqs-border,#E2E2E8);border-radius:10px;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;padding:9px 12px;border-bottom:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F7F7F9);">When</th>
                    <th style="text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;padding:9px 12px;border-bottom:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F7F7F9);">Rule</th>
                    <th style="text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;padding:9px 12px;border-bottom:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F7F7F9);">Subject</th>
                    <th style="text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;padding:9px 12px;border-bottom:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F7F7F9);">Result</th>
                </tr></thead>
                <tbody>
                    @foreach($runs as $r)
                        <tr x-show="tab==='all' || tab==='{{ $r->status }}'" style="border-bottom:1px solid var(--gqs-border,#F0F0F3);">
                            <td style="padding:9px 12px;font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);white-space:nowrap;">{{ $r->created_at?->setTimezone('America/New_York')->format('d-M-Y H:i') }}</td>
                            <td style="padding:9px 12px;font-size:13px;color:var(--gqs-text,#1A1A1F);font-weight:600;">{{ $r->rule_name ?: ($r->rule?->name ?? 'Rule') }}</td>
                            <td style="padding:9px 12px;font-size:13px;color:var(--gqs-text,#1A1A1F);">{{ $r->subject ?: '—' }}</td>
                            <td style="padding:9px 12px;">
                                @if($r->status === 'failed')
                                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:#FCEEF0;color:#C8102E;">Failed</span>
                                    @if($r->detail)<div style="font-size:11px;color:#C8102E;margin-top:3px;">{{ $r->detail }}</div>@endif
                                @else
                                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:#D7EFE4;color:#1F6147;">Success</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        .ah-tab{font-size:12.5px;font-weight:700;padding:7px 14px;border-radius:8px;border:1.5px solid var(--gqs-border,#D6D6DC);background:transparent;color:var(--gqs-text-dim,#6A6A72);cursor:pointer;}
        .ah-tab.ah-on{background:#26262C;border-color:#26262C;color:#fff;}
    </style>
</div>
