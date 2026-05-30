<?php
namespace App\Filament\Admin\Resources\QualificationRunResource\Pages;
use App\Enums\RunResult;
use App\Filament\Admin\Resources\QualificationRunResource;
use App\Models\Personnel;
use App\Services\QualificationEngine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateQualificationRun extends CreateRecord
{
    protected static string $resource = QualificationRunResource::class;

    /**
     * Route the run through the QualificationEngine so status, due date, and
     * counts recompute from full history. Stamp the electronic signature.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $engine = app(QualificationEngine::class);
        $person = Personnel::findOrFail($data['personnel_id']);

        $run = $engine->recordRun(
            $person,
            RunResult::from($data['result']),
            [
                'run_date' => $data['run_date'],
                'notes' => $data['notes'] ?? null,
                'is_seed' => (bool) ($data['is_seed'] ?? false),
                'lims_worklist_id' => $data['lims_worklist_id'] ?? null,
                'veeva_doc_number' => $data['veeva_doc_number'] ?? null,
                'veeva_url' => $data['veeva_url'] ?? null,
                'recorded_by' => Auth::id(),
                'signed_by' => Auth::id(),
                'signed_at' => now(),
                'signature_meaning' => $data['signature_meaning'] ?? null,
            ],
        );

        return $run;
    }
}
