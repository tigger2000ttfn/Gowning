<?php

namespace App\Console\Commands;

use App\Models\JobTitle;
use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time (idempotent) import of the personnel roster from database/seed/personnel.csv.
 *
 * CSV columns: PERSONNEL (LIMS username), FULLNAME, TITLE (job title), ANUMBER (A-number), EMAIL.
 * Rules:
 *  - Skip system / role accounts (underscore-prefixed, Admin-titled, scheduler bots).
 *  - Dedup by business key = ANUMBER if present, else the LIMS username. Merge duplicate rows,
 *    preferring the row that carries the most detail.
 *  - employee_id = ANUMBER if present, else the LIMS username.
 *  - lims_username = the PERSONNEL column.
 *  - Split FULLNAME into first / last (handles "Last, First" comma form).
 *  - Email = provided, else generated firstname.lastname@astellas.com.
 *  - Every distinct TITLE is also added to the Job Title reference list.
 *
 * Re-running updates existing rows (upsert on employee_id); it never duplicates.
 */
class ImportPersonnel extends Command
{
    protected $signature = 'gqs:import-personnel {--path= : CSV path (defaults to database/seed/personnel.csv)} {--dry : Parse and report without writing}';
    protected $description = 'Import the personnel roster from the seed CSV';

    /** PERSONNEL values that are system/role accounts, not people. */
    protected array $skipKeys = ['SCHEDULER', 'SCHEDULER_EM', 'SYSTEM', 'ME_SCHED01'];

    /** Manual name corrections by LIMS username (source data was ambiguous). */
    protected array $nameOverrides = [
        'FKUMAR' => ['first' => 'Praveen', 'last' => 'Kumar'],
    ];

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('seed/personnel.csv');
        if (! is_file($path)) {
            $this->error("CSV not found: {$path}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        $merged = $this->mergeRows($rows);

        $dry = (bool) $this->option('dry');
        $created = 0; $updated = 0; $titles = 0; $skipped = 0;

        foreach ($merged as $r) {
            if ($r['skip']) { $skipped++; continue; }

            // Job title reference list
            if ($r['title'] !== '') {
                if ($dry) {
                    if (! JobTitle::where('name', $r['title'])->exists()) $titles++;
                } else {
                    $jt = JobTitle::firstOrCreate(['name' => $r['title']], ['is_active' => true]);
                    if ($jt->wasRecentlyCreated) $titles++;
                }
            }

            $existing = Personnel::withTrashed()->where('employee_id', $r['employee_id'])->first();

            if ($dry) {
                $existing ? $updated++ : $created++;
                continue;
            }

            $data = [
                'first_name'    => $r['first_name'],
                'last_name'     => $r['last_name'],
                'email'         => $r['email'],
                'job_title'     => $r['title'] ?: null,
                'lims_username' => $r['lims_username'],
                'is_active'     => false,   // imported inactive for now; activate as needed
            ];
            if ($existing) {
                $existing->fill($data)->save();
                $updated++;
            } else {
                Personnel::create($data + ['employee_id' => $r['employee_id']]);
                $created++;
            }
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Personnel: {$created} " . ($dry ? 'to create' : 'created') . ", {$updated} " . ($dry ? 'to update' : 'updated') . ", {$skipped} system/role rows skipped.");
        $this->info("Job titles " . ($dry ? 'to add' : 'added') . " to reference list: {$titles}.");
        return self::SUCCESS;
    }

    /** Read raw CSV rows into associative arrays. */
    protected function readCsv(string $path): array
    {
        $out = [];
        $fh = fopen($path, 'r');
        $header = null;
        while (($cols = fgetcsv($fh)) !== false) {
            if ($header === null) { $header = array_map('trim', $cols); continue; }
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) continue;
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = isset($cols[$i]) ? trim(preg_replace('/\s+/', ' ', (string) $cols[$i])) : '';
            }
            $out[] = $row;
        }
        fclose($fh);
        return $out;
    }

