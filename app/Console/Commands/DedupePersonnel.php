<?php

namespace App\Console\Commands;

use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merges duplicate personnel rows (the CSV produced repeats, e.g. two "AWEBER":
 * one full record and one name-only stub, plus a few typo'd usernames sharing one
 * A-number). Picks the most-complete record as canonical, re-points every foreign
 * key to it, and deletes the duplicates.
 *
 *   php artisan gqs:dedupe-personnel            # preview only (no changes)
 *   php artisan gqs:dedupe-personnel --commit   # actually merge + delete
 */
class DedupePersonnel extends Command
{
    protected $signature = 'gqs:dedupe-personnel {--commit : Apply the merge (otherwise dry-run preview only)}';
    protected $description = 'Merge duplicate personnel records into a single canonical record and re-point all references.';

    /** Tables with a personnel_id column that must follow the merge. */
    protected array $fkTables = [
        'qualifications', 'qualification_runs', 'reservations',
        'class_enrollments', 'class_completions', 'run_samples', 'non_conformances',
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'DEDUPE: applying merges.' : 'DEDUPE: dry-run preview (no changes). Re-run with --commit to apply.');

        // Build duplicate groups. Two records are the same person if they share a
        // non-empty employee_id OR a non-empty lims_username (case-insensitive).
        $all = Personnel::orderBy('id')->get();
        $groups = []; // canonical_key => [Personnel, ...]

        $byEmp = [];
        $byUser = [];
        foreach ($all as $p) {
            $emp = trim((string) $p->employee_id);
            $usr = strtoupper(trim((string) $p->lims_username));
            $key = $emp !== '' ? 'EMP:' . $emp : ($usr !== '' ? 'USR:' . $usr : 'ID:' . $p->id);
            $groups[$key][] = $p;
            if ($emp !== '') $byEmp[$emp][] = $p->id;
            if ($usr !== '') $byUser[$usr][] = $p->id;
        }

        $merged = 0; $deleted = 0; $dupeGroups = 0;

        DB::beginTransaction();
        try {
            foreach ($groups as $key => $rows) {
                if (count($rows) < 2) continue;
                $dupeGroups++;

                // Canonical = most complete: has employee_id, then has email, then most fields, then lowest id.
                $canonical = collect($rows)->sortByDesc(function ($p) {
                    $score = 0;
                    if (trim((string) $p->employee_id) !== '') $score += 1000;
                    if (trim((string) $p->email) !== '') $score += 100;
                    foreach (['first_name', 'last_name', 'department', 'job_title', 'lims_username', 'user_id'] as $f) {
                        if (trim((string) $p->{$f}) !== '') $score += 1;
                    }
                    return $score;
                })->first();

                $dupes = collect($rows)->reject(fn ($p) => $p->id === $canonical->id);

                $this->line(sprintf(
                    '  %s  keep #%d (%s / %s) <= merge %s',
                    $key,
                    $canonical->id,
                    $canonical->full_name ?: '(no name)',
                    $canonical->employee_id ?: '(no A#)',
                    $dupes->map(fn ($d) => '#' . $d->id)->implode(', ')
                ));

                foreach ($dupes as $d) {
                    // backfill any field the canonical is missing from the duplicate
                    foreach (['employee_id', 'email', 'first_name', 'last_name', 'department', 'job_title', 'lims_username', 'user_id'] as $f) {
                        if (trim((string) $canonical->{$f}) === '' && trim((string) $d->{$f}) !== '') {
                            $canonical->{$f} = $d->{$f};
                        }
                    }
                    // re-point all FK references
                    foreach ($this->fkTables as $tbl) {
                        if (\Illuminate\Support\Facades\Schema::hasTable($tbl)
                            && \Illuminate\Support\Facades\Schema::hasColumn($tbl, 'personnel_id')) {
                            DB::table($tbl)->where('personnel_id', $d->id)->update(['personnel_id' => $canonical->id]);
                        }
                    }
                    $merged++;
                }

                if ($commit) {
                    $canonical->save();
                    foreach ($dupes as $d) {
                        // ensure the duplicate's qualification (if any) was already repointed; then delete
                        $d->delete();
                        $deleted++;
                    }
                } else {
                    $deleted += $dupes->count();
                }
            }

            // A qualification is hasOne per person; after re-pointing we may now have
            // multiple qualifications on one canonical. Keep the most-progressed one.
            if (\Illuminate\Support\Facades\Schema::hasTable('qualifications')) {
                $dupQuals = DB::table('qualifications')
                    ->select('personnel_id', DB::raw('count(*) as c'))
                    ->groupBy('personnel_id')->having('c', '>', 1)->pluck('personnel_id');
                foreach ($dupQuals as $pid) {
                    $quals = \App\Models\Qualification::where('personnel_id', $pid)->get();
                    // keep the one with the most runs_completed / furthest along
                    $keep = $quals->sortByDesc(fn ($q) => ($q->runs_completed ?? 0) * 10 + ($q->status === \App\Enums\QualificationStatus::Qualified ? 5 : 0))->first();
                    foreach ($quals as $q) {
                        if ($q->id === $keep->id) continue;
                        if ($commit) $q->delete();
                    }
                    $this->line(sprintf('  qualification: person #%d had %d quals, keeping #%d', $pid, $quals->count(), $keep->id));
                }
            }

            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Aborted, rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf('%s %d duplicate group(s): %d record(s) merged, %d to delete.',
            $commit ? 'Merged' : 'Would merge', $dupeGroups, $merged, $deleted));
        if (! $commit && $dupeGroups > 0) {
            $this->warn('No changes were written. Re-run with --commit to apply.');
        }
        return self::SUCCESS;
    }
}
