<?php

namespace App\Http\Controllers;

use App\Models\ClientAsset;
use App\Models\ClientAssetVersion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream a client asset's current or an archived version's file. Same
 * private-disk streamed-download pattern as AttachmentController/
 * TeamResourceController — authorization piggybacks on the parent Customer's
 * own `view` policy, never a dedicated ClientAsset(Version) policy.
 */
class ClientAssetController extends Controller
{
    public function download(ClientAsset $clientAsset): StreamedResponse
    {
        $this->authorize('view', $clientAsset->customer);

        abort_unless(Storage::disk($clientAsset->disk)->exists($clientAsset->path), 404);

        return Storage::disk($clientAsset->disk)->download($clientAsset->path, $clientAsset->original_name);
    }

    public function downloadVersion(ClientAssetVersion $clientAssetVersion): StreamedResponse
    {
        $this->authorize('view', $clientAssetVersion->clientAsset->customer);

        abort_unless(Storage::disk($clientAssetVersion->disk)->exists($clientAssetVersion->path), 404);

        return Storage::disk($clientAssetVersion->disk)->download($clientAssetVersion->path, $clientAssetVersion->original_name);
    }
}
