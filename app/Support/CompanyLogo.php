<?php

namespace App\Support;

/**
 * Base64 data URI for the NEDS logo, for embedding in a dompdf-rendered PDF
 * -- dompdf's handling of a plain file path for images is inconsistent
 * across environments, so every embedded PDF image in this app goes through
 * a data URI instead (same choice made for UpiQrCode).
 */
class CompanyLogo
{
    private static ?string $cached = null;

    public static function dataUri(): ?string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $path = public_path('images/neds-logo-square.png');

        if (! is_file($path)) {
            return null;
        }

        return self::$cached = 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
