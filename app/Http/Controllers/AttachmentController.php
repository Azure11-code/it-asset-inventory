<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AssetPermit;
use App\Models\Employee;
use App\Models\IncidentReport;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    /**
     * Whitelist of entity slugs → model class + permission resource + edit action.
     * Adding a new attachable is just one line here plus the HasAttachments trait on the model.
     */
    private const ATTACHABLES = [
        'recommendations' => ['model' => Recommendation::class, 'perm' => 'recommendations', 'action' => 'edit'],
        'incidents'       => ['model' => IncidentReport::class,  'perm' => 'incidents',       'action' => 'edit'],
        'permits'         => ['model' => AssetPermit::class,     'perm' => 'permits',         'action' => 'edit'],
        'accountability'  => ['model' => Employee::class,        'perm' => 'accountability',  'action' => 'edit'],
    ];

    private const MAX_MB = 15;

    public function store(Request $request, string $entity, int $id)
    {
        [$modelClass, $perm, $action] = $this->resolve($entity);
        $this->ensurePerm($request, $perm, $action);

        $data = $request->validate([
            'file'  => ['required', 'file', 'max:' . (self::MAX_MB * 1024), 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $parent = $modelClass::findOrFail($id);
        $file   = $request->file('file');

        $folder    = 'attachments/' . now()->format('Y/m');
        $storeName = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path      = $file->storeAs($folder, $storeName, 'local');

        $parent->attachments()->create([
            'disk'                => 'local',
            'path'                => $path,
            'original_name'       => $file->getClientOriginalName(),
            'mime_type'           => $file->getMimeType(),
            'size_bytes'          => $file->getSize(),
            'label'               => $data['label'] ?? null,
            'uploaded_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(Request $request, string $entity, int $id, Attachment $attachment): BinaryFileResponse
    {
        [$modelClass, $perm] = $this->resolve($entity);
        $this->ensurePerm($request, $perm, 'view');

        // Ensure the attachment truly belongs to the given parent (URL-tampering guard)
        abort_unless(
            $attachment->attachable_type === $modelClass && $attachment->attachable_id === $id,
            404
        );
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->download(
            $attachment->absolutePath(),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    /**
     * Serve the file inline (no attachment disposition) — used by the Gallery page
     * for image thumbnails and lightbox previews.
     */
    public function preview(Request $request, string $entity, int $id, Attachment $attachment): BinaryFileResponse
    {
        [$modelClass, $perm] = $this->resolve($entity);
        $this->ensurePerm($request, $perm, 'view');

        abort_unless(
            $attachment->attachable_type === $modelClass && $attachment->attachable_id === $id,
            404
        );
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->file(
            $attachment->absolutePath(),
            [
                'Content-Type'  => $attachment->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    public function destroy(Request $request, string $entity, int $id, Attachment $attachment)
    {
        [$modelClass, $perm, $action] = $this->resolve($entity);
        $this->ensurePerm($request, $perm, $action);

        abort_unless(
            $attachment->attachable_type === $modelClass && $attachment->attachable_id === $id,
            404
        );

        $attachment->delete(); // model boot() removes the file too
        return back()->with('success', 'Attachment removed.');
    }

    private function resolve(string $entity): array
    {
        $entry = self::ATTACHABLES[$entity] ?? null;
        abort_unless($entry, 404, "Unknown attachable: {$entity}");
        return [$entry['model'], $entry['perm'], $entry['action']];
    }

    private function ensurePerm(Request $request, string $resource, string $action): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission($resource, $action), 403);
    }
}
