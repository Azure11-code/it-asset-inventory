<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $recs = Recommendation::query()
            ->with(['asset:id,asset_tag', 'requestor:id,first_name,middle_name,last_name'])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($w) => $w->where('doc_no', 'like', "%{$s}%")
                                       ->orWhere('subject', 'like', "%{$s}%"))
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => [
                'id'          => $r->id,
                'doc_no'      => $r->doc_no,
                'asset'       => $r->asset ? ['id' => $r->asset->id, 'asset_tag' => $r->asset->asset_tag] : null,
                'requestor'   => $r->requestor ? $r->requestor->full_name : ($r->requestor_name ?: null),
                'subject'     => $r->subject,
                'report_date' => $r->report_date?->format('Y-m-d'),
                'status'      => $r->status,
            ]);

        return Inertia::render('Recommendations/Index', [
            'recommendations' => $recs,
            'filters'         => $request->only('search', 'status'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Recommendations/Create', [
            'recommendation' => null,
            'lookups'        => $this->lookups(),
            'defaults'       => [
                'doc_no'      => Recommendation::nextDocNo(),
                'report_date' => now()->format('Y-m-d'),
                'thru'        => 'Information Technology Department',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $rec  = Recommendation::create($data);

        return redirect()->route('recommendations.show', $rec)->with('success', 'Recommendation created.');
    }

    public function show(Recommendation $recommendation)
    {
        $recommendation->load([
            'asset.category', 'asset.brand',
            'requestor.department',
            'preparedBy', 'reviewedBy', 'notedBy',
        ]);

        return Inertia::render('Recommendations/Show', [
            'recommendation' => $this->serialize($recommendation),
        ]);
    }

    public function edit(Recommendation $recommendation)
    {
        return Inertia::render('Recommendations/Create', [
            'recommendation' => $this->serialize($recommendation, forForm: true),
            'lookups'        => $this->lookups(),
            'defaults'       => null,
        ]);
    }

    public function update(Request $request, Recommendation $recommendation)
    {
        $data = $this->validateData($request, $recommendation);
        $recommendation->update($data);

        return redirect()->route('recommendations.show', $recommendation)->with('success', 'Recommendation updated.');
    }

    public function destroy(Recommendation $recommendation)
    {
        $recommendation->delete();
        return redirect()->route('recommendations.index')->with('success', 'Recommendation deleted.');
    }

    public function docx(Recommendation $recommendation, \App\Services\DocxExporter $exporter)
    {
        return $exporter->recommendation($recommendation);
    }

    private function validateData(Request $request, ?Recommendation $rec = null): array
    {
        return $request->validate([
            'doc_no'                => ['required', 'string', 'max:50',
                                         \Illuminate\Validation\Rule::unique('recommendations', 'doc_no')->ignore($rec?->id)],
            'report_date'           => ['required', 'date'],
            'asset_id'              => ['nullable', 'exists:assets,id'],
            'requestor_employee_id' => ['nullable', 'required_without:requestor_name', 'exists:employees,id'],
            'requestor_name'        => ['nullable', 'required_without:requestor_employee_id', 'string', 'max:255'],
            'requestor_employee_no' => ['nullable', 'string', 'max:50'],
            'requestor_position'    => ['nullable', 'string', 'max:255'],
            'requestor_department'  => ['nullable', 'string', 'max:255'],
            'thru'                  => ['required', 'string', 'max:255'],
            'subject'               => ['required', 'string', 'max:255'],
            'body'                  => ['required', 'string'],
            'quick_specs'           => ['nullable', 'array'],
            'quick_specs.*.label'   => ['nullable', 'string', 'max:100'],
            'quick_specs.*.value'   => ['nullable', 'string', 'max:255'],
            'quick_specs.*.note'    => ['nullable', 'string', 'max:255'],
            'prepared_by_user_id'   => ['nullable', 'exists:users,id'],
            'reviewed_by_user_id'   => ['nullable', 'exists:users,id'],
            'noted_by_user_id'      => ['nullable', 'exists:users,id'],
            'status'                => ['required', \Illuminate\Validation\Rule::in(['draft', 'submitted', 'approved', 'closed'])],
        ]);
    }

    private function serialize(Recommendation $r, bool $forForm = false): array
    {
        $base = [
            'id'                    => $r->id,
            'doc_no'                => $r->doc_no,
            'report_date'           => $r->report_date?->format('Y-m-d'),
            'asset_id'              => $r->asset_id,
            'requestor_employee_id' => $r->requestor_employee_id,
            'requestor_name'        => $r->requestor_name,
            'requestor_employee_no' => $r->requestor_employee_no,
            'requestor_position'    => $r->requestor_position,
            'requestor_department'  => $r->requestor_department,
            'thru'                  => $r->thru,
            'subject'               => $r->subject,
            'body'                  => $r->body,
            'quick_specs'           => $r->quick_specs ?? [],
            'prepared_by_user_id'   => $r->prepared_by_user_id,
            'reviewed_by_user_id'   => $r->reviewed_by_user_id,
            'noted_by_user_id'      => $r->noted_by_user_id,
            'status'                => $r->status,
        ];

        if ($forForm) return $base;

        return array_merge($base, [
            'asset'        => $r->asset ? [
                'id'        => $r->asset->id,
                'asset_tag' => $r->asset->asset_tag,
                'category'  => $r->asset->category?->name,
                'brand'     => $r->asset->brand?->name,
                'model'     => $r->asset->model,
            ] : null,
            'requestor'    => [
                'name'          => $r->requestor?->full_name ?? $r->requestor_name,
                'employee_no'   => $r->requestor?->employee_no ?? $r->requestor_employee_no,
                'position'      => $r->requestor?->position ?? $r->requestor_position,
                'department'    => $r->requestor?->department?->name ?? $r->requestor_department,
            ],
            'prepared_by'  => $r->preparedBy?->name,
            'reviewed_by'  => $r->reviewedBy?->name,
            'noted_by'     => $r->notedBy?->name,
        ]);
    }

    private function lookups(): array
    {
        return [
            'assets' => Asset::select('id', 'asset_tag', 'serial_number', 'model', 'description', 'specifications')
                ->orderBy('asset_tag')->get()
                ->map(fn ($a) => [
                    'id'             => $a->id,
                    'name'           => $a->asset_tag . ' — ' . ($a->model ?: ($a->description ?: 'Asset')),
                    'specifications' => $a->specifications,
                    'serial_number'  => $a->serial_number,
                ])->values(),
            'employees' => Employee::orderBy('last_name')->get()
                ->map(fn ($e) => [
                    'id'            => $e->id,
                    'name'          => $e->full_name,
                    'employee_no'   => $e->employee_no,
                    'position'      => $e->position,
                    'department_id' => $e->department_id,
                ])->values(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
