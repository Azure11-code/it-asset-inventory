<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncidentReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = IncidentReport::query()
            ->with(['asset:id,asset_tag', 'endUser:id,first_name,middle_name,last_name'])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($w) => $w->where('ir_no', 'like', "%{$s}%")
                                       ->orWhere('reported_problem', 'like', "%{$s}%"))
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => [
                'id'               => $r->id,
                'ir_no'            => $r->ir_no,
                'asset'            => $r->asset ? ['id' => $r->asset->id, 'asset_tag' => $r->asset->asset_tag] : null,
                'end_user'         => $r->endUser ? $r->endUser->full_name : ($r->end_user_name ?: null),
                'reported_problem' => $r->reported_problem,
                'report_date'      => $r->report_date?->format('Y-m-d'),
                'status'           => $r->status,
            ]);

        return Inertia::render('Incidents/Index', [
            'reports' => $reports,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Incidents/Create', [
            'report'   => null,
            'lookups'  => $this->lookups(),
            'defaults' => [
                'ir_no'       => IncidentReport::nextDocNo(),
                'report_date' => now()->format('Y-m-d'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $report = IncidentReport::create($data);

        return redirect()->route('incidents.show', $report)->with('success', 'Incident report created.');
    }

    public function show(IncidentReport $incident)
    {
        $incident->load([
            'asset.category', 'asset.brand',
            'endUser.department',
            'preparedBy', 'notedBy', 'notedBySecondary', 'approvedBy',
        ]);

        return Inertia::render('Incidents/Show', [
            'report' => $this->serialize($incident),
        ]);
    }

    public function edit(IncidentReport $incident)
    {
        return Inertia::render('Incidents/Create', [
            'report'   => $this->serialize($incident, forForm: true),
            'lookups'  => $this->lookups(),
            'defaults' => null,
        ]);
    }

    public function update(Request $request, IncidentReport $incident)
    {
        $data = $this->validateData($request, $incident);
        $incident->update($data);

        return redirect()->route('incidents.show', $incident)->with('success', 'Incident report updated.');
    }

    public function destroy(IncidentReport $incident)
    {
        $incident->delete();
        return redirect()->route('incidents.index')->with('success', 'Incident report deleted.');
    }

    public function docx(IncidentReport $incident, \App\Services\DocxExporter $exporter)
    {
        return $exporter->incident($incident);
    }

    private function validateData(Request $request, ?IncidentReport $report = null): array
    {
        return $request->validate([
            'ir_no'                       => ['required', 'string', 'max:50',
                                              \Illuminate\Validation\Rule::unique('incident_reports', 'ir_no')->ignore($report?->id)],
            'asset_id'                    => ['nullable', 'exists:assets,id'],
            'end_user_employee_id'        => ['nullable', 'required_without:end_user_name', 'exists:employees,id'],
            'end_user_name'               => ['nullable', 'required_without:end_user_employee_id', 'string', 'max:255'],
            'reported_problem'            => ['required', 'string', 'max:500'],
            'action_taken'                => ['required', 'string'],
            'findings'                    => ['required', 'string'],
            'recommendation'              => ['required', 'string'],
            'prepared_by_user_id'         => ['nullable', 'exists:users,id'],
            'noted_by_user_id'            => ['nullable', 'exists:users,id'],
            'noted_by_secondary_user_id'  => ['nullable', 'exists:users,id'],
            'approved_by_user_id'         => ['nullable', 'exists:users,id'],
            'report_date'                 => ['required', 'date'],
            'status'                      => ['required', \Illuminate\Validation\Rule::in(['draft', 'submitted', 'approved', 'closed'])],
        ]);
    }

    private function serialize(IncidentReport $r, bool $forForm = false): array
    {
        $base = [
            'id'                          => $r->id,
            'ir_no'                       => $r->ir_no,
            'report_date'                 => $r->report_date?->format('Y-m-d'),
            'asset_id'                    => $r->asset_id,
            'end_user_employee_id'        => $r->end_user_employee_id,
            'end_user_name'               => $r->end_user_name,
            'reported_problem'            => $r->reported_problem,
            'action_taken'                => $r->action_taken,
            'findings'                    => $r->findings,
            'recommendation'              => $r->recommendation,
            'prepared_by_user_id'         => $r->prepared_by_user_id,
            'noted_by_user_id'            => $r->noted_by_user_id,
            'noted_by_secondary_user_id'  => $r->noted_by_secondary_user_id,
            'approved_by_user_id'         => $r->approved_by_user_id,
            'status'                      => $r->status,
        ];

        if ($forForm) return $base;

        return array_merge($base, [
            'asset' => $r->asset ? [
                'id'              => $r->asset->id,
                'asset_tag'       => $r->asset->asset_tag,
                'category'        => $r->asset->category?->name,
                'brand'           => $r->asset->brand?->name,
                'model'           => $r->asset->model,
                'serial_number'   => $r->asset->serial_number,
                'purchase_date'   => $r->asset->purchase_date?->format('M d, Y'),
                'deployment_date' => $r->asset->deployment_date?->format('M d, Y'),
                'lifespan_years'  => $r->asset->expected_lifespan_years,
                'specifications'  => $r->asset->specifications,
            ] : null,
            'end_user'           => $r->endUser?->full_name ?? $r->end_user_name,
            'prepared_by'        => $r->preparedBy?->name,
            'noted_by'           => $r->notedBy?->name,
            'noted_by_secondary' => $r->notedBySecondary?->name,
            'approved_by'        => $r->approvedBy?->name,
        ]);
    }

    private function lookups(): array
    {
        return [
            'assets' => Asset::select('id', 'asset_tag', 'serial_number', 'model', 'description')
                ->orderBy('asset_tag')
                ->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->asset_tag . ' — ' . ($a->model ?: ($a->description ?: 'Asset'))])->values(),
            'employees' => Employee::orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->values(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
