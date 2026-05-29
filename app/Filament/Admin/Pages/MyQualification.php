<?php

namespace App\Filament\Admin\Pages;

use App\Models\ClassEnrollment;
use App\Models\Personnel;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyQualification extends Page
{
    public function getHeading(): string { return ''; }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'My Qualification';
    protected static ?int $navigationSort = -10; // top of sidebar
    protected static ?string $title = 'My Qualification';

    protected string $view = 'filament.pages.my-qualification';

    public ?Personnel $person = null;

    public function mount(): void
    {
        // Find the personnel record linked to the logged-in user (by user_id or email).
        $user = Auth::user();
        $this->person = Personnel::where('user_id', $user?->id)
            ->orWhere('email', $user?->email)
            ->with(['qualification.comments.user', 'runs' => fn ($q) => $q->latest('run_date'), 'classCompletions'])
            ->first();
    }

    public function getViewData(): array
    {
        $upcoming = ClassEnrollment::query()
            ->with('classSession.trainingClass')
            ->where(function ($q) {
                $q->where('personnel_id', $this->person?->id)
                  ->orWhere('email', Auth::user()?->email);
            })
            ->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '>=', now()->toDateString()))
            ->get();

        return [
            'person' => $this->person,
            'qualification' => $this->person?->qualification,
            'runs' => $this->person?->runs ?? collect(),
            'classes' => $this->person?->classCompletions ?? collect(),
            'enrollments' => $upcoming,
        ];
    }
}
