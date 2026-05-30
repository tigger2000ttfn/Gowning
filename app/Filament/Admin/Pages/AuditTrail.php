<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AuditTrail extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview)
            || $u->hasCapability(Capability::QaApprove)
            || $u->hasCapability(Capability::SystemSettings)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return false; } // lives in Manage
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Audit Trail';
    protected static ?string $title = 'Audit Trail';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.audit-trail';

    // filters
    public string $fLog = '';
    public string $fEvent = '';
    public string $fSearch = '';

    public function getLogNames(): array
    {
        return Activity::query()->select('log_name')->distinct()->pluck('log_name')->filter()->values()->all();
    }

    public function getEntries()
    {
        return Activity::with('causer', 'subject')
            ->when($this->fLog, fn ($q) => $q->where('log_name', $this->fLog))
            ->when($this->fEvent, fn ($q) => $q->where('event', $this->fEvent))
            ->when($this->fSearch, fn ($q) => $q->where('description', 'ilike', "%{$this->fSearch}%"))
            ->latest()
            ->limit(200)
            ->get();
    }

    /** Only a Super User may delete audit entries (e.g. clearing test data). */
    public function canDeleteAudit(): bool
    {
        return (bool) \Illuminate\Support\Facades\Auth::user()?->role?->isSuperUser();
    }

    public function deleteEntry(int $id): void
    {
        if (! $this->canDeleteAudit()) {
            \Filament\Notifications\Notification::make()->danger()->title('Not Authorized')
                ->body('Only a Super User can delete audit entries.')->send();
            return;
        }
        Activity::whereKey($id)->delete();
        \Filament\Notifications\Notification::make()->success()->title('Audit Entry Deleted')->send();
    }
}
