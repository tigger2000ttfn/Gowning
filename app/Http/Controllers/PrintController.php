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
    public function classAttendanceForm(\Illuminate\Http\Request $request, \App\Models\ClassSession $session)
    {
        $this->guard();
        $session->load(['trainingClass', 'instructorUser', 'enrollments.personnel']);

        $trainees = $session->enrollments
            ->filter(fn ($e) => in_array(($e->status instanceof \BackedEnum ? $e->status->value : $e->status), ['signed_up', 'attended', 'completed', 'historical'], true))
            ->sortBy(fn ($e) => $e->personnel?->last_name ?? $e->name)
            ->map(fn ($e) => [
                'name' => $e->personnel?->full_name ?? $e->name ?? '',
                'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
                'department' => $e->personnel?->department,
                'date' => $session->session_date?->format('d M Y'),  // date of training, prefilled
            ])->values()->all();

        $header = [
            'training_date' => $session->session_date?->format('d M Y'),
            'document_no' => \App\Models\Setting::get('attendance_form_document_no', ''),
            'revision_no' => \App\Models\Setting::get('attendance_form_revision_no', ''),
            'title' => \App\Models\Setting::get('attendance_form_title', '') ?: $session->trainingClass?->name,
            // Trainer prefills from the assigned instructor, overridable at print time via ?trainer=
            'trainer_name' => $request->query('trainer')
                ?: ($session->instructorUser?->name ?? $session->instructor ?? ''),
        ];

        $bytes = app(\App\Services\AttendanceFormFiller::class)->fill($header, $trainees);
        $fname = 'FORM-AST-36513-' . ($session->session_date?->format('Y-m-d') ?? 'session') . '.pdf';

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
        $date = $request->query('date', now()->toDateString());
        $slots = RunSlot::with(['reservations' => fn ($q) => $q->whereIn('status', ['approved', 'completed'])->with('personnel'), 'analyst'])
            ->whereDate('slot_date', $date)
            ->orderBy('start_time')->get();

        $data = [
            'date' => Carbon::parse($date),
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
}
