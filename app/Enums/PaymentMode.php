<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Upi = 'upi';
    case Neft = 'neft';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Gateway = 'gateway';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Neft => 'NEFT',
            self::Cheque => 'Cheque',
            self::Cash => 'Cash',
            self::Gateway => 'Payment Gateway',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
