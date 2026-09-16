<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AssetPermit;
use App\Models\Employee;
use App\Models\IncidentReport;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GalleryController extends Controller
{
    /**
     * Same shape as AttachmentController::ATTACHABLES — kept local so this page
     * stays self-contained. Adding a new entity here + on AttachmentController
     * (plus HasAttachments on the model) is all that's needed for it to appear.
     */
    private const ATTACHABLES = [
        'recommendations' => ['model' => Recommendation::class, 'perm' => 'recommendations', 'label' => 'Recommendation'],
        'incidents'       => ['model' => IncidentReport::class,  'perm' => 'incidents',       'label' => 'Incident'],
        'permits'         => ['model' => AssetPermit::class,     'perm' => 'permits',         'label' => 'Permit'],
        'accountability'  => ['model' => Employee::class,        'perm' => 'accountability',  'label' => 'Accountability'],
    ];

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const PDF_MIMES   = ['application/pdf'];

    public function index(Request $request)
    {
        $user = $request->user();

        // Which entity types can this user see? (based on view perm per resource)
        $allowedTypes = [];
        foreach (self::ATTACHABLES as $slug => $info) {
            if ($user->hasPermission($info['perm'], 'view')) {
                $allowedTypes[$info['model']] = ['slug' => $slug, 'label' => $info['label']];
            }
        }

        abort_if(empty($allowedTypes), 403, 'You do not have permission to view any attachments.');

        // Base query — restricted to types the user can see.
        $base = Attachment::query()->whereIn('attachable_type', array_keys($allowedTypes));

        // Apply UI filters.
        $query = (clone $base)->with('uploader:id,name')->latest('id');

        if ($request->filled('entity') && isset(self::ATTACHABLES[$request->entity])) {
            $entityModel = self::ATTACHABLES[$request->entity]['model'];
            if (isset($allowedTypes[$entityModel])) {
                $query->where('attachable_type', $entityModel);
            }
        }

        if ($request->kind === 'images') {
            $query->whereIn('mime_type', self::IMAGE_MIMES);
        } elseif ($request->kind === 'pdf') {
            $query->whereIn('mime_type', self::PDF_MIMES);
        } elseif ($request->kind === 'docs') {
            $query->whereNotIn('mime_type', array_merge(self::IMAGE_MIMES, self::PDF_MIMES));
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $paginator = $query->paginate(30)->withQueryString();

        // Resolve polymorphic parents in one round-trip per type — the entity page
        // link + a friendly title come from these.
        $items   = $paginator->getCollection();
        $grouped = $items->groupBy('attachable_type');
        $parents = [];
        foreach ($grouped as $type => $rows) {
            $ids   = $rows->pluck('attachable_id')->unique()->all();
            $found = $type::whereIn('id', $ids)->get()->keyBy('id');
            $parents[$type] = $found;
        }

        $paginator->setCollection($items->map(function ($a) use ($allowedTypes, $parents) {
            $type       = $a->attachable_type;
            $entitySlug = $allowedTypes[$type]['slug'];
            $parent     = $parents[$type]->get($a->attachable_id) ?? null;

            $parentTitle    = $this->parentTitle($entitySlug, $parent, $a->attachable_id);
            $parentSubtitle = $this->parentSubtitle($entitySlug, $parent);
            $sourceUrl      = $this->parentUrl($entitySlug, $a->attachable_id);

            $isImage = in_array($a->mime_type, self::IMAGE_MIMES, true);
            $isPdf   = in_array($a->mime_type, self::PDF_MIMES, true);

            return [
                'id'              => $a->id,
                'original_name'   => $a->original_name,
                'mime_type'       => $a->mime_type,
                'size_bytes'      => $a->size_bytes,
                'label'           => $a->label,
                'uploaded_by'     => $a->uploader?->name,
                'created_at'      => $a->created_at?->format('Y-m-d H:i'),
                'entity_slug'     => $entitySlug,
                'entity_label'    => $allowedTypes[$type]['label'],
                'entity_id'       => $a->attachable_id,
                'parent_title'    => $parentTitle,
                'parent_subtitle' => $parentSubtitle,
                'is_image'        => $isImage,
                'is_pdf'          => $isPdf,
                'is_previewable'  => $isImage || $isPdf,
                'preview_url'     => ($isImage || $isPdf) ? route('attachments.preview', ['entity' => $entitySlug, 'id' => $a->attachable_id, 'attachment' => $a->id]) : null,
                'download_url'    => route('attachments.download', ['entity' => $entitySlug, 'id' => $a->attachable_id, 'attachment' => $a->id]),
                'source_url'      => $sourceUrl,
            ];
        }));

        // Totals — based on unfiltered allowed set (drives the count badges on filter chips).
        $totalByEntity = [];
        foreach (self::ATTACHABLES as $slug => $info) {
            if (isset($allowedTypes[$info['model']])) {
                $totalByEntity[$slug] = Attachment::where('attachable_type', $info['model'])->count();
            }
        }

        return Inertia::render('Gallery/Index', [
            'attachments' => $paginator,
            'filters'     => $request->only('search', 'entity', 'kind'),
            'available_entities' => collect(self::ATTACHABLES)
                ->filter(fn ($info) => isset($allowedTypes[$info['model']]))
                ->map(fn ($info, $slug) => ['slug' => $slug, 'label' => $info['label']])
                ->values(),
            'totals' => [
                'all'       => (clone $base)->count(),
                'images'    => (clone $base)->whereIn('mime_type', self::IMAGE_MIMES)->count(),
                'pdf'       => (clone $base)->whereIn('mime_type', self::PDF_MIMES)->count(),
                'docs'      => (clone $base)->whereNotIn('mime_type', array_merge(self::IMAGE_MIMES, self::PDF_MIMES))->count(),
                'by_entity' => $totalByEntity,
            ],
        ]);
    }

    private function parentTitle(string $slug, ?object $parent, int $id): string
    {
        if (!$parent) return '(deleted #' . $id . ')';
        return match ($slug) {
            'recommendations' => $parent->doc_no    ?? '#' . $parent->id,
            'incidents'       => $parent->ir_no     ?? '#' . $parent->id,
            'permits'         => $parent->permit_no ?? '#' . $parent->id,
            'accountability'  => $parent->employee_no ? ($parent->full_name . ' (' . $parent->employee_no . ')') : $parent->full_name,
            default           => '#' . $parent->id,
        };
    }

    private function parentSubtitle(string $slug, ?object $parent): ?string
    {
        if (!$parent) return null;
        return match ($slug) {
            'recommendations' => $parent->subject          ?? null,
            'incidents'       => $parent->reported_problem ?? null,
            'permits'         => $parent->destination      ?? null,
            'accountability'  => 'Signed accountability form',
            default           => null,
        };
    }

    private function parentUrl(string $slug, int $id): string
    {
        return match ($slug) {
            'accountability' => '/accountability',
            default          => '/' . $slug . '/' . $id,
        };
    }
}
