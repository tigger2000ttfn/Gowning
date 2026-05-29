<?php

namespace App\Enums;

/** Every discrete permission in the system. Gates read these from the role matrix. */
enum Capability: string
{
    case ManageScheduling   = 'manage_scheduling';    // classes, slots, run day, reservations
    case RecordRuns         = 'record_runs';          // record run pass/fail + sampling
    case ManageClasses      = 'manage_classes';       // training classes + sessions
    case ManageAttendance   = 'manage_attendance';    // mark attendance / completions
    case ManagePersonnel    = 'manage_personnel';     // personnel roster
    case ViewQualifications = 'view_qualifications';  // see all qualifications
    case QaReview           = 'qa_review';            // comment, review failed runs
    case QaApprove          = 'qa_approve';           // approve/sign determinations, override due dates
    case ViewReports        = 'view_reports';         // reports + exports
    case ImportData         = 'import_data';          // bulk CSV import
    case ManageUsers        = 'manage_users';         // user accounts + approvals
    case ManageRoles        = 'manage_roles';         // edit the permission matrix
    case SystemSettings     = 'system_settings';      // qualification settings config
    case ViewOnly           = 'view_only';            // read-only access to most screens

    public function label(): string
    {
        return match ($this) {
            self::ManageScheduling   => 'Manage Scheduling',
            self::RecordRuns         => 'Record Run Results',
            self::ManageClasses      => 'Manage Classes',
            self::ManageAttendance   => 'Manage Attendance',
            self::ManagePersonnel    => 'Manage Personnel',
            self::ViewQualifications => 'View Qualifications',
            self::QaReview           => 'QA Review',
            self::QaApprove          => 'QA Approve / Sign',
            self::ViewReports        => 'View Reports',
            self::ImportData         => 'Import Data',
            self::ManageUsers        => 'Manage Users',
            self::ManageRoles        => 'Manage Roles & Permissions',
            self::SystemSettings     => 'System Settings',
            self::ViewOnly           => 'View Only Access',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageScheduling, self::RecordRuns, self::ManageClasses, self::ManageAttendance => 'Scheduling & Runs',
            self::ManagePersonnel, self::ViewQualifications => 'Personnel & Qualifications',
            self::QaReview, self::QaApprove => 'Quality Assurance',
            self::ViewReports, self::ViewOnly => 'Reporting & Access',
            self::ImportData, self::ManageUsers, self::ManageRoles, self::SystemSettings => 'Administration',
        };
    }
}
