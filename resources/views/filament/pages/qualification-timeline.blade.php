<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $axis = $this->axis();
        $axisStart = \Carbon\Carbon::parse($axis['start']);
        $axisEnd = \Carbon\Carbon::parse($axis['end']);
        $totalDays = max(1, $axisStart->diffInDays($axisEnd));
        $pct = function ($date) use ($axisStart, $totalDays) {
            $d = \Carbon\Carbon::parse($date);
            return max(0, min(100, ($axisStart->diffInDays($d, false) / $totalDays) * 100));
        };
        // month tick marks across the axis
        $ticks = [];
        $cur = $axisStart->copy()->startOfMonth();
        while ($cur->lte($axisEnd)) {
            if ($cur->gte($axisStart)) $ticks[] = ['label' => $cur->format('M Y'), 'left' => ($axisStart->diffInDays($cur, false) / $totalDays) * 100];
            $cur->addMonth();
        }
        $todayLeft = $pct($axis['today']);
    @endphp

    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-chart-bar" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Qualification Timeline</h1>
                <p>{{ $view_mode === 'due_window' ? 'The window each person has to complete their next round before the due date.' : 'Each person\'s full cycle path to qualified.' }}</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID" class="gqs-fld sb-hf-search">
            <select wire:model.live="deptFilter" class="gqs-fld sb-hf-sel">
                <option value="">All Departments</option>
                @foreach($this->departmentOptions() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
            </select>
            <select wire:model.live="view_mode" class="gqs-fld sb-hf-sel" style="min-width:170px;">
                <option value="due_window">Due Window (next round)</option>
                <option value="full">Full Cycle Path</option>
            </select>
            @if($view_mode === 'due_window')
                <select wire:model.live="windowDays" class="gqs-fld sb-hf-sel" style="min-width:120px;">
                    <option value="30">30-day window</option>
                    <option value="60">60-day window</option>
                    <option value="90">90-day window</option>
                </select>
            @endif
        </div>
    </div>

    {{-- Legend = clickable status filters --}}
    <div class="tl-legend">
        @php
            $chips = ['' => ['All', '#6A6A72'], 'in_progress' => ['In Progress', '#A4123F'], 'qualified' => ['Qualified', '#2E7D5B'], 'lapsed' => ['Lapsed', '#C8102E']];
        @endphp
        @foreach($chips as $val => [$label, $color])
            <button type="button" wire:click="$set('statusFilter', '{{ $val }}')"
                    class="tl-chip @if($statusFilter === $val) tl-chip-on @endif"
                    style="--chip:{{ $color }};">
                <i style="background:{{ $color }};"></i>{{ $label }}
            </button>
        @endforeach
        <span class="tl-count">{{ count($rows) }} people</span>
    </div>

    <div class="tl-fullbleed">
        @if(empty($rows))
            <div class="gqs-empty" style="padding:40px;">No people match. Adjust the filters.</div>
        @else
            <div class="tl-grid">
                {{-- Left frozen name pane --}}
                <div class="tl-names">
                    <div class="tl-names-head">Person</div>
                    @foreach($rows as $r)
                        <div class="tl-name-row" wire:click="showDetail({{ $r['id'] }})">
                            <div class="tl-name">{{ $r['name'] }}</div>
                            <div class="tl-sub">{{ $r['employee_id'] }} · {{ $r['stage'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Right time grid --}}
                <div class="tl-track-wrap">
                    <div class="tl-track-head">
                        @foreach($ticks as $t)
                            <div class="tl-tick" style="left:{{ $t['left'] }}%;">{{ $t['label'] }}</div>
                        @endforeach
                    </div>
                    <div class="tl-track-body">
                        {{-- today line --}}
                        @if($todayLeft >= 0 && $todayLeft <= 100)
                            <div class="tl-today" style="left:{{ $todayLeft }}%;" title="Today"></div>
                        @endif
                        @foreach($rows as $r)
                            @php $l = $pct($r['start']); $w = max(1.5, $pct($r['end']) - $l); @endphp
                            <div class="tl-row" wire:click="showDetail({{ $r['id'] }})">
                                <div class="tl-bar tl-{{ $r['class'] }}" style="left:{{ $l }}%;width:{{ $w }}%;" title="{{ $r['name'] }} · due {{ $r['due'] }}">
                                    <div class="tl-bar-fill" style="width:{{ $r['progress'] }}%;"></div>
                                    <span class="tl-bar-lbl">{{ $r['runs'] }}@if($r['due']) · due {{ $r['due'] }}@endif</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Detail modal --}}
    @php $d = $this->detail(); @endphp
    @if($d)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="closeDetail">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:420px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <div style="font-weight:800;font-size:17px;">{{ $d['name'] }}</div>
                        <div style="font-size:12px;opacity:.8;">{{ $d['employee_id'] }}@if($d['department']) · {{ $d['department'] }}@endif</div>
                    </div>
                    <button wire:click="closeDetail" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;opacity:.7;">&times;</button>
                </div>
                <div style="padding:18px 20px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px;font-size:13px;">
                        <div><div class="dm-l">Stage</div><div class="dm-v">{{ $d['stage'] }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $d['status'] }}</div></div>
                        <div><div class="dm-l">Runs</div><div class="dm-v">{{ $d['runs'] }}</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $d['due'] ?? '—' }}</div></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button wire:click="closeDetail" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Close</button>
                        @if($d['edit_url'])<a href="{{ $d['edit_url'] }}" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;font-weight:700;text-decoration:none;">Open Record</a>@endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div wire:ignore>
        <script>
            (function () {
                function sync() {
                    const names = document.querySelector('.tl-names');
                    const track = document.querySelector('.tl-track-wrap');
                    if (!names || !track || track._synced) return;
                    track._synced = true;
                    track.addEventListener('scroll', () => { names.scrollTop = track.scrollTop; });
                    names.addEventListener('scroll', () => { track.scrollTop = names.scrollTop; });
                }
                if (document.readyState !== 'loading') sync(); else document.addEventListener('DOMContentLoaded', sync);
                document.addEventListener('livewire:initialized', () => {
                    if (window.Livewire) Livewire.hook('morph.updated', () => setTimeout(() => {
                        const t = document.querySelector('.tl-track-wrap'); if (t) t._synced = false; sync();
                    }, 50));
                });
            })();
        </script>
    </div>

    <style>
        .tl-fullbleed{ margin:0 -32px; padding:0 32px; }
        .tl-legend{ display:flex; align-items:center; gap:8px; padding:4px 32px 12px; flex-wrap:wrap; }
        .tl-chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 13px; border-radius:20px; border:1.5px solid var(--gqs-border,#DADADF); background:transparent; cursor:pointer; font-size:12.5px; font-weight:600; color:var(--gqs-text-dim,#6A6A72); }
        .tl-chip i{ width:10px; height:10px; border-radius:50%; }
        .tl-chip-on{ border-color:var(--chip); color:var(--chip); background:color-mix(in srgb, var(--chip) 10%, transparent); }
        .tl-count{ margin-left:auto; font-weight:700; font-size:12.5px; color:var(--gqs-text-dim,#6A6A72); }

        .tl-grid{ display:flex; border:1px solid var(--gqs-border,#E2E2E6); border-radius:12px; overflow:hidden; background:#fff; height:calc(100vh - 280px); min-height:340px; }
        .dark .tl-grid{ background:#1A1A20; border-color:#2A2A32; }
        .tl-names{ flex:0 0 240px; border-right:2px solid var(--gqs-border,#E2E2E6); overflow-y:auto; }
        .dark .tl-names{ border-color:#2A2A32; }
        .tl-names-head{ height:38px; display:flex; align-items:center; padding:0 14px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--gqs-text-dim,#8A8A93); border-bottom:1px solid var(--gqs-border,#ECECEF); position:sticky; top:0; background:#F7F7F9; z-index:2; }
        .dark .tl-names-head{ background:#23232B; border-color:#2A2A32; }
        .tl-name-row{ height:40px; padding:5px 14px; border-bottom:1px solid var(--gqs-border,#F2F2F4); cursor:pointer; display:flex; flex-direction:column; justify-content:center; }
        .dark .tl-name-row{ border-color:#26262E; }
        .tl-name-row:hover{ background:var(--gqs-surface-2,#F4F4F6); }
        .dark .tl-name-row:hover{ background:#23232B; }
        .tl-name{ font-weight:700; font-size:13px; color:var(--gqs-text,#1A1A1F); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dark .tl-name{ color:#fff; }
        .tl-sub{ font-size:11px; color:var(--gqs-text-dim,#9A9AA4); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .tl-track-wrap{ flex:1; overflow:auto; position:relative; }
        .tl-track-head{ height:38px; position:sticky; top:0; background:#F7F7F9; border-bottom:1px solid var(--gqs-border,#ECECEF); z-index:2; min-width:760px; }
        .dark .tl-track-head{ background:#23232B; border-color:#2A2A32; }
        .tl-tick{ position:absolute; top:0; height:38px; display:flex; align-items:center; font-size:11px; font-weight:700; color:var(--gqs-text-dim,#8A8A93); border-left:1px solid var(--gqs-border,#ECECEF); padding-left:6px; }
        .tl-track-body{ position:relative; min-width:760px; }
        .tl-today{ position:absolute; top:0; bottom:0; width:2px; background:#A4123F; z-index:1; opacity:.6; }
        .tl-row{ height:40px; border-bottom:1px solid var(--gqs-border,#F2F2F4); position:relative; cursor:pointer; }
        .dark .tl-row{ border-color:#26262E; }
        .tl-row:hover{ background:var(--gqs-surface-2,#FAFAFB); }
        .dark .tl-row:hover{ background:#1F1F25; }
        .tl-bar{ position:absolute; top:8px; height:24px; border-radius:6px; overflow:hidden; display:flex; align-items:center; box-shadow:0 1px 2px rgba(0,0,0,.12); }
        .tl-bar-fill{ position:absolute; left:0; top:0; bottom:0; background:rgba(0,0,0,.18); }
        .tl-bar-lbl{ position:relative; z-index:1; font-size:10.5px; font-weight:700; color:#fff; padding:0 8px; white-space:nowrap; text-shadow:0 1px 1px rgba(0,0,0,.3); }
        .tl-active{ background:#A4123F; }
        .tl-qualified{ background:#2E7D5B; }
        .tl-lapsed{ background:#C8102E; }
        .tl-overdue{ background:#8A0E22; }
        .dm-l{ font-size:10.5px; text-transform:uppercase; letter-spacing:.04em; color:var(--gqs-text-dim,#9A9AA4); font-weight:700; }
        .dm-v{ font-size:13.5px; color:var(--gqs-text,#1A1A1F); font-weight:600; margin-top:2px; }
        .dark .dm-v{ color:#fff; }
    </style>
</x-filament-panels::page>
