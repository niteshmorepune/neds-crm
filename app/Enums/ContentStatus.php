<?php

namespace App\Enums;

enum ContentStatus: string
{
    // neds_led only
    case CopyDrafting = 'copy_drafting';
    case SentToPartner = 'sent_to_partner';
    // agency_led only
    case PendingFromAgency = 'pending_from_agency';
    // both
    case Received = 'received';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::CopyDrafting => 'Copy Drafting',
            self::SentToPartner => 'Sent to Partner',
            self::PendingFromAgency => 'Pending from Agency',
            self::Received => 'Received',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CopyDrafting => 'bg-gray-100 text-gray-700',
            self::SentToPartner => 'bg-blue-100 text-blue-700',
            self::PendingFromAgency => 'bg-yellow-100 text-yellow-700',
            self::Received => 'bg-purple-100 text-purple-700',
            self::Approved => 'bg-teal-100 text-teal-700',
            self::Scheduled => 'bg-orange-100 text-orange-700',
            self::Published => 'bg-green-100 text-green-700',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Returns the valid next statuses for a given workflow type. */
    public static function allowedNextFor(ContentWorkflowType $type): array
    {
        return match ($type) {
            ContentWorkflowType::AgencyLed => [
                self::PendingFromAgency->value => [self::Received->value],
                self::Received->value => [self::Approved->value],
                self::Approved->value => [self::Scheduled->value],
                self::Scheduled->value => [self::Published->value],
                self::Published->value => [],
            ],
            ContentWorkflowType::NedsLed => [
                self::CopyDrafting->value => [self::SentToPartner->value],
                self::SentToPartner->value => [self::Received->value],
                self::Received->value => [self::Approved->value],
                self::Approved->value => [self::Scheduled->value],
                self::Scheduled->value => [self::Published->value],
                self::Published->value => [],
            ],
        };
    }

    /** Initial status when a content piece is created. */
    public static function initialFor(ContentWorkflowType $type): self
    {
        return match ($type) {
            ContentWorkflowType::AgencyLed => self::PendingFromAgency,
            ContentWorkflowType::NedsLed => self::CopyDrafting,
        };
    }
}
