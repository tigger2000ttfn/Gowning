<?php

namespace App\Console\Commands;

use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prepends "A" to any employee_id that starts with a digit (the Astellas standard ID format is
 * A + number, but some roster rows arrived as the bare number, e.g. 4034625 -> A4034625).
 *
 * Leaves alone anything that already starts with a letter (A4..., API..., APG..., AM..., or
 * username-style placeholders). Skips a change that would collide with an existing employee_id.
 * Dry-run by default; pass --force to apply. On apply it also syncs the denormalized employee_id
 * copies on class_completions and class_enrollments by personnel_id.
 */
class PrefixEmployeeIds extends Command
{
    protected $signature = 'gqs:prefix-employee-ids {--force : Apply the changes (otherwise dry-run)}';
    protected $description = 'Prepend "A" to employee IDs that are bare numbers (e.g. 4034625 -> A4034625)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // Candidates: employee_id begins with a digit.
        $candidates = Personnel::query()
            ->whereRaw("employee_id ~ '^[0-9]'")
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $existing = Personnel::query()->pluck('id', 'employee_id'); // employee_id => personnel id

        $toChange = []; $collide = [];
        foreach ($candidates as $p) {
            $newId = 'A' . $p->employee_id;
            if (isset($existing[$newId]) && (int) $existing[$newId] !== (int) $p->id) {
                $collide[] = [$p, $newId]; continue;
            }
            $toChange[] = [$p, $newId];
        }

        $this->info('Employee IDs starting with a digit: ' . $candidates->count());
        if ($toChange) {
            $this->table(
                ['Name', 'Old ID', 'New ID'],
                collect($toChange)->take(500)->map(fn ($r) => [
                    $r[0]->last_name . ', ' . $r[0]->first_name, $r[0]->employee_id, $r[1],
                ])->all()
            );
            $this->line('WILL UPDATE: ' . count($toChange));
        } else {
            $this->warn('No numeric employee IDs to prefix.');
        }
        if ($collide) {
            $this->warn('COLLISIONS (left unchanged - A+number already in use):');
            foreach ($collide as [$p, $newId]) {
                $this->line("  {$p->last_name}, {$p->first_name}: {$p->employee_id} -> {$newId}");
            }
        }

        if (! $force) {
            $this->warn('Dry-run only. Re-run with --force to apply the ' . count($toChange) . ' update(s).');
            return self::SUCCESS;
        }
        if (! $toChange) return self::SUCCESS;

        DB::transaction(function () use ($toChange) {
            foreach ($toChange as [$p, $newId]) {
                $p->employee_id = $newId;
                $p->save();
                \App\Models\ClassCompletion::where('personnel_id', $p->id)->update(['employee_id' => $newId]);
                \App\Models\ClassEnrollment::where('personnel_id', $p->id)->update(['employee_id' => $newId]);
            }
        });

        $this->info('Applied ' . count($toChange) . ' employee ID prefix update(s).');
        return self::SUCCESS;
    }
}
