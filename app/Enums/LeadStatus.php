<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::Lost], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Status values considered "open" — shared by LeadObserver::autoAssign(),
     * the bulk lead-handover-on-deactivation flow, and anywhere else that
     * needs to count leads still actively being worked.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen())),
        );
    }
}