    /** Merge duplicate rows by business key; compute final fields + skip flag. */
    protected function mergeRows(array $rows): array
    {
        // Pass 1: merge rows that share a LIMS username (the bare row + the detailed row).
        $byLims = [];
        foreach ($rows as $row) {
            $lims = trim($row['PERSONNEL'] ?? '');
            if ($lims === '') continue;
            if (! isset($byLims[$lims])) {
                $byLims[$lims] = ['PERSONNEL' => $lims, 'FULLNAME' => '', 'TITLE' => '', 'ANUMBER' => '', 'EMAIL' => ''];
            }
            foreach (['FULLNAME', 'TITLE', 'EMAIL'] as $f) {
                $v = trim($row[$f] ?? '');
                if ($v !== '' && strlen($v) > strlen($byLims[$lims][$f])) $byLims[$lims][$f] = $v;
            }
            $a = trim($row['ANUMBER'] ?? '');
            if ($a !== '' && $byLims[$lims]['ANUMBER'] === '') $byLims[$lims]['ANUMBER'] = $a;
        }

        // Pass 2: collapse any records that share the same non-empty A-number (same person,
        // different LIMS username, e.g. a typo'd second account). Keep the first seen.
        $seenAnum = [];
        $result = [];
        foreach ($byLims as $r) {
            $a = $r['ANUMBER'];
            if ($a !== '' && isset($seenAnum[$a])) continue;
            if ($a !== '') $seenAnum[$a] = true;

            $lims = $r['PERSONNEL'];
            $skip = $this->isSystemAccount($lims, $r['TITLE'], $r['FULLNAME']);
            $provided = strtolower(trim($r['EMAIL']));
            [$first, $last] = $this->splitName($r['FULLNAME'], $lims, $provided);
            if (isset($this->nameOverrides[strtoupper($lims)])) {
                $first = $this->nameOverrides[strtoupper($lims)]['first'] ?? $first;
                $last = $this->nameOverrides[strtoupper($lims)]['last'] ?? $last;
            }
            $email = $provided;
            if ($email === '' && $first !== '' && $last !== '') {
                $email = $this->generateEmail($first, $last);
            }
            $result[] = [
                'skip'          => $skip,
                'employee_id'   => $r['ANUMBER'] !== '' ? $r['ANUMBER'] : $lims,
                'lims_username' => $lims,
                'first_name'    => $first ?: $lims,
                'last_name'     => $last,
                'title'         => $r['TITLE'],
                'email'         => $email ?: null,
            ];
        }
        return $result;
    }

    protected function isSystemAccount(string $lims, string $title, string $name): bool
    {
        if (Str::startsWith($lims, '_')) return true;
        if (strcasecmp(trim($title), 'Admin') === 0) return true;
        if (in_array(strtoupper($lims), $this->skipKeys, true)) return true;
        return false;
    }

    /** Split a full name into [first, last].
     *  Strongest signal: LIMS username = first-initial + full last name, so the name token
     *  that the LIMS username ends with is the LAST name. Falls back to a provided email in
     *  firstname.lastname form, then to positional order. */
    protected function splitName(string $full, string $lims = '', string $providedEmail = ''): array
    {
        $full = trim($full);
        $full = preg_replace('/\(.*?\)/u', '', $full);
        $full = preg_replace('/\[.*?\]/u', '', $full);
        $full = trim(preg_replace('/\s+/', ' ', $full));
        if ($full === '') return ['', ''];

        // initial positional split (handles "Last, First")
        if (str_contains($full, ',')) {
            [$last, $first] = array_pad(explode(',', $full, 2), 2, '');
            $first = trim($first); $last = trim($last);
        } else {
            $parts = preg_split('/\s+/', $full);
            $first = array_shift($parts);
            $last = implode(' ', $parts);
        }

        $alnum = fn ($s) => strtoupper(preg_replace('/[^A-Za-z]/', '', $s));
        $limsA = $alnum($lims);

        // LIMS suffix check (primary): whichever token the LIMS username ends with is the last name.
        if ($limsA !== '' && $first !== '' && $last !== '') {
            $fA = $alnum($first); $lA = $alnum($last);
            $endsFirst = $fA !== '' && str_ends_with($limsA, $fA);
            $endsLast  = $lA !== '' && str_ends_with($limsA, $lA);
            if ($endsLast && ! $endsFirst) {
                // already first/last; keep
            } elseif ($endsFirst && ! $endsLast) {
                [$first, $last] = [$last, $first]; // reversed (e.g. "Patel Prachi" with LIMS PPATEL)
            }
        } elseif ($providedEmail !== '' && str_contains($providedEmail, '@astellas.com') && $first !== '' && $last !== '') {
            // secondary: email order, only when LIMS gave no answer
            $localTok = array_values(array_filter(explode('.', explode('@', $providedEmail)[0]), fn ($t) => $t !== '' && $t !== 'contractor'));
            if (count($localTok) === 2) {
                $a = strtolower($localTok[0]); $b = strtolower($localTok[1]);
                $set = [strtolower($first), strtolower($last)]; sort($set);
                $emailSet = [$a, $b]; sort($emailSet);
                if ($set === $emailSet && [$a, $b] !== [strtolower($first), strtolower($last)]) {
                    $first = $a; $last = $b;
                }
            }
        }

        return [Str::title($first), Str::title($last)];
    }

    protected function generateEmail(string $first, string $last): string
    {
        $f = preg_replace('/[^a-z]/', '', strtolower($first));
        $l = preg_replace('/[^a-z\-]/', '', strtolower($last));
        if ($f === '' || $l === '') return '';
        return "{$f}.{$l}@astellas.com";
    }
}
