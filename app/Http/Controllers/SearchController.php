<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetPermit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IncidentReport;
use App\Models\Location;
use App\Models\Recommendation;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class SearchController extends Controller
{
    /**
     * Every searchable module: the label shown in the results, the permission
     * needed to see the group, and how to turn a matching row into a result.
     */
    private const TYPES = [
        'asset'          => ['label' => 'Assets',              'permission' => 'assets'],
        'employee'       => ['label' => 'Employees',           'permission' => 'employees'],
        'permit'         => ['label' => 'Permits',             'permission' => 'permits'],
        'incident'       => ['label' => 'Incident Reports',    'permission' => 'incidents'],
        'recommendation' => ['label' => 'Recommendations',     'permission' => 'recommendations'],
        'movement'       => ['label' => 'Asset Movements',     'permission' => 'assets'],
        'location'       => ['label' => 'Locations',           'permission' => 'locations'],
        'department'     => ['label' => 'Departments',         'permission' => 'departments'],
        'category'       => ['label' => 'Categories',          'permission' => 'categories'],
        'brand'          => ['label' => 'Brands',              'permission' => 'brands'],
        'condition'      => ['label' => 'Conditions',          'permission' => 'conditions'],
        'signatory'      => ['label' => 'Signatories',         'permission' => 'signatories'],
        'user'           => ['label' => 'Users',               'permission' => null], // admin only
    ];

    /** Quick autocomplete suggestions — returns a small grouped set. */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (! $this->isSearchable($q)) {
            return response()->json(['groups' => []]);
        }

        $groups = [];
        foreach ($this->collectAll($request, $q, perGroupLimit: 5) as $type => $items) {
            if ($items->isEmpty()) {
                continue;
            }
            $groups[] = [
                'type'  => $type,
                'label' => self::TYPES[$type]['label'],
                'items' => $items->values()->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }

    /** Full results page. */
    public function page(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $groups = [];
        $total  = 0;

        if ($this->isSearchable($q)) {
            foreach ($this->collectAll($request, $q, perGroupLimit: 25) as $type => $items) {
                $count  = $items->count();
                $total += $count;
                $groups[] = [
                    'type'  => $type,
                    'label' => self::TYPES[$type]['label'],
                    'items' => $items->values()->all(),
                    'count' => $count,
                ];
            }
        }

        return Inertia::render('Search/Index', [
            'query'  => $q,
            'groups' => $groups,
            'total'  => $total,
        ]);
    }

    /**
     * A one-character query matches almost everything, so require two — unless
     * the user quoted it, which is an explicit "I really mean this".
     */
    private function isSearchable(string $q): bool
    {
        return $q !== '' && (mb_strlen($q) >= 2 || str_contains($q, '"'));
    }

    /**
     * Runs the keyword search against every module the user may view.
     *
     * @return array<string, Collection<int, array>>
     */
    private function collectAll(Request $request, string $q, int $perGroupLimit): array
    {
        $take = fn (Builder $query) => $query->search($q)->limit($perGroupLimit)->get();

        $results = [
            'asset' => fn () => $take(
                Asset::query()->with([
                    'brand:id,name',
                    'category:id,name',
                    'currentHolder:id,first_name,middle_name,last_name',
                    'currentLocation:id,name',
                ])
            )->map(fn ($a) => [
                'id'       => $a->id,
                'title'    => $a->asset_tag,
                'subtitle' => $this->join([
                    $a->category?->name,
                    trim(($a->brand?->name ?? '') . ' ' . ($a->model ?? '')),
                    $a->serial_number ? "SN {$a->serial_number}" : null,
                    $a->currentHolder ? 'Held by ' . $a->currentHolder->full_name : null,
                    $a->currentLocation?->name,
                ]),
                'url' => "/assets/{$a->id}",
            ]),

            'employee' => fn () => $take(
                Employee::query()->with(['department:id,name', 'location:id,name'])
            )->map(fn ($e) => [
                'id'       => $e->id,
                'title'    => $e->full_name,
                'subtitle' => $this->join([
                    $e->employee_no,
                    $e->position,
                    $e->department?->name,
                    $e->location?->name,
                ]),
                'url' => '/employees?search=' . urlencode($e->employee_no ?: $e->full_name),
            ]),

            'permit' => fn () => $take(
                AssetPermit::query()->with('employee:id,first_name,middle_name,last_name')
            )->map(fn ($p) => [
                'id'       => $p->id,
                'title'    => $p->permit_no ?: "Permit #{$p->id}",
                'subtitle' => $this->join([
                    $p->employee?->full_name ?: $p->employee_name,
                    $p->destination,
                    $p->status,
                    $p->date_borrow?->format('Y-m-d'),
                ]),
                'url' => "/permits/{$p->id}",
            ]),

            'incident' => fn () => $take(
                IncidentReport::query()->with(['asset:id,asset_tag', 'endUser:id,first_name,middle_name,last_name'])
            )->map(fn ($i) => [
                'id'       => $i->id,
                'title'    => $i->ir_no ?: "Incident #{$i->id}",
                'subtitle' => $this->join([
                    $i->asset?->asset_tag,
                    $i->endUser?->full_name ?: $i->end_user_name,
                    $this->snippet($i->reported_problem),
                    $i->status,
                ]),
                'url' => "/incidents/{$i->id}",
            ]),

            'recommendation' => fn () => $take(
                Recommendation::query()->with(['asset:id,asset_tag', 'requestor:id,first_name,middle_name,last_name'])
            )->map(fn ($r) => [
                'id'       => $r->id,
                'title'    => $r->doc_no ?: "Recommendation #{$r->id}",
                'subtitle' => $this->join([
                    $r->subject,
                    $r->asset?->asset_tag,
                    $r->requestor?->full_name ?: $r->requestor_name,
                    $r->status,
                ]),
                'url' => "/recommendations/{$r->id}",
            ]),

            'movement' => fn () => $take(
                AssetMovement::query()->with([
                    'asset:id,asset_tag',
                    'fromEmployee:id,first_name,middle_name,last_name',
                    'toEmployee:id,first_name,middle_name,last_name',
                    'toLocation:id,name',
                ])
            )->map(fn ($m) => [
                'id'       => $m->id,
                'title'    => $this->join([$m->asset?->asset_tag, ucfirst((string) $m->type)]),
                'subtitle' => $this->join([
                    $m->movement_date?->format('Y-m-d'),
                    $m->fromEmployee || $m->toEmployee
                        ? trim(($m->fromEmployee?->full_name ?: '—') . ' → ' . ($m->toEmployee?->full_name ?: '—'))
                        : null,
                    $m->toLocation?->name,
                    $m->reference,
                ]),
                'url' => $m->asset_id ? "/assets/{$m->asset_id}" : '/assets',
            ]),

            'location' => fn () => $take(Location::query())->map(fn ($l) => [
                'id'       => $l->id,
                'title'    => $l->name,
                'subtitle' => $this->join([$l->building, $l->floor, $l->room, $l->description]) ?: 'Location',
                'url'      => '/locations?search=' . urlencode($l->name),
            ]),

            'department' => fn () => $take(Department::query())->map(fn ($d) => [
                'id'       => $d->id,
                'title'    => $d->name,
                'subtitle' => $this->join([$d->code, 'Department']),
                'url'      => '/departments?search=' . urlencode($d->name),
            ]),

            'category' => fn () => $take(Category::query())->map(fn ($c) => [
                'id'       => $c->id,
                'title'    => $c->name,
                'subtitle' => $this->join([$c->prefix, $c->description]) ?: 'Category',
                'url'      => '/categories?search=' . urlencode($c->name),
            ]),

            'brand' => fn () => $take(Brand::query())->map(fn ($b) => [
                'id'       => $b->id,
                'title'    => $b->name,
                'subtitle' => $b->description ?: 'Brand',
                'url'      => '/brands?search=' . urlencode($b->name),
            ]),

            'condition' => fn () => $take(Condition::query())->map(fn ($c) => [
                'id'       => $c->id,
                'title'    => $c->name,
                'subtitle' => $c->description ?: 'Condition',
                'url'      => '/conditions?search=' . urlencode($c->name),
            ]),

            'signatory' => fn () => $take(Signatory::query())->map(fn ($s) => [
                'id'       => $s->id,
                'title'    => $s->name,
                'subtitle' => $this->join([$s->title, str_replace('_', ' ', (string) $s->role)]),
                'url'      => '/signatories', // the signatories page lists all, no search filter
            ]),

            'user' => fn () => $take(User::query())->map(fn ($u) => [
                'id'       => $u->id,
                'title'    => $u->name,
                'subtitle' => $this->join([$u->username, $u->email, $u->is_admin ? 'Admin' : null]),
                'url'      => '/users?search=' . urlencode($u->username ?: $u->email),
            ]),
        ];

        // Only search what this user is allowed to open.
        return collect($results)
            ->filter(fn ($_, $type) => $this->canView($request, $type))
            ->map(fn ($resolve) => $resolve())
            ->all();
    }

    private function canView(Request $request, string $type): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $permission = self::TYPES[$type]['permission'] ?? null;

        // Groups without a permission key (users) are admin-only.
        return $permission === null
            ? (bool) $user->is_admin
            : $user->hasPermission($permission, 'view');
    }

    /** Joins the non-empty parts of a subtitle with a middle dot. */
    private function join(array $parts): string
    {
        return collect($parts)
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->implode(' · ');
    }

    private function snippet(?string $text, int $length = 60): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));

        return $text === '' ? null : mb_strimwidth($text, 0, $length, '…');
    }
}
