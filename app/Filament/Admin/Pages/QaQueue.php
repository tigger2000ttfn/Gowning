<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\ElectronicSignature;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class QaQueue extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'QA Sign-off Queue';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'QA Sign-off Queue';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-queue';

    public function getQueue()
    {
        return Qualification::with('personnel')
            ->whereIn('workflow_stage', [WorkflowStage::QaReview->value, WorkflowStage::ResultsReleased->value])
            ->orderBy('stage_changed_at')
            ->get();
    }

    public function getFailed()
    {
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Failed->value)
            ->get();
    }

    public function canApprove(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::QaApprove);
    }

    /** QA sign-off with two-component electronic signature (Part 11). */
    public function signOffAction(): Action
    {
        return Action::make('signOff')
            ->label('Sign Off')
            ->icon('heroicon-m-pencil-square')
            ->color('success')
            ->modalHeading('Electronic Signature, QA Sign-off')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Sign')
            ->schema(function (array $arguments) {
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                $name = $q?->personnel?->full_name ?? 'this qualification';
                $esig = (bool) Setting::get('esig_required', true);
                $fields = [
                    Placeholder::make('statement')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="font-size:13.5px;line-height:1.5;">By signing, I, <strong>' . e(Auth::user()?->name) .
                            '</strong>, certify that I have reviewed the qualification record for <strong>' . e($name) .
                            '</strong> and approve it as complete. This electronic signature is the legally binding equivalent of my handwritten signature.</div>'
                        )),
                    TextInput::make('meaning')->label('Signature Meaning')
                        ->default('Approved')->required(),
                ];
                if ($esig) {
                    $fields[] = TextInput::make('password')->label('Confirm Your Password')->password()->required()
                        ->helperText('Re-enter your password to apply your electronic signature.');
                }
                return $fields;
            })
            ->action(function (array $data, array $arguments) {
                if (! $this->canApprove()) {
                    Notification::make()->danger()->title('Not authorized')->send();
                    return;
                }
                // verify identity (manifestation) if e-sig required
                if ((bool) Setting::get('esig_required', true)) {
                    if (! Hash::check($data['password'] ?? '', Auth::user()->password)) {
                        Notification::make()->danger()->title('Signature failed')
                            ->body('Password did not match. Sign-off not applied.')->send();
                        return;
                    }
                }
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;

                // record the electronic signature
                ElectronicSignature::create([
                    'signable_type' => Qualification::class,
                    'signable_id' => $q->id,
                    'user_id' => Auth::id(),
                    'signer_name' => Auth::user()->name,
                    'meaning' => $data['meaning'] ?? 'Approved',
                    'statement' => 'QA sign-off: qualification approved as complete.',
                    'signed_at' => now(),
                ]);

                $q->workflow_stage = WorkflowStage::QaSignoff;
                $q->stage_changed_at = now();
                $q->status = 'qualified';
                if (! $q->qualified_date) $q->qualified_date = now();
                if (! $q->due_date) $q->due_date = now()->addMonths((int) Setting::get('cycle_months', 12));
                $q->save();

                Notification::make()->success()->title('Signed off')
                    ->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
            });
    }

    public function signOff(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->body('QA approver role required to sign off.')->send();
            return;
        }
        $q = Qualification::with('personnel')->find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::QaSignoff;
        $q->stage_changed_at = now();
        $q->status = 'qualified';
        if (! $q->qualified_date) $q->qualified_date = now();
        if (! $q->due_date) $q->due_date = now()->addMonths((int) \App\Models\Setting::get('cycle_months', 12));
        $q->save();
        Notification::make()->success()->title('Signed off')
            ->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
    }

    public function markFailed(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();
            return;
        }
        $q = Qualification::find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::Failed;
        $q->stage_changed_at = now();
        $q->save();
        Notification::make()->warning()->title('Marked for determination')->send();
    }
}
