<?php

namespace App\Console\Commands;

use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles personnel Employee IDs against an authoritative roster CSV (database/data/employee_roster.csv,
 * pipe-delimited: last_name|first_name|employee_id).
 *
 * Rules (per request):
 *   - Only consider personnel whose CURRENT employee_id does NOT start with "A" (the A-prefixed ones are
 *     treated as already correct and are never touched).
 *   - Match to the roster by EXACT last name, then EXACT first name (case-insensitive, trimmed).
 *   - Replace the employee_id with the roster value ONLY on a single exact match.
 *   - Never replace when: no match, more than one match (ambiguous), the roster id is blank, the roster id
 *     is unchanged, or the new id already belongs to a different person (would collide).
 *
 * Dry-run by default; pass --force to apply. Use --show-skips to also list every skipped person.
 */
class FixEmployeeIds extends Command
{
    protected $signature = 'gqs:fix-employee-ids {--force : Apply the changes (otherwise dry-run)} {--show-skips : List skipped/no-match personnel too} {--file= : Path to the roster CSV (default database/data/employee_roster.csv)}';
    protected $description = 'Replace non-A employee IDs with the authoritative roster value, matched by exact last + first name';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $path = $this->option('file') ?: database_path('data/employee_roster.csv');

        if (! is_file($path)) {
            $this->error("Roster file not found: {$path}");
            return self::FAILURE;
        }

        // Build the roster index keyed by lower(last)|lower(first); track duplicates as ambiguous.
        $byName = [];      // key => employee_id
        $ambiguous = [];   // key => true
        $fh = fopen($path, 'r');
        $header = true;
        while (($cols = fgetcsv($fh, 0, '|')) !== false) {
            if ($header) { $header = false; continue; }
            if (count($cols) < 3) continue;
            $last = trim((string) $cols[0]);
            $first = trim((string) $cols[1]);
            $emp = trim((string) $cols[2]);
            if ($last === '' || $first === '' || $emp === '') continue;
            $key = mb_strtolower($last) . '|' . mb_strtolower($first);
            if (isset($byName[$key]) && $byName[$key] !== $emp) {
                $ambiguous[$key] = true;
            }
            $byName[$key] = $emp;
        }
        fclose($fh);
        $this->info('Roster entries loaded: ' . count($byName) . ' (' . count($ambiguous) . ' ambiguous name(s) will be skipped)');

        // Pre-index existing employee IDs so we can detect collisions (a new id already in use by someone else).
        $idOwner = Personnel::query()->pluck('id', 'employee_id'); // employee_id => personnel id

        $candidates = Personnel::query()
            ->where(function ($q) {
                $q->whereRaw("employee_id NOT ILIKE 'A%'")->orWhereNull('employee_id');
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $this->info('Candidates (employee_id not starting with A): ' . $candidates->count());
        $this->newLine();

        $toChange = [];
        $noMatch = []; $ambig = []; $collide = []; $unchanged = [];

        foreach ($candidates as $p) {
            $key = mb_strtolower(trim((string) $p->last_name)) . '|' . mb_strtolower(trim((string) $p->first_name));
            if (! isset($byName[$key])) { $noMatch[] = $p; continue; }
            if (isset($ambiguous[$key])) { $ambig[] = $p; continue; }
            $newId = $byName[$key];
            if ($newId === (string) $p->employee_id) { $unchanged[] = $p; continue; }
            // Collision: the target id already belongs to a different person.
            if (isset($idOwner[$newId]) && (int) $idOwner[$newId] !== (int) $p->id) {
                $collide[] = [$p, $newId]; continue;
            }
            $toChange[] = [$p, $newId];
        }

        // Report the planned changes.
        if ($toChange) {
            $this->line('<info>WILL UPDATE ' . count($toChange) . ':</info>');
            $this->table(
                ['Name', 'Old ID', 'New ID'],
                collect($toChange)->map(fn ($r) => [
                    $r[0]->last_name . ', ' . $r[0]->first_name,
                    $r[0]->employee_id ?: '(blank)',
                    $r[1],
                ])->all()
            );
        } else {
            $this->warn('No employee IDs need changing.');
        }

        $this->line('Skipped: ' . count($noMatch) . ' no exact name match, ' . count($ambig) . ' ambiguous, '
            . count($collide) . ' would collide with an existing ID, ' . count($unchanged) . ' already correct.');

        if ($collide) {
            $this->newLine();
            $this->warn('COLLISIONS (left unchanged - the roster ID is already used by someone else):');
            foreach ($collide as [$p, $newId]) {
                $this->line("  {$p->last_name}, {$p->first_name}: {$p->employee_id} -> {$newId} (already owned by personnel #" . $idOwner[$newId] . ')');
            }
        }

        if ($this->option('show-skips')) {
            $this->newLine();
            $this->line('NO MATCH (' . count($noMatch) . '):');
            foreach ($noMatch as $p) { $this->line("  {$p->last_name}, {$p->first_name}  [{$p->employee_id}]"); }
            if ($ambig) {
                $this->line('AMBIGUOUS (' . count($ambig) . '):');
                foreach ($ambig as $p) { $this->line("  {$p->last_name}, {$p->first_name}  [{$p->employee_id}]"); }
            }
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Dry-run only. Re-run with --force to apply the ' . count($toChange) . ' update(s).');
            return self::SUCCESS;
        }

        if (! $toChange) { return self::SUCCESS; }

        DB::transaction(function () use ($toChange) {
            foreach ($toChange as [$p, $newId]) {
                $oldId = $p->employee_id;
                $p->employee_id = $newId;
                $p->save();
                // Keep the two denormalized copies of employee_id in step with the personnel record,
                // matched by personnel_id (authoritative) so class history shows the corrected ID.
                \App\Models\ClassCompletion::where('personnel_id', $p->id)->update(['employee_id' => $newId]);
                \App\Models\ClassEnrollment::where('personnel_id', $p->id)->update(['employee_id' => $newId]);
            }
        });

        $this->newLine();
        $this->info('Applied ' . count($toChange) . ' employee ID update(s).');
        return self::SUCCESS;
    }
}
