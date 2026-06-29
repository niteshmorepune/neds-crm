<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\ContentPiece;
use Illuminate\Http\Request;

class PartnerUploadController extends Controller
{
    public function show(string $token)
    {
        $piece = $this->findValidPiece($token);

        return view('partner-upload.show', compact('piece'));
    }

    public function store(Request $request, string $token)
    {
        $piece = $this->findValidPiece($token);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,pdf'],
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('partner-uploads', 'local');

            $piece->attachments()->create([
                'uploaded_by' => null,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        // Only advance to received if still in a "waiting" status
        $waitingStatuses = [
            ContentStatus::PendingFromAgency->value,
            ContentStatus::SentToPartner->value,
        ];

        if (in_array($piece->status->value, $waitingStatuses)) {
            $piece->update(['status' => ContentStatus::Received->value]);
        }

        return back()->with('success', 'Files uploaded successfully. NEDS will review and get back to you.');
    }

    private function findValidPiece(string $token): ContentPiece
    {
        $piece = ContentPiece::where('upload_token', $token)->firstOrFail();

        abort_unless($piece->isUploadTokenValid(), 404, 'This upload link has expired or is no longer valid.');

        return $piece;
    }
}
