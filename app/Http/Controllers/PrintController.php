<?php

namespace App\Http\Controllers;

use App\Models\RunSlot;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class PrintController extends Controller
{
    /** Astellas Class Training Form (FORM-AST-36513), prefilled for a class session. */
    public function classAttendanceForm(\Illuminate\Http\Request $request, \App\Models\ClassSession $session, ?string $file = null)
    {
        $this->guard();
        $session->load(['trainingClass', 'instructorUser', 'enrollments.personnel']);

        $trainees = $session->enrollments
            ->filter(fn ($e) => in_array(($e->status instanceof \BackedEnum ? $e->status->value : $e->status), ['signed_up', 'attended', 'completed', 'historical'], true))
            ->sortBy(fn ($e) => $e->personnel?->last_name ?? $e->name)
            ->map(fn ($e) => [
                'name' => $e->personnel?->full_name ?? $e->name ?? '',
                'department' => $e->personnel?->department,
                'date' => $session->session_date?->gmp(),  // date of training, prefilled
            ])->values()->all();

        $header = [
            'training_date' => $session->session_date?->gmp(),
            'document_no' => \App\Models\Setting::get('attendance_form_document_no', ''),
            'revision_no' => \App\Models\Setting::get('attendance_form_revision_no', ''),
            'title' => \App\Models\Setting::get('attendance_form_title', '') ?: $session->trainingClass?->name,
            // Trainer prefills from the assigned instructor, overridable at print time via ?trainer=
            'trainer_name' => $request->query('trainer')
                ?: ($session->instructorUser?->name ?? $session->instructor ?? ''),
            'trainer_date' => $session->session_date?->gmp(),
        ];

        try {
            $bytes = app(\App\Services\AttendanceFormFiller::class)->fill($header, $trainees);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Attendance form fill failed: ' . $e->getMessage());
            abort(500, 'Could not generate the attendance form: ' . $e->getMessage());
        }
        $classSlug = \Illuminate\Support\Str::slug($session->trainingClass?->name ?? 'Class');
        $dateSlug = $session->session_date?->format('Y-m-d') ?? 'session';
        $fname = 'FORM-AST-36513-Class-Training-' . $classSlug . '-' . $dateSlug . '.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fname . '"',
        ]);
    }

    /** Astellas QCM Gowning Qualification Approval (FORM-AST-36749), prefilled for a qualification. */
    public function approvalForm(\Illuminate\Http\Request $request, Qualification $qualification, ?string $file = null)
    {
        $this->guard();
        $qualification->load('personnel');

        // Latest cycle runs to determine outcome + the QCM analyst/date.
        $runs = QualificationRun::where('personnel_id', $qualification->personnel_id)
            ->orderBy('run_date')->orderBy('id')->get();
        $lastRun = $runs->last();
        $type = $qualification->type instanceof \BackedEnum ? $qualification->type->value : $qualification->type;
        $isInitial = $type === 'initial';
        $status = $qualification->status instanceof \BackedEnum ? $qualification->status->value : $qualification->status;
        $passed = $status === 'qualified';

        // QCM "completed by" = the QC Micro analyst recorded on the GQS record as having signed off the
        // result evaluation (run.qcm_signed_by, or the QCM Sign-off e-signature). This comes from the GQS
        // record, which LIMS auto-import/backfill and sync keep current; it is NOT the person viewing.
        $qcmSignedRun = $runs->first(fn ($r) => $r->qcm_signed_by) ?: $lastRun;
        $qcmSig = \App\Models\ElectronicSignature::where('signable_type', QualificationRun::class)
            ->whereIn('signable_id', $runs->pluck('id'))
            ->where('meaning', 'like', '%QCM%')
            ->latest('signed_at')->first();
        $qcmBy = optional($qcmSignedRun?->qcmSignedBy)->name ?? $qcmSig?->signer_name;
        $qcmDate = ($qcmSignedRun?->qcm_signed_at ?? $qcmSig?->signed_at)?->gmp()
            ?? $lastRun?->run_date?->gmp();
        $qaSig = \App\Models\ElectronicSignature::where('signable_type', Qualification::class)
            ->where('signable_id', $qualification->id)->where('meaning', 'like', '%Approv%')
            ->latest('signed_at')->first();

        $data = [
            'personnel_name' => $qualification->personnel?->full_name,
            'is_initial' => $isInitial,
            'is_requal' => ! $isInitial,
            'result_initial' => $isInitial ? ($passed ? 'yes' : 'no') : null,
            'result_requal' => ! $isInitial ? ($passed ? 'yes' : 'no') : null,
            'passed' => $passed,
            'results_attached' => true,
            'qcm_by' => $qcmBy,
            'qcm_date' => $qcmDate,
            'qa_by' => $qaSig?->signer_name,
            'qa_date' => $qaSig?->signed_at?->gmp(),
            'registered' => $passed,
            'next_sample_date' => $qualification->due_date?->gmp(),
            'qa_completed_by' => $qaSig?->signer_name,
            'qa_completed_date' => $qaSig?->signed_at?->gmp(),
        ];

        try {
            $bytes = app(\App\Services\ApprovalFormFiller::class)->fill($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Approval form fill failed: ' . $e->getMessage());
            abort(500, 'Could not generate the approval form: ' . $e->getMessage());
        }
        $nameSlug = \Illuminate\Support\Str::slug($qualification->personnel?->full_name ?? ('qual-' . $qualification->id));
        $eid = $qualification->personnel?->employee_id;
        $typeSlug = $isInitial ? 'Initial' : 'Requalification';
        $dateSlug = now()->format('Y-m-d');
        $fname = 'FORM-AST-36749-Gowning-Approval-' . $nameSlug
            . ($eid ? '-' . $eid : '') . '-' . $typeSlug . '-' . $dateSlug . '.pdf';
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fname . '"',
        ]);
    }

    private function guard(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /** Run Day Roster, printable. ?date=YYYY-MM-DD&pdf=1 */
    public function runDay(\Illuminate\Http\Request $request)
    {
        $this->guard();
        $raw = (string) $request->query('date', now()->toDateString());
        // Be defensive: strip anything after the date (e.g. a stray ':1' suffix) and validate.
        $raw = trim(explode(':', $raw)[0]);
        try {
            $dateC = Carbon::parse($raw);
        } catch (\Throwable $e) {
            $dateC = Carbon::now();
        }
        $date = $dateC->toDateString();
        $slots = RunSlot::with(['reservations' => fn ($q) => $q->whereIn('status', ['approved', 'completed'])->with('personnel'), 'analyst'])
            ->whereDate('slot_date', $date)
            ->orderBy('start_time')->get();

        $data = [
            'date' => $dateC,
            'slots' => $slots,
            'org' => \App\Models\Setting::get('org_name', 'MATC, Astellas'),
            'site' => \App\Models\Setting::get('site_name', 'Manufacturing Technology Center'),
        ];

        if ($request->boolean('pdf', true)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('print.run-day', $data)
                ->setPaper('letter', 'landscape')
                ->stream('run-roster-' . $date . '.pdf');
        }
        return view('print.run-day', $data);
    }

    /** Compliance report, printable. */
    public function report()
    {
        $this->guard();
        $overdue = Qualification::with('personnel')->where('status', 'qualified')
            ->whereNotNull('due_date')->whereDate('due_date', '<', now())->orderBy('due_date')->get();
        $upcoming = Qualification::with('personnel')->where('status', 'qualified')
            ->whereNotNull('due_date')->whereDate('due_date', '>=', now())
            ->whereDate('due_date', '<=', now()->addDays(60))->orderBy('due_date')->get();
        $passes = QualificationRun::where('result', 'pass')->count();
        $fails = QualificationRun::where('result', 'fail')->count();

        $data = [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'passes' => $passes,
            'fails' => $fails,
            'org' => \App\Models\Setting::get('org_name', 'MATC, Astellas'),
            'site' => \App\Models\Setting::get('site_name', 'Manufacturing Technology Center'),
            'generated' => now()->setTimezone('America/New_York'),
        ];

        if (request()->boolean('pdf', true)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('print.report', $data)
                ->setPaper('letter', 'landscape')
                ->stream('compliance-report-' . now()->toDateString() . '.pdf');
        }
        return view('print.report', $data);
    }

    /** Prebuilt reports (QA / QCM / general), rendered to a generic tabular PDF. */
    public function reportByKey(\Illuminate\Http\Request $request, string $key)
    {
        $this->guard();
        $adv = app(\App\Services\RunCycleAdvancer::class);
        $stageLabel = fn ($q) => $q->workflow_stage instanceof \BackedEnum
            ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label())
            : (string) $q->workflow_stage;

        $title = 'Report';
        $columns = [];
        $rows = [];

        switch ($key) {
            case 'roster':
                $title = 'Qualification Status Roster';
                $columns = ['Name', 'Employee', 'Department', 'Type', 'Stage', 'Runs', 'Due Date', 'Qualified'];
                foreach (Qualification::with('personnel')->whereNull('superseded_at')->whereNull('archived_at')->get()
                    ->sortBy(fn ($q) => $q->personnel?->full_name ?? 'zzz') as $q) {
                    $rows[] = [$q->personnel?->full_name ?? 'Unknown', $q->personnel?->employee_id ?: '-', $q->personnel?->department ?: '-',
                        $q->sessionLabel(), $stageLabel($q), ((int) $q->runs_completed) . '/' . ((int) $q->runs_required),
                        $q->due_date?->gmp() ?? '-', $q->qualified_date?->gmp() ?? '-'];
                }
                break;

            case 'qa-signoffs':
                $title = 'QA Sign-Off Log';
                $columns = ['Name', 'Employee', 'Type', 'QA Owner', 'Qualified Date', 'Next Due'];
                foreach (Qualification::with(['personnel', 'qaOwner'])->whereNotNull('qualified_date')
                    ->orderByDesc('qualified_date')->get() as $q) {
                    $rows[] = [$q->personnel?->full_name ?? 'Unknown', $q->personnel?->employee_id ?: '-', $q->sessionLabel(),
                        $q->qaOwner?->name ?? '-', $q->qualified_date?->gmp() ?? '-', $q->due_date?->gmp() ?? '-'];
                }
                break;

            case 'qa-pending':
                $title = 'QA Review Queue';
                $columns = ['Name', 'Employee', 'Type', 'Stage', 'In Stage Since', 'Days Waiting'];
                foreach (Qualification::with('personnel')->whereIn('workflow_stage', ['qa_review', 'qa_signoff'])
                    ->whereNull('superseded_at')->get() as $q) {
                    $since = $q->stage_changed_at ?: $q->updated_at;
                    $rows[] = [$q->personnel?->full_name ?? 'Unknown', $q->personnel?->employee_id ?: '-', $q->sessionLabel(),
                        $stageLabel($q), $since?->gmp() ?? '-', $since ? (int) floor($since->diffInDays(now())) : 0];
                }
                break;

            case 'qa-failures':
                $title = 'Failures & Nonconformances';
                $columns = ['Name', 'Employee', 'Run Date', 'Result', 'Worklist', 'NC / TrackWise'];
                foreach (QualificationRun::with('personnel')->where('result', 'fail')->orderByDesc('run_date')->get() as $r) {
                    $rows[] = [$r->personnel?->full_name ?? 'Unknown', $r->personnel?->employee_id ?: '-',
                        $r->run_date?->gmp() ?? '-', 'Fail', $r->lims_worklist_id ?: '-', $r->lims_nc_number ?: '-'];
                }
                break;

            case 'qcm-results':
                $title = 'QCM Results Log';
                $columns = ['Name', 'Employee', 'Run Date', 'Result', 'Worklist', 'Evaluation', 'Veeva'];
                foreach (QualificationRun::with('personnel')->whereIn('result', ['pass', 'fail'])
                    ->orderByDesc('run_date')->orderByDesc('id')->limit(500)->get() as $r) {
                    $res = $r->result instanceof \BackedEnum ? $r->result->value : $r->result;
                    $rows[] = [$r->personnel?->full_name ?? 'Unknown', $r->personnel?->employee_id ?: '-',
                        $r->run_date?->gmp() ?? '-', ucfirst((string) $res), $r->lims_worklist_id ?: '-',
                        $r->lims_evaluation ?: '-', $r->veeva_doc_number ?: '-'];
                }
                break;

            case 'qcm-incubation':
                $title = 'Incubation Status';
                $columns = ['Name', 'Employee', 'Worklist', 'Run Date', 'Inc Started', 'Status'];
                foreach (QualificationRun::with('personnel')->whereNotNull('incubation_started_at')
                    ->whereNull('results_entered_at')->orderBy('incubation_started_at')->get() as $r) {
                    $status = $r->lims_inc2_end ? 'Complete' : ($r->lims_inc1_start ? 'Incubating' : 'Incubating');
                    $rows[] = [$r->personnel?->full_name ?? 'Unknown', $r->personnel?->employee_id ?: '-',
                        $r->lims_worklist_id ?: '-', $r->run_date?->gmp() ?? '-',
                        $r->incubation_started_at?->gmp() ?? '-', $status];
                }
                break;

            default:
                abort(404);
        }

        $data = [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'org' => \App\Models\Setting::get('org_name', 'MATC, Astellas'),
            'site' => \App\Models\Setting::get('site_name', 'Manufacturing Technology Center'),
            'generated' => now()->setTimezone('America/New_York'),
        ];

        if ($request->boolean('pdf', true)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('print.report-generic', $data)
                ->setPaper('letter', 'landscape')
                ->stream(str_replace(' ', '-', strtolower($title)) . '-' . now()->toDateString() . '.pdf');
        }
        return view('print.report-generic', $data);
    }
}
