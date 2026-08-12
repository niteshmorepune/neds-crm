<?php

namespace App\Http\Controllers;

use App\Models\TeamResource;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamResourceController extends Controller
{
    /**
     * Stream a resource file after checking it's visible to this user's
     * role. Same private-disk streamed-download pattern as
     * AttachmentController::download() — never a public/symlinked URL.
     */
    public function download(TeamResource $teamResource): StreamedResponse
    {
        $this->authorize('view', $teamResource);

        abort_unless(Storage::disk($teamResource->disk)->exists($teamResource->path), 404);

        return Storage::disk($teamResource->disk)->download($teamResource->path, $teamResource->original_name);
    }
}
