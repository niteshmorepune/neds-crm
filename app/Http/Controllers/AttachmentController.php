<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Stream an attachment after checking the user may view its parent record.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->attachable);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        $this->authorize('update', $attachment->attachable);

        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }
}
