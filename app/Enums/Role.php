<?php

namespace App\Enums;

enum Role: string
{
    case SuperUser           = 'super_user';
    case SiteAdmin           = 'site_admin';
    case PowerUser           = 'power_user';
    case QaApprover          = 'qa_approver';
    case Qa                  = 'qa';
    case QcmAdmin            = 'qcm_admin';
    case QcmScheduler        = 'qcm_scheduler';
    case Qcm                 = 'qcm';
    case TrainingCoordinator = 'training_coordinator';
    case ViewOnly            = 'view_only';
    case Operator            = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::SuperUser           => 'Super User',
            self::SiteAdmin           => 'Site Admin',
            self::PowerUser           => 'Power User',
            self::QaApprover          => 'QA Approver',
            self::Qa                  => 'QA',
            self::QcmAdmin            => 'QCM Admin',
            self::QcmScheduler        => 'QCM Scheduler',
            self::Qcm                 => 'QCM',
            self::TrainingCoordinator => 'Training Coordinator',
            self::ViewOnly            => 'View Only',
            self::Operator            => 'Operator',
        };
    }

    /** Super User always has every capability and cannot be locked out. */
    public function isSuperUser(): bool
    {
        return $this === self::SuperUser;
    }

    /** Default capabilities seeded for each role (editable afterward in the matrix). */
    public function defaultCapabilities(): array
    {
        $c = Capability::class;
        return match ($this) {
            self::SuperUser => array_map(fn ($cap) => $cap->value, Capability::cases()),
            self::SiteAdmin => array_map(fn ($cap) => $cap->value, Capability::cases()),
            self::PowerUser => [
                Capability::ManageScheduling->value, Capability::RecordRuns->value,
                Capability::ManageClasses->value, Capability::ManageAttendance->value,
                Capability::ManagePersonnel->value, Capability::ViewQualifications->value,
                Capability::ViewReports->value, Capability::ImportData->value,
            ],
            self::QaApprover => [
                Capability::ViewQualifications->value, Capability::QaReview->value,
                Capability::QaApprove->value, Capability::ViewReports->value,
            ],
            self::Qa => [
                Capability::ViewQualifications->value, Capability::QaReview->value,
                Capability::ViewReports->value,
            ],
            self::QcmAdmin => [
                Capability::ManageScheduling->value, Capability::RecordRuns->value,
                Capability::ManageClasses->value, Capability::ManageAttendance->value,
                Capability::ViewQualifications->value, Capability::ViewReports->value,
            ],
            self::QcmScheduler => [
                Capability::ManageScheduling->value, Capability::ManageClasses->value,
                Capability::ViewQualifications->value,
            ],
            self::Qcm => [
                Capability::RecordRuns->value, Capability::ViewQualifications->value,
            ],
            self::TrainingCoordinator => [
                Capability::ManageClasses->value, Capability::ManageAttendance->value,
                Capability::ViewQualifications->value,
            ],
            self::ViewOnly => [
                Capability::ViewOnly->value, Capability::ViewQualifications->value,
                Capability::ViewReports->value,
            ],
            self::Operator => [],
        };
    }
}
