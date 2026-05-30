<?php

namespace App\Http\Controllers;

use App\Models\RunSlot;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class PrintController extends Controller
{
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
