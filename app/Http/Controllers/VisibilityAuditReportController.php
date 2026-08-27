<?php

namespace App\Http\Controllers;

use App\Models\VisibilityAuditPurchase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public, permanent link a Visibility Audit report is shared through
 * (step 4 of the post-payment conversion pipeline) — no login required,
 * same "public offer page" precedent as VisibilityAuditOfferController,
 * authorized purely by knowing the unguessable report_token (same shape as
 * PartnerUploadController's own token-only public access).
 */
class VisibilityAuditReportController extends Controller
{
    public function show(string $token): StreamedResponse
    {
        $purchase = VisibilityAuditPurchase::where('report_token', $token)->firstOrFail();

        $attachment = $purchase->reportAttachment();
        abort_unless($attachment !== null, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
