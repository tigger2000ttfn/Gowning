<?php

namespace App\Filament\Admin\Pages;

use App\Enums\WorkflowStage;
use App\Enums\Capability;
use App\Models\Qualification;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class StatusBoard extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling)
            || $u->hasCapability(Capability::QaReview)
            || $u->hasCapability(Capability::ViewQualifications)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Status Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Status Board';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.status-board';

    public string $search = '';
    public string $deptFilter = '';
    public string $typeFilter = '';
    public string $stageFilter = '';
    public bool $stalledOnly = false;
    public bool $pastDueOnly = false;
    public string $groupBy = '';

    public function stageOptions(): array
    {
        $out = ['' => 'All Stages'];
        foreach (WorkflowStage::pipeline() as $s) { $out[$s->value] = $s->label(); }
        return $out;
    }

    public function stalledDays(): int { return (int) \App\Models\Setting::get('stalled_days', 14); }

    public function groupByOptions(): array
    {
        return ['' => 'No Grouping', 'department' => 'Department', 'job_title' => 'Job Title', 'type' => 'Cycle Type', 'due_window' => 'Due Window'];
    }

    public function departmentOptions(): array
    {
        return \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();
    }

    public function getStages(): array
    {
        // keep the board current: lapse overdue quals, then promote anything past incubation
        app(\App\Services\LifecycleAdvancer::class)->run();
        app(\App\Services\RunCycleAdvancer::class)->sweep();
        $out = [];
        $byStage = Qualification::with('personnel')
            ->whereNull('superseded_at')
            ->whereNull('archived_at')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->stageFilter !== '', fn ($q) => $q->where('workflow_stage', $this->stageFilter))
            ->when($this->stalledOnly, fn ($q) => $q->where('stage_changed_at', '<=', now()->subDays($this->stalledDays())))
            ->when($this->pastDueOnly, fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString()))
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->when($this->deptFilter !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('department', $this->deptFilter)))
            ->get()
            ->groupBy(fn ($q) => $q->workflow_stage?->value ?? 'class_pending');

        foreach (WorkflowStage::pipeline() as $stage) {
            $cards = ($byStage[$stage->value] ?? collect())->map(function ($q) {
                // Cycle-aware: only count runs in the CURRENT cycle, so a fresh cycle
                // doesn't show prior-cycle runs or an old worklist/date.
                $cycleRuns = app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q);
                // Show the SAME pass count the Active Runs list shows (the stored, engine-maintained value).
                // cycleRuns() requires incubation_started_at, so backfilled/seed runs (no incubation stamp)
                // would otherwise read 0 here and disagree with the list - use runs_completed as the source
                // of truth and only fall back to the live count if the stored value is missing.
                $passes = (int) ($q->runs_completed ?? 0);
                if ($passes === 0) {
                    $passes = $cycleRuns->filter(fn ($r) => (($r->result->value ?? $r->result) === 'pass'))->count();
                }
                // Last run for display: prefer a cycle run, else the person's most recent run overall.
                // "Last run" = the person's most recent run by date (not cycle-filtered), since the user
                // reads this as "when did they last run a plate", regardless of incubation/cycle state.
                $lastRun = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)
                    ->orderByDesc('run_date')->orderByDesc('id')->first();
                return [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'job_title' => $q->personnel?->job_title,
                'type' => $q->sessionLabel(),
                'due_bucket' => (function () use ($q) {
                    $d = $q->due_date;
                    if (! $d) return 'No Due Date';
                    $today = now()->startOfDay();
                    $due = $d->copy()->startOfDay();
                    if ($due->lt($today)) return 'Overdue';
                    $days = (int) $today->diffInDays($due);
                    if ($days <= 30) return 'Due ≤30 Days';
                    if ($days <= 90) return 'Due ≤90 Days';
                    return 'Later';
                })(),
                'meta' => $passes . '/' . $q->runs_required . ' runs',
                'runs_done' => (int) $passes,
                'runs_req' => (int) $q->runs_required,
                'due' => $q->due_date?->gmp(),
                'last_run_date' => $lastRun?->run_date?->gmp(),
                'last_run_worklist' => $lastRun?->lims_worklist_id,
                // Status pill reflects the card's TRUE state. QA sign-off is what makes someone qualified,
                // so "Qualified" shows when: the card is at QA Approved (qa_signoff), OR the person is in
                // the steady qualified state (status qualified + qualified_date) and NOT currently mid-run
                // pipeline. A person in their requal window who has NOT yet started runs still shows
                // Qualified (valid until due); once they enter the run pipeline the lane + pips show
                // progress and the pill tracks the work. Past due always = Lapsed Qual.
                'flag' => (function () use ($q) {
                    $stage = $q->workflow_stage?->value;
                    $status = $q->status?->value ?? $q->status;
                    $inPipeline = in_array($stage, [
                        'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review',
                    ], true);
                    if ($status === 'lapsed') return 'Lapsed Qual';
                    if ($stage === 'failed') return 'Failed';
                    if ($stage === 'qa_signoff') return 'Qualified';
                    if ($status === 'qualified' && $q->qualified_date && ! $inPipeline) {
                        return $q->isPastDue() ? 'Lapsed Qual' : 'Qualified';
                    }
                    if ($status === 'in_progress' || $inPipeline) return 'In Progress';
                    if ($status === 'pending') return 'Pending';
                    return null;
                })(),
                'flag_key' => (function () use ($q) {
                    $stage = $q->workflow_stage?->value;
                    $status = $q->status?->value ?? $q->status;
                    $inPipeline = in_array($stage, [
                        'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review',
                    ], true);
                    if ($status === 'lapsed') return 'lapsed';
                    if ($stage === 'failed') return 'lapsed';
                    if ($stage === 'qa_signoff') return 'qualified';
                    if ($status === 'qualified' && $q->qualified_date && ! $inPipeline) {
                        return $q->isPastDue() ? 'lapsed' : 'qualified';
                    }
                    if ($status === 'in_progress' || $inPipeline) return 'in_progress';
                    if ($status === 'pending') return 'pending';
                    return null;
                })(),
                // Whether this person already has an active run reservation (so Book Run can hide).
                'has_booking' => $q->personnel_id
                    ? \App\Models\Reservation::where('personnel_id', $q->personnel_id)->whereIn('status', ['requested', 'approved'])->exists()
                    : false,
                // Explicit stage label for the card body (the column header also shows it, but the card
                // listing it on its own line keeps each card self-describing).
                'stage_label' => $q->workflow_stage
                    ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label())
                    : null,
                // Due-date nuance: an INITIAL qualification's due date is the deadline to finish initial
                // qualification; a seasoned (already-qualified) person's due date is their requal deadline
                // (miss it and cleanroom access lapses). Label + tag make which-one unambiguous.
                'due_label' => (function () use ($q) {
                    $type = $q->type instanceof \BackedEnum ? $q->type->value : $q->type;
                    return $type === 'annual' ? 'Requal Due' : 'Initial Due';
                })(),
                'due_tag' => (function () use ($q) {
                    $status = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
                    $type = $q->type instanceof \BackedEnum ? $q->type->value : $q->type;
                    if ($status === 'lapsed' || $q->isPastDue()) return 'Lapsed';
                    return $type === 'annual' ? 'Requal' : 'Initial';
                })(),
                // Card review link: ONLY for stages PAST incubating (awaiting results onward), routing to
                // the review screen where the work happens. Earlier-stage cards have no button - click the
                // card for detail. Awaiting Results / Results Released -> Lab Review; QA stages -> QA Review.
                'review_url' => (function () use ($q) {
                    return match ($q->workflow_stage?->value) {
                        'awaiting_results', 'results_released' => \App\Filament\Admin\Pages\IncubationBoard::getUrl(),
                        'qa_review', 'qa_signoff', 'failed' => \App\Filament\Admin\Pages\QaQueue::getUrl(),
                        default => null,
                    };
                })(),
                'review_label' => (function () use ($q) {
                    return match ($q->workflow_stage?->value) {
                        'awaiting_results', 'results_released' => 'Lab Review',
                        'qa_review', 'qa_signoff', 'failed' => 'QA Review',
                        default => null,
                    };
                })(),
            ];
            })->values()->all();

            $out[] = [
                'key' => $stage->value,
                'label' => \App\Models\WorkflowStatus::labelFor('run', $stage->value, $stage->label()),
                'color' => \App\Models\WorkflowStatus::colorFor('run', $stage->value, $stage->color()),
                'cards' => $cards,
            ];
        }
        // Failed lane at the end (toggleable in Settings)
        if ((bool) \App\Models\Setting::get('board_show_failed', true)) {
            $failed = ($byStage['failed'] ?? collect())->map(function ($q) {
                $nc = \App\Models\NonConformance::where('personnel_id', $q->personnel_id)
                    ->whereNotNull('trackwise_id')->latest('id')->first();
                return [
                    'id' => $q->id, 'name' => $q->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $q->personnel?->employee_id, 'meta' => 'Needs determination', 'due' => null,
                    'department' => $q->personnel?->department, 'job_title' => $q->personnel?->job_title,
                    'type' => $q->sessionLabel(), 'due_bucket' => $q->due_date ? 'Later' : 'No Due Date',
                    'nc' => $nc?->trackwise_id, 'nc_url' => $nc?->trackwise_url, 'nc_status' => $nc?->trackwise_status,
                ];
            })->values()->all();
            $out[] = ['key' => 'failed', 'label' => \App\Models\WorkflowStatus::labelFor('run', 'failed', WorkflowStage::Failed->label()), 'color' => \App\Models\WorkflowStatus::colorFor('run', 'failed', WorkflowStage::Failed->color()), 'cards' => $failed];
        }

        // Apply the saved lane order (drag-to-reorder persists this).
        $order = $this->laneOrder();
        if ($order) {
            usort($out, function ($a, $b) use ($order) {
                $ia = array_search($a['key'], $order, true);
                $ib = array_search($b['key'], $order, true);
                $ia = $ia === false ? 999 : $ia;
                $ib = $ib === false ? 999 : $ib;
                return $ia <=> $ib;
            });
        }

        return $out;
    }

    /**
     * Swimlanes: the same stage columns, split into horizontal bands by a grouping
     * field (department or cycle type). Returns [['label','key','stages'=>[...]], ...].
     * With no grouping, a single band containing the full board.
     */
    public function getSwimlanes(): array
    {
        $stages = $this->getStages();
        if ($this->groupBy === '' || ! array_key_exists($this->groupBy, $this->groupByOptions())) {
            return [['label' => null, 'key' => '_all', 'stages' => $stages]];
        }
        $field = match ($this->groupBy) {
            'job_title'  => 'job_title',
            'due_window' => 'due_bucket',
            default      => $this->groupBy, // 'department' | 'type'
        };
        $values = collect($stages)
            ->flatMap(fn ($l) => collect($l['cards'])->pluck($field))
            ->map(fn ($v) => ($v === null || $v === '') ? '—' : $v)
            ->unique()->values()->all();
        if ($this->groupBy === 'due_window') {
            $rank = ['Overdue' => 0, 'Due ≤30 Days' => 1, 'Due ≤90 Days' => 2, 'Later' => 3, 'No Due Date' => 4, '—' => 5];
            usort($values, fn ($a, $b) => ($rank[$a] ?? 9) <=> ($rank[$b] ?? 9));
        } else {
            sort($values);
        }
        if (empty($values)) {
            return [['label' => null, 'key' => '_all', 'stages' => $stages]];
        }
        $lanes = [];
        foreach ($values as $val) {
            $count = 0;
            $forGroup = array_map(function ($lane) use ($field, $val, &$count) {
                $lane['cards'] = array_values(array_filter($lane['cards'], function ($c) use ($field, $val) {
                    $cv = $c[$field] ?? null;
                    $cv = ($cv === null || $cv === '') ? '—' : $cv;
                    return $cv === $val;
                }));
                $count += count($lane['cards']);
                return $lane;
            }, $stages);
            $lanes[] = ['label' => $val, 'count' => $count, 'key' => \Illuminate\Support\Str::slug($val) ?: 'na', 'stages' => $forGroup];
        }
        return $lanes;
    }
    public function getArchive(): array
    {
        $signed = Qualification::with('personnel')
            ->whereNotNull('archived_at')
            ->whereNull('superseded_at')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->when($this->deptFilter !== '', fn ($q) => $q->whereHas('personnel', fn ($p) => $p->where('department', $this->deptFilter)))
            ->latest('archived_at')->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'due' => $q->due_date?->gmp(),
            ])->values()->all();

        return [
            'label' => \App\Models\WorkflowStatus::labelFor('run', 'archived', 'Historical'),
            'color' => \App\Models\WorkflowStatus::colorFor('run', 'archived', WorkflowStage::Archived->color()),
            'cards' => $signed,
        ];
    }

    public function laneOrder(): array
    {
        $raw = \App\Models\Setting::get('board_lane_order', '');
        return $raw ? array_values(array_filter(explode(',', $raw))) : [];
    }

    public function setLaneOrder(array $keys): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) return;
        \App\Models\Setting::put('board_lane_order', implode(',', $keys));
    }

    /** Drag a person's card to a new workflow stage. QA sign-off requires QaApprove. */

    // ----- Book a run for a Class-Complete person (next available, or a specific run day) -----
    public ?int $bookRunQid = null;
    public string $bookRunMode = 'next';   // 'next' | 'specific'
    public ?int $bookRunSlotId = null;

    public function openBookRun(int $qid): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $this->bookRunQid = $qid;
        $this->bookRunMode = 'next';
        $this->bookRunSlotId = null;
    }
    public function closeBookRun(): void { $this->bookRunQid = null; $this->bookRunSlotId = null; }

    /** Upcoming open run slots with seats left, for the specific-day picker. */
    public function bookRunSlotOptions(): array
    {
        $scheduler = app(\App\Services\AutoScheduler::class);
        return \App\Models\RunSlot::where('status', 'open')
            ->where('is_special', false)
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')->get()
            ->filter(fn ($s) => $scheduler->seatsLeft($s) > 0)
            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->gmp() . ' · ' . ($s->cleanroom ?: 'Run Day')
                . ' (' . $scheduler->seatsLeft($s) . ' seats)'])->all();
    }

    public function confirmBookRun(): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $q = Qualification::find($this->bookRunQid);
        if (! $q) { $this->closeBookRun(); return; }
        $scheduler = app(\App\Services\AutoScheduler::class);

        // already actively booked?
        $active = \App\Models\Reservation::where('personnel_id', $q->personnel_id)
            ->whereIn('status', ['requested', 'approved'])->exists();
        if ($active) {
            Notification::make()->warning()->title('Already Booked')->body('This person already has an active run booking.')->send();
            $this->closeBookRun();
            return;
        }

        if ($this->bookRunMode === 'specific' && $this->bookRunSlotId) {
            $slot = \App\Models\RunSlot::find($this->bookRunSlotId);
            if (! $slot || $scheduler->seatsLeft($slot) < 1) {
                Notification::make()->danger()->title('Run Day Full')->body('That run day has no seats left. Pick another.')->send();
                return;
            }
            \App\Models\Reservation::create([
                'run_slot_id' => $slot->id, 'personnel_id' => $q->personnel_id,
                'status' => 'approved', 'requested_at' => now(), 'decided_at' => now(),
                'notes' => 'Booked from Status Board',
            ]);
            \App\Services\AutoScheduler::markScheduled($q);
            Notification::make()->success()->title('Run Booked')
                ->body(($q->personnel?->full_name ?? 'Trainee') . ' booked for ' . $slot->slot_date->gmp() . ($slot->cleanroom ? ' in ' . $slot->cleanroom : '') . '.')->send();
        } else {
            $res = $scheduler->bookNext($q);
            if (! $res) {
                Notification::make()->warning()->title('No Open Run Day')->body('No upcoming run day with open seats. Schedule one, or pick a specific day.')->send();
                return;
            }
            $slot = $res->runSlot;
            Notification::make()->success()->title('Run Booked')
                ->body(($q->personnel?->full_name ?? 'Trainee') . ' booked for ' . ($slot?->slot_date?->gmp() ?? 'the next available run day') . '.')->send();
        }
        $this->closeBookRun();
    }

    /** Where to go to make the next entry for this person, based on their stage. */
    protected function quickActionUrl(Qualification $q): ?string
    {
        $stage = $q->workflow_stage?->value;
        return match ($stage) {
            // ready to run / scheduled / performing -> Run Scheduler
            'class_complete', 'run_scheduled', 'run_performed'
                => \App\Filament\Admin\Pages\RunDayRoster::getUrl(),
            // incubating / results -> Lab Review (where QC Micro enters/evaluates results)
            'incubating', 'awaiting_results', 'results_released'
                => \App\Filament\Admin\Pages\IncubationBoard::getUrl(),
            // QA stages -> QA Sign-off Queue
            'qa_review', 'qa_signoff', 'failed'
                => \App\Filament\Admin\Pages\QaQueue::getUrl(),
            // class pending -> Class Scheduler
            'class_pending'
                => \App\Filament\Admin\Pages\ClassScheduler::getUrl(),
            default => null,
        };
    }

    protected function quickActionLabel(Qualification $q): ?string
    {
        $stage = $q->workflow_stage?->value;
        return match ($stage) {
            'class_complete', 'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released'
                => 'Go To Run Scheduler',
            'qa_review', 'qa_signoff', 'failed' => 'Go To QA Queue',
            'class_pending' => 'Go To Class Scheduler',
            default => null,
        };
    }

    /** Short label for the inline card button. */
    protected function quickActionShort(Qualification $q): ?string
    {
        $stage = $q->workflow_stage?->value;
        return match ($stage) {
            'class_complete' => 'Run Scheduler',
            'run_scheduled', 'run_performed' => 'Run Scheduler',
            'incubating', 'awaiting_results', 'results_released' => 'Lab Review',
            'qa_review', 'qa_signoff', 'failed' => 'QA Queue',
            'class_pending' => 'Class Scheduler',
            default => null,
        };
    }

    public function moveCard(int $id, string $toStage): void
    {
        $stage = WorkflowStage::tryFrom($toStage);
        if (! $stage) {
            return;
        }
        $q = Qualification::find($id);
        if (! $q) {
            return;
        }

        // Read-only viewers (ViewQualifications only) cannot move cards.
        $u = Auth::user();
        if (! ($u?->hasCapability(Capability::ManageScheduling) || $u?->hasCapability(Capability::QaApprove) || $u?->hasCapability(Capability::RecordRuns))) {
            Notification::make()->danger()->title('Read-only Access')
                ->body('You can view the board but not move cards.')->send();
            return;
        }

        $fromStage = $q->workflow_stage?->value;

        // QA SIGN-OFF is never a plain drag - it is the formal QA approval wizard (the QA approval date
        // drives qualified_date + next due). Dragging a card to QA Approved opens that wizard instead of
        // silently qualifying. Non-QA roles are blocked outright.
        if ($stage === WorkflowStage::QaSignoff) {
            if (! $u?->hasCapability(Capability::QaApprove)) {
                Notification::make()->danger()->title('Not Your Role')
                    ->body('Only QA approvers can complete a QA sign-off. Ask QA to approve this record.')->send();
                // (no model change = the card snaps back to its lane on re-render)
                return;
            }
            // QA user: send them to the sign-off wizard rather than auto-stamping qualified here.
            $this->redirect(\App\Filament\Admin\Pages\QaQueue::getUrl(['signoff' => $q->id]));
            return;
        }

        // Moving INTO QA Review is the QCM hand-off and requires a QCM sign-off. It can only come from a
        // released result, and it pops the QCM results/sign-off screen so the e-signature is captured.
        if ($stage === WorkflowStage::QaReview && $fromStage !== 'qa_review') {
            if (! ($u?->hasCapability(Capability::QaReview) || $u?->hasCapability(Capability::RecordRuns) || $u?->hasCapability(Capability::QaApprove))) {
                Notification::make()->danger()->title('Not Your Role')
                    ->body('A QC Micro reviewer signs results off to QA. You do not have that role.')->send();
                // (no model change = the card snaps back on re-render)
                return;
            }
            if (! in_array($fromStage, ['results_released', 'awaiting_results'], true)) {
                Notification::make()->warning()->title('Release Results First')
                    ->body('Results must be entered and released (QCM review) before this goes to QA.')->send();
                // (no model change = the card snaps back on re-render)
                return;
            }
            // Route to Lab Review's Result Evaluation, opening this record's results modal to capture the QCM sign-off.
            $this->redirect(\App\Filament\Admin\Pages\IncubationBoard::getUrl(['tab' => 'evaluation', 'evaluate' => $q->id]));
            return;
        }

        $q->workflow_stage = $stage;
        $q->stage_changed_at = now();

        // Archived = fully done and filed. Stamp archived_at and mark the latest run completed,
        // so run history reflects a completed cycle (and a future automation can sweep these).
        if ($stage === WorkflowStage::Archived) {
            $q->archived_at = now();
            $latest = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)
                ->latest('run_date')->latest('id')->first();
            if ($latest && ! $latest->qa_signed_at) {
                $latest->qa_signed_at = now();
                $latest->qa_signed_by = Auth::id();
                $latest->save();
            }
        }
        // Reversibility: moving a card BACKWARD must undo the disposition a forward move set.
        // Any destination other than Archived un-archives it; dropping below QA Sign-off clears
        // the qualified stamp unless this cycle's runs actually still qualify them.
        if ($stage !== WorkflowStage::Archived) {
            $q->archived_at = null;
        }
        if ($stage === WorkflowStage::Archived) {
            $q->status = \App\Enums\QualificationStatus::Qualified;
        } elseif ($stage !== WorkflowStage::QaSignoff) {
            $passes = app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q)
                ->filter(fn ($r) => (($r->result->value ?? $r->result) === 'pass'))->count();
            $stillQualified = $q->runs_required > 0 && $passes >= (int) $q->runs_required
                && $q->due_date && ! $q->due_date->isPast();
            if ($stillQualified) {
                $q->status = \App\Enums\QualificationStatus::Qualified;
            } else {
                // not (yet) qualified this cycle: drop the qualified stamp so the card no longer
                // reads Qualified. due_date is left intact for access/lifecycle logic.
                $q->qualified_date = null;
                $q->status = ($q->due_date && $q->due_date->isPast())
                    ? \App\Enums\QualificationStatus::Lapsed
                    : ($passes > 0 ? \App\Enums\QualificationStatus::InProgress : \App\Enums\QualificationStatus::Pending);
            }
        }

        $q->save();
        // No success toast on drag-move: the card visibly moving is the confirmation.
        // (Errors/auth failures above still notify, and the workflow automations still fire.)
    }

    /** Bulk action: book the selected people into the next available run day. */
    public function bulkBookRunDay(array $ids): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) {
            Notification::make()->danger()->title('Not authorized')
                ->body('You need scheduling permission to book run days.')->send();
            return;
        }
        $scheduler = app(\App\Services\AutoScheduler::class);
        $booked = 0; $skipped = 0;
        foreach ($ids as $id) {
            $q = Qualification::with('personnel')->find((int) $id);
            if (! $q) { continue; }
            // only people who are class-complete / ready (not already scheduled or qualified)
            if (in_array($q->workflow_stage, [WorkflowStage::ClassComplete, WorkflowStage::ClassPending], true)) {
                if ($scheduler->bookNext($q)) { $booked++; } else { $skipped++; }
            } else {
                $skipped++;
            }
        }
        Notification::make()->success()->title('Bulk scheduling done')
            ->body("Booked {$booked}. Skipped {$skipped} (not ready or no slot available).")->send();
    }
}
