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

    /** Show the run-slot reservation request form. */
    public function showRunSignup(RunSlot $slot)
    {
        abort_unless($slot->status === 'open' && $slot->hasCapacity() && ! $slot->slot_date->isPast(), 404);
        return view('public.run-signup', ['slot' => $slot]);
    }

    /** Record a run-slot reservation request from the public page. */
    public function storeRunSignup(Request $request, RunSlot $slot)
    {
        abort_unless($slot->status === 'open' && $slot->hasCapacity() && ! $slot->slot_date->isPast(), 404);

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
            'approval_status' => 'pending',
        ]);

        return redirect()->route('public.home')
            ->with('flash', 'Account requested. An administrator will approve your access shortly.');
    }
}
