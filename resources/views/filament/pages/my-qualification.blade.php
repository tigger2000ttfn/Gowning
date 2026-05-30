<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'My Qualification', 'subtitle' => 'Your status and run history.', 'icon' => 'heroicon-o-identification'])

    @if($this->rescheduleAction->isVisible())
        <div style="margin-bottom:16px;">{{ $this->rescheduleAction }}</div>
    @endif
    <x-filament-actions::modals />
    @if (! $person)
        <div style="padding:24px;border:1px dashed var(--gray-300);border-radius:12px;">
            <p>No personnel record is linked to your account yet. An administrator can link
               your account to your employee record so your qualification status appears here.</p>
        </div>
    @else
        {{-- Status summary --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <x-filament::section>
                <x-slot name="heading">Status</x-slot>
                @php $q = $qualification; @endphp
                @if ($q)
                    <span @style([
                        'font-size:20px;font-weight:700',
                        'color:#2E7D5B' => $q->status?->value === 'qualified',
                        'color:#B8860B' => $q->status?->value === 'in_progress',
                        'color:#C8102E' => $q->status?->value === 'lapsed',
                    ])>{{ $q->status?->label() }}</span>
                    <div style="margin-top:8px;color:var(--gray-500);">
                        {{ $q->runs_completed }} / {{ $q->runs_required }} runs &middot; {{ $q->type?->label() }}
                    </div>
                @else
                    <span style="color:var(--gray-500)">Not started</span>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Due date</x-slot>
                <span style="font-size:20px;font-weight:700;">
                    {{ $qualification?->due_date?->format('M j, Y') ?? '—' }}
                </span>
                @if ($qualification?->due_date)
                    <div style="margin-top:8px;color:{{ $qualification->isPastDue() ? '#C8102E' : 'var(--gray-500)' }};">
                        {{ $qualification->isPastDue() ? 'Overdue' : 'Current' }}
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Gowning class</x-slot>
                @if ($classes->isNotEmpty())
                    <span style="color:#2E7D5B;font-weight:700;">Completed</span>
                    <div style="margin-top:8px;color:var(--gray-500);">
                        {{ $classes->first()->class_name }} &middot; {{ $classes->first()->completion_date?->format('M j, Y') }}
                    </div>
                @else
                    <span style="color:#B8860B;font-weight:700;">Not on file</span>
                @endif
            </x-filament::section>
        </div>

        {{-- Run history --}}
        <x-filament::section class="fi-mt-6" style="margin-top:20px;">
            <x-slot name="heading">My qualification run history</x-slot>
            @if ($runs->isEmpty())
                <p style="color:var(--gray-500)">No runs recorded yet.</p>
            @else
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="text-align:left;border-bottom:2px solid #A4123F;">
                        <th style="padding:8px;">Date</th><th>Result</th><th>Cycle</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($runs as $run)
                        <tr style="border-bottom:1px solid var(--gray-200);">
                            <td style="padding:8px;">{{ $run->run_date?->format('M j, Y') }}</td>
                            <td><span style="color:{{ $run->result?->value === 'pass' ? '#2E7D5B' : '#C8102E' }};font-weight:600;">{{ $run->result?->label() }}</span></td>
                            <td>{{ $run->cycle_type?->label() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        {{-- Upcoming class enrollments --}}
        <x-filament::section style="margin-top:20px;">
            <x-slot name="heading">My upcoming classes</x-slot>
            @if ($enrollments->isEmpty())
                <p style="color:var(--gray-500)">You're not signed up for any upcoming classes.
                   <a href="{{ url('/') }}" style="color:#A4123F;font-weight:600;">Browse classes &rarr;</a></p>
            @else
                @foreach ($enrollments as $e)
                    <div style="padding:10px 0;border-bottom:1px solid var(--gray-200);">
                        <strong>{{ $e->classSession?->trainingClass?->name }}</strong>
                        <span style="color:var(--gray-500);"> &middot; {{ $e->classSession?->session_date?->format('M j, Y') }}
                        @if($e->classSession?->location) &middot; {{ $e->classSession->location }} @endif
                        &middot; {{ ucfirst(str_replace('_',' ',$e->status)) }}</span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
