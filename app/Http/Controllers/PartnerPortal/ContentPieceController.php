<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Enums\ContentStatus;
use App\Models\ContentPiece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentPieceController extends PartnerPortalController
{
    /**
     * Logged-in counterpart to PartnerUploadController::store() — same
     * attachment-creation and status-advance logic, scoped to the partner's
     * own content piece instead of a one-off anonymous upload token.
     */
    public function upload(Request $request, ContentPiece $contentPiece): RedirectResponse
    {
        abort_unless($contentPiece->partner_id === $this->partner()->id, 404);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,pdf'],
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('partner-uploads', 'local');

            $contentPiece->attachments()->create([
                'uploaded_by' => null,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $waitingStatuses = [
            ContentStatus::PendingFromAgency->value,
            ContentStatus::SentToPartner->value,
        ];

        if (in_array($contentPiece->status->value, $waitingStatuses)) {
            $contentPiece->update(['status' => ContentStatus::Received->value]);
        }

        return back()->with('status', 'Files uploaded successfully. NEDS will review and get back to you.');
    }
}
