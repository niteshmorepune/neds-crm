<x-layouts.public title="Upload Content — NEDS">
    @if (session('success'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-4 text-sm text-green-800">
            <p class="font-medium">{{ session('success') }}</p>
            <p class="mt-1 text-green-700">You can upload more files below or close this page.</p>
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow-sm space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Upload Content</h1>
            <p class="mt-1 text-sm text-gray-500">Upload the files for this content piece. The NEDS team will review and publish them.</p>
        </div>

        <dl class="rounded-md bg-gray-50 p-4 text-sm space-y-1">
            <div class="flex gap-2"><span class="text-gray-400 w-24 shrink-0">Title:</span><span class="font-medium">{{ $piece->title }}</span></div>
            <div class="flex gap-2"><span class="text-gray-400 w-24 shrink-0">Platform:</span><span>{{ $piece->platform->label() }}</span></div>
            @if ($piece->publish_date)
                <div class="flex gap-2"><span class="text-gray-400 w-24 shrink-0">Publish date:</span><span>{{ $piece->publish_date->format('d M Y') }}</span></div>
            @endif
            @if ($piece->copy_text)
                <div class="pt-2">
                    <p class="text-gray-400 mb-1">Copy / Brief:</p>
                    <div class="whitespace-pre-wrap text-gray-700 bg-white rounded border border-gray-200 p-3 text-sm">{{ $piece->copy_text }}</div>
                </div>
            @endif
        </dl>

        <form method="POST" action="{{ route('partner-upload.store', $piece->upload_token) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select files *</label>
                <input type="file" name="files[]" multiple accept="image/*,video/*,.pdf"
                       class="block w-full text-sm text-gray-700 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
                <p class="mt-1 text-xs text-gray-400">Accepted: images (JPG, PNG, GIF, WebP), videos (MP4, MOV, AVI, MKV), PDF. Max 50 MB per file.</p>
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Upload Files
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-xs text-gray-400">
        This is a secure, one-time upload link from Niranjan Enterprises Digital Solutions.
        Link expires: {{ $piece->upload_token_expires_at->setTimezone('Asia/Kolkata')->format('d M Y, H:i') }} IST
    </p>
</x-layouts.public>
