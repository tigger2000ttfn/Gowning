<x-filament-panels::page>
    <div class="pg-head">
        <div class="pg-head-badge"><x-filament::icon icon="heroicon-o-academic-cap"/></div>
        <div>
            <div class="pg-head-title">QA Classroom Training Approval</div>
            <div class="pg-head-sub">Review submitted, signed attendance forms and approve trainees into Class Complete.</div>
        </div>
    </div>

    @php($queue = $this->getQueue())

    @forelse($queue as $session)
        <div class="gqs-panel" style="margin-bottom:16px;">
            <div class="gqs-panel-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <span>{{ $session['title'] }}</span>
                <span style="font-size:12px;font-weight:600;opacity:.92;">
                    {{ count($session['rows']) }} pending · submitted {{ $session['submitted_at'] }}@if($session['submitted_by']) by {{ $session['submitted_by'] }}@endif
                </span>
            </div>
            <div class="gqs-panel-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <a href="{{ $session['form_url'] }}" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">View Signed Form</a>
                    <button type="button" wire:click="approveSession({{ $session['id'] }})" class="gqs-btn gqs-btn-primary"
                            wire:confirm="Approve all {{ count($session['rows']) }} trainee(s) on this session?">Approve All</button>
                </div>
                <table class="gqs-tbl">
                    <thead><tr><th>Name</th><th>Employee ID</th><th style="text-align:right;">Action</th></tr></thead>
                    <tbody>
                        @foreach($session['rows'] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['employee_id'] ?: '—' }}</td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <button type="button" wire:click="approve({{ $row['id'] }})" class="rd-act rd-act-magenta">Approve</button>
                                    <button type="button" wire:click="reject({{ $row['id'] }})" class="rd-act" style="background:#6A6A72;">Return</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="gqs-empty">No classroom training awaiting QA approval. Submitted sessions will appear here.</div>
    @endforelse
</x-filament-panels::page>
