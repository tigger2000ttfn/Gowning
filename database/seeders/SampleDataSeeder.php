<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\Personnel;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /** Seeds demo classes, sessions, personnel, and a QC Micro user. */
    public function run(): void
    {
        // A QC Micro admin (approved) so there is a non-superadmin to log in with
        User::updateOrCreate(
            ['email' => 'qcmicro@matcastellas.com'],
            [
                'name' => 'QC Micro Admin',
                'password' => Hash::make('QcMicro!2026'),
                'role' => Role::QcMicro,
                'is_active' => true,
                'approval_status' => 'approved',
                'approved_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        // Gowning class + upcoming sessions (these show on the public page)
        $class = TrainingClass::updateOrCreate(
            ['name' => 'Aseptic Gowning Qualification Class'],
            [
                'code' => 'GOWN-101',
                'description' => 'Required gowning class. Completion is the prerequisite for initial cleanroom qualification runs.',
                'is_gowning_prerequisite' => true,
                'is_published' => true,
            ],
        );

        $refresher = TrainingClass::updateOrCreate(
            ['name' => 'Annual Gowning Refresher'],
            [
                'code' => 'GOWN-201',
                'description' => 'Annual refresher for already-qualified personnel.',
                'is_gowning_prerequisite' => false,
                'is_published' => true,
            ],
        );

        foreach ([7, 14, 21] as $i => $days) {
            ClassSession::updateOrCreate(
                ['training_class_id' => $class->id, 'session_date' => now()->addDays($days)->toDateString()],
                [
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'location' => 'MATC Training Room ' . ($i + 1),
                    'instructor' => ['M. Reyes', 'L. Tran', 'D. Okafor'][$i],
                    'capacity' => 20,
                    'status' => 'open',
                ],
            );
        }
        ClassSession::updateOrCreate(
            ['training_class_id' => $refresher->id, 'session_date' => now()->addDays(10)->toDateString()],
            ['start_time' => '13:00', 'end_time' => '15:00', 'location' => 'MATC Training Room 2',
             'instructor' => 'M. Reyes', 'capacity' => 25, 'status' => 'open'],
        );

        // A few sample personnel
        $people = [
            ['EMP1001', 'Alex', 'Morgan', 'Aseptic Filling'],
            ['EMP1002', 'Jordan', 'Lee', 'Aseptic Filling'],
            ['EMP1003', 'Sam', 'Patel', 'QC Micro'],
            ['EMP1004', 'Casey', 'Nguyen', 'Manufacturing'],
        ];
        foreach ($people as [$eid, $first, $last, $dept]) {
            Personnel::updateOrCreate(
                ['employee_id' => $eid],
                ['first_name' => $first, 'last_name' => $last,
                 'email' => strtolower("$first.$last@matcastellas.com"),
                 'department' => $dept, 'is_active' => true],
            );
        }

        $this->command?->info('Sample classes, sessions, personnel, and QC Micro user seeded.');
    }
}
