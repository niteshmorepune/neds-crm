<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Renders a UPI "Scan to Pay" QR code as a PNG data URI, ready to drop
 * straight into an <img src="..."> tag. PNG via endroid/qr-code's GD writer
 * (no imagick needed) rather than inline SVG -- dompdf's SVG support
 * couldn't render bacon-qr-code's nested-transform/path output at all
 * (confirmed empirically: a blank box where the QR should be), while PNG
 * embeds reliably since that's dompdf's normal image path.
 */
class UpiQrCode
{
    /**
     * Standard UPI deep-link payload (`upi://pay?...`), scannable by any UPI
     * app (GPay, PhonePe, Paytm, etc). $amountRupees pre-fills the amount so
     * the payer doesn't have to type it in; omitted when null or 0 -- some
     * UPI apps reject a QR with am=0 rather than just leaving it blank.
     */
    public static function dataUri(string $upiId, string $payeeName, ?float $amountRupees, string $note): string
    {
        $params = [
            'pa' => $upiId,
            'pn' => $payeeName,
            'cu' => 'INR',
            'tn' => $note,
        ];

        if ($amountRupees !== null && $amountRupees > 0) {
            $params['am'] = number_format($amountRupees, 2, '.', '');
        }

        $url = 'upi://pay?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            size: 140,
            margin: 4,
        ))->build();

        return $result->getDataUri();
    }
}
