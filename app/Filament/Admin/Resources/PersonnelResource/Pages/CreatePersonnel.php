<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Enums\QualificationType;
use App\Enums\QualificationStatus;
use App\Enums\WorkflowStage;
use App\Filament\Admin\Resources\PersonnelResource;
use App\Models\ClassCompletion;
use App\Models\Qualification;
use App\Models\Setting;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreatePersonnel extends CreateRecord
{
    protected static string $resource = PersonnelResource::class;

    protected array $onboard = [];

    /** Pull the form-only onboarding fields out before the Personnel row is saved. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach (['onboard_type', 'onboard_due_date', 'onboard_class_done', 'onboard_class_date'] as $k) {
            if (array_key_exists($k, $data)) { $this->onboard[$k] = $data[$k]; unset($data[$k]); }
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $person = $this->record;
        $type = $this->onboard['onboard_type'] ?? 'initial';
        $dueDate = $this->onboard['onboard_due_date'] ?? null;
        $classDone = (bool) ($this->onboard['onboard_class_done'] ?? false);
        $classDate = $this->onboard['onboard_class_date'] ?? null;

        // Only build an onboarding qualification if the wizard's onboarding step was used and the
        // detailed Qualification Setup did not already create one.
        if (! $person->qualification && $dueDate) {
            $isAnnual = $type === 'annual';
            $cycleType = $isAnnual ? QualificationType::Annual : QualificationType::Initial;

            // Transfer who already took the class -> record it + skip the class step.
            $recordClass = $isAnnual && $classDone && $classDate;
            $stage = $recordClass ? WorkflowStage::ClassComplete : WorkflowStage::ClassPending;

            $qual = Qualification::create([
                'personnel_id' => $person->id,
                'type' => $cycleType,
                'status' => QualificationStatus::Pending,
                'runs_required' => $cycleType->runsRequired(),
                'runs_completed' => 0,
                'due_date' => Carbon::parse($dueDate)->toDateString(),
                'workflow_stage' => $stage,
                'stage_changed_at' => now(),
                'class_on_file' => (bool) $recordClass,
                'class_on_file_date' => $recordClass ? Carbon::parse($classDate)->toDateString() : null,
            ]);

            if ($recordClass) {
                ClassCompletion::create([
                    'personnel_id' => $person->id,
                    'employee_id' => $person->employee_id,
                    'class_name' => 'Gowning Qualification Class',
                    'completion_date' => Carbon::parse($classDate)->toDateString(),
                    'source' => 'manual',
                ]);
            }

            app(\App\Services\QualificationSeeder::class)->reconcile($qual);
            return;
        }

        // Fallback: detailed setup created a qualification - reconcile as before.
        $q = $person->qualification;
        if ($q) {
            app(\App\Services\QualificationSeeder::class)->reconcile($q);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
