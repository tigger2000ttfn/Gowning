<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\RunSlot;
use App\Models\Reservation;
use App\Models\Personnel;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PublicController extends Controller
{
    /** Landing page with hero + upcoming published class sessions. */
    public function home()
    {
        $sessions = ClassSession::query()
            ->with('trainingClass')
            ->whereHas('trainingClass', fn ($q) => $q->where('is_published', true))
            ->where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->get()
            ->filter(fn ($s) => $s->isOpen());

        $runSlots = RunSlot::query()
            ->where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')
            ->get()
            ->filter(fn ($s) => $s->hasCapacity());

        return view('public.home', [
            'sessions' => $sessions,
            'runSlots' => $runSlots,
        ]);
    }

    /** Calendar page (FullCalendar shell). */
    public function calendar()
    {
        return view('public.calendar');
    }

    /** JSON feed of all classes + run slots as calendar events. */
    public function calendarEvents()
    {
        $events = [];

        $sessions = ClassSession::with('trainingClass')
            ->whereHas('trainingClass', fn ($q) => $q->where('is_published', true))
            ->get();
        foreach ($sessions as $s) {
            $events[] = [
                'title' => $s->trainingClass->name,
                'start' => $s->session_date->toDateString() . ($s->start_time ? 'T' . $s->start_time : ''),
                'end' => $s->end_time ? $s->session_date->toDateString() . 'T' . $s->end_time : null,
                'color' => '#A4123F',
                'url' => $s->isOpen() ? route('public.signup', $s) : null,
                'extendedProps' => ['type' => 'Gowning Class', 'location' => $s->location, 'seats' => $s->seatsLeft()],
            ];
        }

        $slots = RunSlot::all();
        foreach ($slots as $slot) {
            $events[] = [
                'title' => $slot->cleanroom . ' Run',
                'start' => $slot->slot_date->toDateString() . ($slot->start_time ? 'T' . $slot->start_time : ''),
                'end' => $slot->end_time ? $slot->slot_date->toDateString() . 'T' . $slot->end_time : null,
                'color' => '#C79A2E',
                'url' => ($slot->status === \App\Enums\RunSlotStatus::Open && $slot->hasCapacity() && ! $slot->slot_date->isPast()) ? route('public.run.signup', $slot) : null,
                'extendedProps' => ['type' => 'Run Slot', 'location' => $slot->cleanroom, 'seats' => max(0, $slot->capacity - $slot->approvedCount())],
            ];
        }

        return response()->json($events);
    }

    /** Show the run-slot reservation request form. */
    public function showRunSignup(RunSlot $slot)
    {
        abort_unless($slot->status === \App\Enums\RunSlotStatus::Open && $slot->hasCapacity() && ! $slot->slot_date->isPast(), 404);
        return view('public.run-signup', ['slot' => $slot]);
    }

    /** Record a run-slot reservation request from the public page. */
    public function storeRunSignup(Request $request, RunSlot $slot)
    {
        abort_unless($slot->status === \App\Enums\RunSlotStatus::Open && $slot->hasCapacity() && ! $slot->slot_date->isPast(), 404);

        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
        ]);

        $person = Personnel::where('employee_id', $data['employee_id'])->first();
        if (! $person) {
            return back()->withErrors(['employee_id' => 'No personnel record matches that Employee ID. Contact QC Micro.'])->withInput();
        }

        Reservation::firstOrCreate(
            ['run_slot_id' => $slot->id, 'personnel_id' => $person->id],
            ['status' => 'requested', 'requested_at' => now()],
        );

        return redirect()->route('public.home')
            ->with('flash', "Run-slot request submitted for {$slot->slot_date->format('M j, Y')} in {$slot->cleanroom}. QC Micro will approve it.");
    }

    /** Dedicated page: all open gowning class sessions. */
    public function classes()
    {
        $sessions = ClassSession::query()
            ->with('trainingClass')
            ->whereHas('trainingClass', fn ($q) => $q->where('is_published', true))
            ->where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->get()
            ->filter(fn ($s) => $s->isOpen());

        return view('public.classes', ['sessions' => $sessions]);
    }

    /** Dedicated page: all open qualification run slots. */
    public function runs()
    {
        $runSlots = RunSlot::query()
            ->where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')
            ->get()
            ->filter(fn ($s) => $s->hasCapacity());

        return view('public.runs', ['runSlots' => $runSlots]);
    }

    /** Show the sign-up form for a specific session. */
    public function showSignup(ClassSession $session)
    {
        abort_unless($session->isOpen(), 404);
        return view('public.signup', ['session' => $session->load('trainingClass')]);
    }

    /** Record a public class sign-up. */
    public function storeSignup(Request $request, ClassSession $session)
    {
        abort_unless($session->isOpen(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:50'],
        ]);

        ClassEnrollment::create([
            'class_session_id' => $session->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
            'status' => 'signed_up',
            'signed_up_at' => now(),
        ]);

        return redirect()->route('public.home')
            ->with('flash', "You're signed up for {$session->trainingClass->name} on {$session->session_date->format('M j, Y')}.");
    }

    /** Self-registration form. */
    public function showRegister()
    {
        return view('public.register');
    }

    /**
     * Create a self-registered account in PENDING state.
     * Part 11: pending accounts cannot access the qualification system until an
     * administrator approves them.
     */
    public function storeRegister(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => \App\Enums\Role::Operator,
            'is_active' => true,
            'approval_status' => \App\Models\Setting::get('auto_approve', false) ? 'approved' : 'pending',
            'approved_at' => \App\Models\Setting::get('auto_approve', false) ? now() : null,
        ]);

        $msg = \App\Models\Setting::get('auto_approve', false)
            ? 'Account created. You can sign in now.'
            : 'Account requested. An administrator will approve your access shortly.';

        return redirect()->route('public.home')->with('flash', $msg);
    }

    public function runIcs(RunSlot $slot)
    {
        $start = \Carbon\Carbon::parse($slot->slot_date->toDateString() . ' ' . ($slot->start_time ?: '09:00'));
        $end = $slot->end_time
            ? \Carbon\Carbon::parse($slot->slot_date->toDateString() . ' ' . $slot->end_time)
            : $start->copy()->addHour();
        $ics = \App\Support\IcsBuilder::event(
            'run-' . $slot->id,
            'Cleanroom Gowning Qualification Run',
            $start, $end,
            'Your scheduled gowning qualification run. Arrive a few minutes early.',
            $slot->cleanroom,
            60, // 1-hour reminder baked into the calendar event
        );
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="gowning-run.ics"',
        ]);
    }

    public function classIcs(ClassSession $session)
    {
        $session->loadMissing('trainingClass');
        $start = \Carbon\Carbon::parse($session->session_date->toDateString() . ' ' . ($session->start_time ?: '09:00'));
        $end = $session->end_time
            ? \Carbon\Carbon::parse($session->session_date->toDateString() . ' ' . $session->end_time)
            : $start->copy()->addHours(2);
        $ics = \App\Support\IcsBuilder::event(
            'class-' . $session->id,
            ($session->trainingClass?->name ?? 'Gowning Class'),
            $start, $end,
            'Your scheduled gowning class.',
            $session->location,
            60,
        );
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="gowning-class.ics"',
        ]);
    }
}
